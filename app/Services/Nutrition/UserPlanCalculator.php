<?php

namespace App\Services\Nutrition;

use App\Enums\DietProtocol;
use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\CustomerProfile;
use App\Models\Meal;

/**
 * Derives per-slot calorie targets from the customer's plan tier.
 *
 * Core day (tier): breakfast + 2× main (scaled) + 2 fixed picks from {side salad, dessert, soup} (~150 kcal each).
 */
final class UserPlanCalculator
{
    public const CORE_FIXED_PORTION_SLOTS = ['side_salad', 'dessert', 'soup'];

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

    public static function fixedChoiceCaloriesPerSlot(): float
    {
        return round((float) config('customer_nutrition.fixed_choice_calories', 150.0), 2);
    }

    public static function dayCalorieTolerance(): float
    {
        return max(0.0, round((float) config('customer_nutrition.day_calorie_tolerance', 50.0), 2));
    }

    /**
     * @return array{protein_g: float, carbs_g: float, fat_g: float}
     */
    public static function dayMacroTolerance(): array
    {
        $config = config('customer_nutrition.day_macro_tolerance', []);

        return [
            'protein_g' => max(0.0, round((float) ($config['protein_g'] ?? 15.0), 2)),
            'carbs_g' => max(0.0, round((float) ($config['carbs_g'] ?? 20.0), 2)),
            'fat_g' => max(0.0, round((float) ($config['fat_g'] ?? 15.0), 2)),
        ];
    }

    /**
     * Split the remaining day calories after fixed picks.
     *
     * Breakfast stays at the tier-table value (changes only with plan tier).
     * Mains absorb any overshoot/undershoot when actual fixed kcal ≠ the 2×150 budget.
     *
     * @return array{breakfast: float, main_each: float, scalable_budget: float}
     */
    public static function scalableSlotTargetsForFixedTotal(float $planTier, float $fixedPortionTotal): array
    {
        $tierTargets = self::tierSlotCalories($planTier);
        $mainCount = max(1, (int) config('customer_nutrition.scalable_slots.main', 2));

        $breakfast = round((float) $tierTargets['breakfast'], 2);
        $scalableBudget = max(0.0, round($planTier - $fixedPortionTotal, 2));
        $mainsBudget = max(0.0, round($scalableBudget - $breakfast, 2));
        $mainEach = $mainCount > 0 ? round($mainsBudget / $mainCount, 2) : 0.0;

        return [
            'breakfast' => $breakfast,
            'main_each' => $mainEach,
            'scalable_budget' => $scalableBudget,
        ];
    }

    public static function fixedChoiceCount(): int
    {
        return max(0, (int) config('customer_nutrition.fixed_choice_count', 2));
    }

    /**
     * @return list<string>
     */
    public static function fixedChoiceSlots(): array
    {
        /** @var list<string> $slots */
        $slots = config('customer_nutrition.fixed_choice_slots', self::CORE_FIXED_PORTION_SLOTS);

        return array_values($slots);
    }

    /**
     * @return array{breakfast: float, main_each: float}
     */
    public static function tierSlotCalories(float $planTier): array
    {
        $snapped = (int) self::snapToPlanTier($planTier);

        /** @var array<int, array{breakfast?: float, main_each?: float}> $table */
        $table = config('customer_nutrition.tier_slot_calories', []);

        $row = $table[$snapped] ?? null;

        if ($row === null) {
            $fixedTotal = self::fixedChoiceCount() * self::fixedChoiceCaloriesPerSlot();
            $mainCount = max(1, (int) config('customer_nutrition.scalable_slots.main', 2));
            $scalable = max(0.0, $planTier - $fixedTotal);
            $breakfastWeight = 0.20;
            $mainEachWeight = 0.40;

            return [
                'breakfast' => round($scalable * $breakfastWeight, 2),
                'main_each' => round($scalable * $mainEachWeight, 2),
            ];
        }

        return [
            'breakfast' => round((float) ($row['breakfast'] ?? 0.0), 2),
            'main_each' => round((float) ($row['main_each'] ?? 0.0), 2),
        ];
    }

    /**
     * Protein-first macro percentages for scalable main meal slots (all tiers).
     *
     * @return array{protein: float, carb: float, fat: float}
     */
    public static function mainEachMacroPercentages(?CustomerProfile $profile = null): array
    {
        $split = self::slotMacroSplitConfig('main_each', $profile);

        return [
            'protein' => (float) ($split['protein_percentage'] ?? 45.0),
            'carb' => (float) ($split['carb_percentage'] ?? 25.0),
            'fat' => (float) ($split['fat_percentage'] ?? 30.0),
        ];
    }

    /**
     * @return array{protein_g: float, carbs_g: float, fat_g: float}
     */
    public static function mainEachMacroGrams(float $calories, ?CustomerProfile $profile = null): array
    {
        $split = self::mainEachMacroPercentages($profile);

        return self::macroGramsFromCaloriesAndPercentages(
            $calories,
            $split['protein'],
            $split['carb'],
            $split['fat'],
        );
    }

    /**
     * Protein-forward macro percentages for the breakfast slot.
     *
     * @return array{protein: float, carb: float, fat: float}
     */
    public static function breakfastMacroPercentages(?CustomerProfile $profile = null): array
    {
        $split = self::slotMacroSplitConfig('breakfast', $profile);

        return [
            'protein' => (float) ($split['protein_percentage'] ?? 40.0),
            'carb' => (float) ($split['carb_percentage'] ?? 25.0),
            'fat' => (float) ($split['fat_percentage'] ?? 35.0),
        ];
    }

    /**
     * @return array{protein_g: float, carbs_g: float, fat_g: float}
     */
    public static function breakfastMacroGrams(float $calories, ?CustomerProfile $profile = null): array
    {
        $split = self::breakfastMacroPercentages($profile);

        return self::macroGramsFromCaloriesAndPercentages(
            $calories,
            $split['protein'],
            $split['carb'],
            $split['fat'],
        );
    }

    /**
     * @return array{protein_percentage?: float, carb_percentage?: float, fat_percentage?: float}
     */
    private static function slotMacroSplitConfig(string $slot, ?CustomerProfile $profile): array
    {
        if ($profile !== null && DietProtocol::tryFromStored($profile->diet_protocol) === DietProtocol::NutrientDense) {
            /** @var array<string, array<string, float>> $splits */
            $splits = config('customer_nutrition.nutrient_dense_slot_macro_splits', []);
            $dense = $splits[$slot] ?? [];

            if ($dense !== []) {
                return $dense;
            }
        }

        $legacyKey = match ($slot) {
            'breakfast' => 'breakfast_macro_split',
            'main_each' => 'main_each_macro_split',
            default => null,
        };

        if ($legacyKey !== null) {
            /** @var array{protein_percentage?: float, carb_percentage?: float, fat_percentage?: float} $split */
            $split = config("customer_nutrition.{$legacyKey}", []);

            return $split;
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $macros
     * @return array{protein_g: float, carbs_g: float, fat_g: float}
     */
    public static function normalizeMacroGrams(array $macros): array
    {
        return [
            'protein_g' => round(max(0.0, (float) ($macros['protein_g'] ?? 0)), 2),
            'carbs_g' => round(max(0.0, (float) ($macros['carbs_g'] ?? $macros['carb_g'] ?? 0)), 2),
            'fat_g' => round(max(0.0, (float) ($macros['fat_g'] ?? 0)), 2),
        ];
    }

    /**
     * @param  array{protein_g: float, carbs_g: float, fat_g: float}  $left
     * @param  array{protein_g: float, carbs_g: float, fat_g: float}  $right
     * @return array{protein_g: float, carbs_g: float, fat_g: float}
     */
    public static function subtractMacroGrams(array $left, array $right): array
    {
        return [
            'protein_g' => round(max(0.0, $left['protein_g'] - $right['protein_g']), 2),
            'carbs_g' => round(max(0.0, $left['carbs_g'] - $right['carbs_g']), 2),
            'fat_g' => round(max(0.0, $left['fat_g'] - $right['fat_g']), 2),
        ];
    }

    /**
     * @param  array<string, float>  $overrides
     */
    public static function coreFixedPortionCaloriesTotal(array $overrides = []): float
    {
        if ($overrides !== []) {
            return round(array_sum($overrides), 2);
        }

        return round(self::fixedChoiceCount() * self::fixedChoiceCaloriesPerSlot(), 2);
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
     * @deprecated Use {@see coreFixedPortionCaloriesTotal()} — soup is part of pick-2 fixed group.
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
        return self::fixedChoiceCaloriesPerSlot();
    }

    /**
     * @param  list<string>|null  $selectedFixedSlots
     * @return array<string, float>
     */
    public static function resolveFixedSlotCalories(?array $selectedFixedSlots, array $options = []): array
    {
        $perSlotDefault = self::fixedChoiceCaloriesPerSlot();
        $choiceSlots = self::fixedChoiceSlots();

        if ($selectedFixedSlots === null || $selectedFixedSlots === []) {
            $selected = $choiceSlots;
            $count = self::fixedChoiceCount();
        } else {
            $selected = array_values(array_intersect($choiceSlots, $selectedFixedSlots));
            $count = count($selected);
        }

        $out = [];

        foreach ($choiceSlots as $slot) {
            if (! in_array($slot, $selected, true)) {
                continue;
            }

            $optionKey = match ($slot) {
                'side_salad' => 'side_salad_calories',
                'dessert' => 'dessert_calories',
                'soup' => 'soup_calories',
                default => null,
            };

            $out[$slot] = round(
                (float) ($optionKey !== null && isset($options[$optionKey])
                    ? $options[$optionKey]
                    : $perSlotDefault),
                2,
            );
        }

        if ($count < self::fixedChoiceCount() && $selectedFixedSlots === null) {
            return $out;
        }

        if ($count < self::fixedChoiceCount() && ($selectedFixedSlots === null || $selectedFixedSlots === [])) {
            return $out;
        }

        return $out;
    }

    /**
     * @param  array{
     *     include_soup?: bool,
     *     selected_fixed_slots?: list<string>,
     *     fixed_slot_actual_macros?: array{protein_g?: float, carbs_g?: float, fat_g?: float},
     *     soup_calories?: float,
     *     side_salad_calories?: float,
     *     dessert_calories?: float,
     *     snap_to_tier?: bool,
     *     plan_tier?: float,
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

        $selectedFixedSlots = isset($options['selected_fixed_slots'])
            ? array_values(array_intersect(
                self::fixedChoiceSlots(),
                (array) $options['selected_fixed_slots'],
            ))
            : null;

        if ($selectedFixedSlots === []) {
            $selectedFixedSlots = null;
        }

        $includeSoup = in_array('soup', $selectedFixedSlots ?? [], true)
            || (bool) ($options['include_soup'] ?? false);

        $perSlotFixed = self::resolveFixedSlotCalories($selectedFixedSlots, $options);

        $budgetFixedTotal = round(self::fixedChoiceCount() * self::fixedChoiceCaloriesPerSlot(), 2);

        $fixedPortionTotal = $selectedFixedSlots !== null
            ? round(array_sum($perSlotFixed), 2)
            : $budgetFixedTotal;

        $proteinPct = (float) $profile->protein_percentage;
        $carbPct = (float) $profile->carb_percentage;
        $fatPct = (float) $profile->fat_percentage;

        $dailyMacros = self::macroGramsFromCaloriesAndPercentages($planTier, $proteinPct, $carbPct, $fatPct);

        $mainCount = max(1, (int) config('customer_nutrition.scalable_slots.main', 2));

        $scalableTargets = self::scalableSlotTargetsForFixedTotal($planTier, $fixedPortionTotal);
        $breakfastTargetCalories = $scalableTargets['breakfast'];
        $mainTargetCaloriesEach = $scalableTargets['main_each'];
        $scalableBudgetCalories = $scalableTargets['scalable_budget'];

        $fixedPortionMacros = isset($options['fixed_slot_actual_macros']) && is_array($options['fixed_slot_actual_macros'])
            ? self::normalizeMacroGrams($options['fixed_slot_actual_macros'])
            : self::macroGramsFromCaloriesAndPercentages(
                $fixedPortionTotal,
                $proteinPct,
                $carbPct,
                $fatPct,
            );

        $scalableBudgetMacros = self::subtractMacroGrams($dailyMacros, $fixedPortionMacros);

        $coreDayCalories = round(
            $fixedPortionTotal + $breakfastTargetCalories + ($mainTargetCaloriesEach * $mainCount),
            2,
        );
        $dayTotalCalories = round($coreDayCalories, 2);

        $soupCalories = $perSlotFixed['soup'] ?? 0.0;
        $soupMacros = $soupCalories > 0
            ? self::macroGramsFromCaloriesAndPercentages($soupCalories, $proteinPct, $carbPct, $fatPct)
            : self::macroGramsFromCaloriesAndPercentages(0.0, $proteinPct, $carbPct, $fatPct);

        $baseline = self::resolveScalableBaselineCalories();
        $baselineTotal = $baseline['calories'];

        $multiplier = $baselineTotal > 0
            ? $scalableBudgetCalories / $baselineTotal
            : 1.0;

        $multiplier = max(0.0, round($multiplier, 4));

        return [
            'profile_id' => (int) $profile->id,
            'plan_tier' => $planTier,
            'daily_calorie_target' => $rawTarget,
            'protein_percentage' => $proteinPct,
            'carb_percentage' => $carbPct,
            'fat_percentage' => $fatPct,
            'daily_macros' => $dailyMacros,
            'include_soup' => $includeSoup,
            'selected_fixed_slots' => $selectedFixedSlots ?? self::fixedChoiceSlots(),
            'fixed_portion' => [
                'slots' => self::coreFixedPortionSlots(),
                'choice_count' => self::fixedChoiceCount(),
                'calories_per_choice' => self::fixedChoiceCaloriesPerSlot(),
                'calories' => $fixedPortionTotal,
                'budget_calories' => $budgetFixedTotal,
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
            'day_calorie_tolerance' => self::dayCalorieTolerance(),
            'day_macro_tolerance' => self::dayMacroTolerance(),
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
                    'macros' => self::breakfastMacroGrams($breakfastTargetCalories, $profile),
                ],
                'main_each' => [
                    'calories' => $mainTargetCaloriesEach,
                    'macros' => self::mainEachMacroGrams($mainTargetCaloriesEach, $profile),
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
     * @return array{
     *     main_min: float,
     *     main_max: float,
     *     main_target: float,
     *     side_calories: float,
     * }
     */
    public static function businessCraftConfig(): array
    {
        /** @var array{main_min?: float, main_max?: float, main_target?: float, side_calories?: float} $config */
        $config = config('customer_nutrition.business_craft', []);

        return [
            'main_min' => (float) ($config['main_min'] ?? 350.0),
            'main_max' => (float) ($config['main_max'] ?? 400.0),
            'main_target' => (float) ($config['main_target'] ?? 375.0),
            'side_calories' => (float) ($config['side_calories'] ?? 150.0),
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
