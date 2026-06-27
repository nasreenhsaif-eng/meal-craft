<?php

namespace App\Services\Nutrition;

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\CustomerProfile;
use App\Models\Meal;
use App\Support\ChiaBreakfastMeals;

/**
 * Derives per-slot calorie targets from the customer's plan tier.
 *
 * Core day (tier): breakfast + 2× main (scaled) + side salad + dessert (fixed standard portions).
 * When soup is included it uses a fixed standard portion counted within the tier, shrinking scalable slots.
 */
final class UserPlanCalculator
{
    public const CORE_FIXED_PORTION_SLOTS = ['side_salad', 'dessert'];

    /**
     * @return list<int>
     */
    public static function planTiers(): array
    {
        /** @var list<int> $tiers */
        $tiers = config('customer_nutrition.plan_tiers', [1000, 1200, 1500, 1800, 2000]);

        return array_values(array_map(intval(...), $tiers));
    }

    public static function snapToPlanTier(float $calories): float
    {
        $tiers = self::planTiers();

        if ($tiers === []) {
            return max(0.0, round($calories, 2));
        }

        $nearest = $tiers[0];
        $smallestDistance = abs($calories - $nearest);

        foreach ($tiers as $tier) {
            $distance = abs($calories - $tier);
            if ($distance < $smallestDistance) {
                $smallestDistance = $distance;
                $nearest = $tier;
            }
        }

        return (float) $nearest;
    }

    public static function slotBehavior(string $slot): string
    {
        return (string) (config('customer_nutrition.slot_behaviors')[$slot] ?? 'scalable');
    }

    /**
     * @return array{min: float, target: float, max: float}
     */
    public static function slotCalorieBand(string $slot): array
    {
        /** @var array{min?: float, target?: float, max?: float} $band */
        $band = config('customer_nutrition.slot_calorie_bands')[$slot] ?? [];

        return [
            'min' => (float) ($band['min'] ?? 0.0),
            'target' => (float) ($band['target'] ?? 0.0),
            'max' => (float) ($band['max'] ?? 0.0),
        ];
    }

    public static function slotPlanningMidpoint(string $slot): float
    {
        $band = self::slotCalorieBand($slot);

        return round($band['target'], 2);
    }

    /**
     * @param  array<string, float>  $overrides
     */
    public static function coreFixedPortionCaloriesTotal(array $overrides = []): float
    {
        $total = 0.0;

        foreach (self::coreFixedPortionSlots() as $slot) {
            $total += (float) ($overrides[$slot] ?? self::slotPlanningMidpoint($slot));
        }

        return round($total, 2);
    }

    /**
     * @return list<string>
     */
    public static function coreFixedPortionSlots(): array
    {
        /** @var list<string> $slots */
        $slots = config('customer_nutrition.core_fixed_portion_slots', self::CORE_FIXED_PORTION_SLOTS);

        return array_values($slots);
    }

    /**
     * @deprecated Use {@see coreFixedPortionCaloriesTotal()} — soup is no longer subtracted from tier.
     */
    public static function fixedCaloriesTotal(): float
    {
        return self::coreFixedPortionCaloriesTotal();
    }

    /**
     * @deprecated Use {@see slotPlanningMidpoint()} with a slot name.
     */
    public static function fixedCaloriesPerMeal(): float
    {
        return self::slotPlanningMidpoint('side_salad');
    }

    /**
     * @param  array{
     *     include_soup?: bool,
     *     soup_calories?: float,
     *     side_salad_calories?: float,
     *     dessert_calories?: float,
     *     snap_to_tier?: bool,
     *     plan_tier?: float,
     *     fixed_chia_breakfast?: bool,
     * }  $options
     * @return array<string, mixed>
     */
    public static function calculateUserPlan(CustomerProfile $profile, array $options = []): array
    {
        $snapToTier = (bool) ($options['snap_to_tier'] ?? false);

        if (isset($options['plan_tier'])) {
            $planTier = (float) $options['plan_tier'];
            $rawTarget = $planTier;
        } else {
            $rawTarget = (float) $profile->daily_calorie_target;
            $planTier = $snapToTier ? self::snapToPlanTier($rawTarget) : $rawTarget;
        }

        $includeSoup = (bool) ($options['include_soup'] ?? false);

        $sideSaladCalories = round(
            (float) ($options['side_salad_calories'] ?? self::slotPlanningMidpoint('side_salad')),
            2,
        );
        $dessertCalories = round(
            (float) ($options['dessert_calories'] ?? self::slotPlanningMidpoint('dessert')),
            2,
        );
        $soupCalories = $includeSoup
            ? round((float) ($options['soup_calories'] ?? self::slotPlanningMidpoint('soup')), 2)
            : 0.0;

        $fixedChiaBreakfast = (bool) ($options['fixed_chia_breakfast'] ?? false);
        $chiaBreakfastCalories = $fixedChiaBreakfast
            ? round(ChiaBreakfastMeals::fixedCalories(), 2)
            : 0.0;

        $proteinPct = (float) $profile->protein_percentage;
        $carbPct = (float) $profile->carb_percentage;
        $fatPct = (float) $profile->fat_percentage;

        $dailyMacros = self::macroGramsFromCaloriesAndPercentages($planTier, $proteinPct, $carbPct, $fatPct);

        $fixedPortionTotal = round(
            $sideSaladCalories + $dessertCalories + $soupCalories + $chiaBreakfastCalories,
            2,
        );
        $fixedPortionMacros = self::macroGramsFromCaloriesAndPercentages(
            $fixedPortionTotal,
            $proteinPct,
            $carbPct,
            $fatPct,
        );

        $scalableBudgetCalories = max(0.0, round($planTier - $fixedPortionTotal, 2));
        $scalableBudgetMacros = self::macroGramsFromCaloriesAndPercentages(
            $scalableBudgetCalories,
            $proteinPct,
            $carbPct,
            $fatPct,
        );

        $breakfastWeight = (float) config('customer_nutrition.scalable_slot_weights.breakfast', 0.20);
        $mainEachWeight = (float) config('customer_nutrition.scalable_slot_weights.main_each', 0.40);
        $mainCount = max(1, (int) config('customer_nutrition.scalable_slots.main', 2));

        if ($fixedChiaBreakfast) {
            $breakfastTargetCalories = $chiaBreakfastCalories;
            $mainTargetCaloriesEach = round($scalableBudgetCalories / $mainCount, 2);
        } else {
            $breakfastTargetCalories = round($scalableBudgetCalories * $breakfastWeight, 2);
            $mainTargetCaloriesEach = round($scalableBudgetCalories * $mainEachWeight, 2);
        }

        if ($fixedChiaBreakfast) {
            $coreDayCalories = round(
                $fixedPortionTotal + ($mainTargetCaloriesEach * $mainCount),
                2,
            );
        } else {
            $coreDayCalories = round(
                $fixedPortionTotal + $breakfastTargetCalories + ($mainTargetCaloriesEach * $mainCount),
                2,
            );
        }
        $dayTotalCalories = round($coreDayCalories, 2);

        $soupMacros = $includeSoup
            ? self::macroGramsFromCaloriesAndPercentages($soupCalories, $proteinPct, $carbPct, $fatPct)
            : self::macroGramsFromCaloriesAndPercentages(0.0, $proteinPct, $carbPct, $fatPct);

        $baseline = self::resolveScalableBaselineCalories();
        $baselineTotal = $fixedChiaBreakfast
            ? round($baseline['main_calories'] * $mainCount, 2)
            : $baseline['calories'];

        $multiplier = $baselineTotal > 0
            ? $scalableBudgetCalories / $baselineTotal
            : 1.0;

        $multiplier = max(0.0, round($multiplier, 4));

        $perSlotFixed = [
            'side_salad' => $sideSaladCalories,
            'dessert' => $dessertCalories,
        ];

        if ($includeSoup) {
            $perSlotFixed['soup'] = $soupCalories;
        }

        if ($fixedChiaBreakfast) {
            $perSlotFixed['breakfast'] = $chiaBreakfastCalories;
        }

        return [
            'profile_id' => (int) $profile->id,
            'plan_tier' => $planTier,
            'daily_calorie_target' => $rawTarget,
            'protein_percentage' => $proteinPct,
            'carb_percentage' => $carbPct,
            'fat_percentage' => $fatPct,
            'daily_macros' => $dailyMacros,
            'include_soup' => $includeSoup,
            'fixed_chia_breakfast' => $fixedChiaBreakfast,
            'fixed_portion' => [
                'slots' => self::coreFixedPortionSlots(),
                'calories' => $fixedPortionTotal,
                'per_slot' => $perSlotFixed,
                'macros' => $fixedPortionMacros,
            ],
            'optional_add_on' => [
                'soup' => [
                    'included' => $includeSoup,
                    'calories' => $soupCalories,
                    'macros' => $soupMacros,
                ],
            ],
            'core_day_calories' => $coreDayCalories,
            'day_total_calories' => $dayTotalCalories,
            'scalable_budget' => [
                'calories' => $scalableBudgetCalories,
                'macros' => $scalableBudgetMacros,
            ],
            'fixed' => [
                'calories' => $fixedPortionTotal,
                'per_slot' => $perSlotFixed,
                'slots' => self::coreFixedPortionSlots(),
                'macros' => $fixedPortionMacros,
            ],
            'remaining' => [
                'calories' => $scalableBudgetCalories,
                'macros' => $scalableBudgetMacros,
            ],
            'baseline_scalable' => $baseline,
            'scaling_multiplier' => $multiplier,
            'scalable_slot_targets' => [
                'breakfast' => [
                    'calories' => $breakfastTargetCalories,
                    'macros' => self::macroGramsFromCaloriesAndPercentages(
                        $breakfastTargetCalories,
                        $proteinPct,
                        $carbPct,
                        $fatPct,
                    ),
                ],
                'main_each' => [
                    'calories' => $mainTargetCaloriesEach,
                    'macros' => self::macroGramsFromCaloriesAndPercentages(
                        $mainTargetCaloriesEach,
                        $proteinPct,
                        $carbPct,
                        $fatPct,
                    ),
                ],
            ],
        ];
    }

    /**
     * Convert calorie target + macro percentages into gram targets (4/4/9 rule).
     *
     * @return array{protein_g: float, carbs_g: float, fat_g: float}
     */
    public static function macroGramsFromCaloriesAndPercentages(
        float $calories,
        float $proteinPct,
        float $carbPct,
        float $fatPct,
    ): array {
        $calories = max(0.0, $calories);

        return [
            'protein_g' => round(($calories * ($proteinPct / 100.0)) / 4.0, 2),
            'carbs_g' => round(($calories * ($carbPct / 100.0)) / 4.0, 2),
            'fat_g' => round(($calories * ($fatPct / 100.0)) / 9.0, 2),
        ];
    }

    /**
     * Sum of one baseline breakfast + two baseline mains from the meal library averages,
     * falling back to design-time placeholder targets when the library is empty.
     *
     * @return array{
     *     calories: float,
     *     breakfast_calories: float,
     *     main_calories: float,
     *     main_meal_count: int
     * }
     */
    public static function resolveScalableBaselineCalories(): array
    {
        $mainCount = max(1, (int) config('customer_nutrition.scalable_slots.main', 2));
        $configBaselines = config('customer_nutrition.baseline_calories', []);

        $breakfastAvg = self::averageLibraryCaloriesFor(MealType::Breakfast, RecipeCategory::Breakfast);
        $mainAvg = self::averageLibraryCaloriesFor(MealType::Main, RecipeCategory::Meal);

        $breakfastCalories = $breakfastAvg > 0
            ? $breakfastAvg
            : (float) ($configBaselines['breakfast'] ?? self::slotPlanningMidpoint('breakfast'));

        $mainCalories = $mainAvg > 0
            ? $mainAvg
            : (float) ($configBaselines['main'] ?? self::slotPlanningMidpoint('main'));

        $total = $breakfastCalories + ($mainCalories * $mainCount);

        return [
            'calories' => round($total, 2),
            'breakfast_calories' => round($breakfastCalories, 2),
            'main_calories' => round($mainCalories, 2),
            'main_meal_count' => $mainCount,
        ];
    }

    private static function averageLibraryCaloriesFor(MealType $mealType, RecipeCategory $category): float
    {
        $meals = Meal::queryForMealLibrary()
            ->where(function ($query) use ($mealType, $category): void {
                $query->where('meal_type', $mealType->value)
                    ->orWhere('category', $category->value);
            })
            ->where('total_calories', '>', 0)
            ->get(['total_calories']);

        if ($meals->isEmpty()) {
            return 0.0;
        }

        return (float) $meals->avg('total_calories');
    }
}
