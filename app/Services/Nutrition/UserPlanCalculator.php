<?php

namespace App\Services\Nutrition;

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\CustomerProfile;
use App\Models\Meal;
use App\Support\MealPlanSlotBasedDayNutrition;

/**
 * Core scaling engine: derives a single meal-level multiplier from the customer's
 * daily targets minus fixed Soup / Side Salad / Dessert (450 kcal total).
 *
 * Breakfast and both Main meals share one multiplier so every ingredient in those
 * meals scales uniformly (macros, micros, oils, salt, spices).
 */
final class UserPlanCalculator
{
    public const FIXED_SLOTS = ['soup', 'side_salad', 'dessert'];

    public static function fixedCaloriesTotal(): float
    {
        $perMeal = (float) config('customer_nutrition.fixed_meal_calories', 150.0);
        $slots = config('customer_nutrition.fixed_meal_slots', self::FIXED_SLOTS);

        return $perMeal * count($slots);
    }

    public static function fixedCaloriesPerMeal(): float
    {
        return (float) config('customer_nutrition.fixed_meal_calories', 150.0);
    }

    /**
     * @return array{
     *     profile_id: int,
     *     daily_calorie_target: float,
     *     daily_macros: array{protein_g: float, carbs_g: float, fat_g: float},
     *     fixed: array{
     *         calories: float,
     *         per_meal_calories: float,
     *         slots: list<string>,
     *         macros: array{protein_g: float, carbs_g: float, fat_g: float}
     *     },
     *     remaining: array{
     *         calories: float,
     *         macros: array{protein_g: float, carbs_g: float, fat_g: float}
     *     },
     *     baseline_scalable: array{
     *         calories: float,
     *         breakfast_calories: float,
     *         main_calories: float,
     *         main_meal_count: int
     *     },
     *     scaling_multiplier: float,
     *     scalable_slot_targets: array{
     *         breakfast: array{calories: float, macros: array{protein_g: float, carbs_g: float, fat_g: float}},
     *         main_each: array{calories: float, macros: array{protein_g: float, carbs_g: float, fat_g: float}}
     *     }
     * }
     */
    public static function calculateUserPlan(CustomerProfile $profile): array
    {
        $dailyCalories = (float) $profile->daily_calorie_target;
        $dailyMacros = self::macroGramsFromCaloriesAndPercentages(
            $dailyCalories,
            (float) $profile->protein_percentage,
            (float) $profile->carb_percentage,
            (float) $profile->fat_percentage,
        );

        $fixedCalories = self::fixedCaloriesTotal();
        $fixedMacros = self::macroGramsFromCaloriesAndPercentages(
            $fixedCalories,
            (float) $profile->protein_percentage,
            (float) $profile->carb_percentage,
            (float) $profile->fat_percentage,
        );

        $remainingCalories = max(0.0, $dailyCalories - $fixedCalories);
        $remainingMacros = [
            'protein_g' => max(0.0, $dailyMacros['protein_g'] - $fixedMacros['protein_g']),
            'carbs_g' => max(0.0, $dailyMacros['carbs_g'] - $fixedMacros['carbs_g']),
            'fat_g' => max(0.0, $dailyMacros['fat_g'] - $fixedMacros['fat_g']),
        ];

        $baseline = self::resolveScalableBaselineCalories();
        $baselineTotal = $baseline['calories'];

        $multiplier = $baselineTotal > 0
            ? $remainingCalories / $baselineTotal
            : 1.0;

        $multiplier = max(0.0, round($multiplier, 4));

        $mainCount = (int) config('customer_nutrition.scalable_slots.main', 2);
        $breakfastShare = $baselineTotal > 0
            ? $baseline['breakfast_calories'] / $baselineTotal
            : 1 / 3;
        $mainShareEach = $baselineTotal > 0
            ? ($baseline['main_calories'] / $baselineTotal) / max(1, $mainCount)
            : (2 / 3) / max(1, $mainCount);

        $breakfastTargetCalories = round($remainingCalories * $breakfastShare, 2);
        $mainTargetCaloriesEach = round($remainingCalories * $mainShareEach, 2);

        return [
            'profile_id' => (int) $profile->id,
            'daily_calorie_target' => $dailyCalories,
            'daily_macros' => $dailyMacros,
            'fixed' => [
                'calories' => $fixedCalories,
                'per_meal_calories' => self::fixedCaloriesPerMeal(),
                'slots' => array_values(config('customer_nutrition.fixed_meal_slots', self::FIXED_SLOTS)),
                'macros' => $fixedMacros,
            ],
            'remaining' => [
                'calories' => round($remainingCalories, 2),
                'macros' => $remainingMacros,
            ],
            'baseline_scalable' => $baseline,
            'scaling_multiplier' => $multiplier,
            'scalable_slot_targets' => [
                'breakfast' => [
                    'calories' => $breakfastTargetCalories,
                    'macros' => self::macroGramsFromCaloriesAndPercentages(
                        $breakfastTargetCalories,
                        (float) $profile->protein_percentage,
                        (float) $profile->carb_percentage,
                        (float) $profile->fat_percentage,
                    ),
                ],
                'main_each' => [
                    'calories' => $mainTargetCaloriesEach,
                    'macros' => self::macroGramsFromCaloriesAndPercentages(
                        $mainTargetCaloriesEach,
                        (float) $profile->protein_percentage,
                        (float) $profile->carb_percentage,
                        (float) $profile->fat_percentage,
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
            : (float) ($configBaselines['breakfast'] ?? MealPlanSlotBasedDayNutrition::placeholderCaloriesForSlot('breakfast'));

        $mainCalories = $mainAvg > 0
            ? $mainAvg
            : (float) ($configBaselines['main'] ?? MealPlanSlotBasedDayNutrition::placeholderCaloriesForSlot('main'));

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
