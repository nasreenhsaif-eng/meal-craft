<?php

namespace App\Support;

use App\Enums\MealPlanSlotType;
use App\Models\Meal;

/**
 * Slot-based daily nutrition: 1,200 kcal “core” budget plus optional soup (+150 target when empty).
 */
final class MealPlanSlotBasedDayNutrition
{
    public const CORE_CALORIE_TARGET = 1200.0;

    public const SOUP_OPTIONAL_TARGET = 150.0;

    /** Warn when admin “high core path” exceeds this (core only, soup excluded). */
    public const CORE_HIGH_PATH_WARNING_KCAL = 1250.0;

    /**
     * Empty-slot calorie targets by slot type (core model + soup).
     */
    public static function placeholderCaloriesForSlot(string $slotTypeValue): float
    {
        return match ($slotTypeValue) {
            MealPlanSlotType::Breakfast->value => 200.0,
            MealPlanSlotType::Main->value => 350.0,
            MealPlanSlotType::Salad->value => 150.0,
            MealPlanSlotType::Dessert->value => 150.0,
            MealPlanSlotType::Soup->value => 150.0,
            default => 0.0,
        };
    }

    /**
     * @return list<string>
     */
    public static function nutritionKeys(): array
    {
        return [
            'calories', 'protein', 'carbs', 'fat',
            'b6', 'b9_folate', 'b12', 'iron', 'magnesium', 'fiber', 'sugar',
            'calcium', 'potassium', 'sodium', 'zinc',
            'vitamin_c', 'vitamin_a', 'vitamin_e', 'vitamin_d', 'vitamin_k',
        ];
    }

    /**
     * Design-time placeholder when a slot has no meal (macros derived from target calories).
     *
     * @return array<string, float>
     */
    public static function placeholderNutrition(float $targetCalories): array
    {
        $cal = max(0.0, $targetCalories);
        $pro = $cal > 0 ? round($cal * 0.25 / 4, 2) : 0.0;
        $carb = $cal > 0 ? round($cal * 0.45 / 4, 2) : 0.0;
        $fat = $cal > 0 ? round($cal * 0.30 / 9, 2) : 0.0;

        $row = [
            'calories' => $cal,
            'protein' => $pro,
            'carbs' => $carb,
            'fat' => $fat,
        ];

        foreach (self::nutritionKeys() as $key) {
            if (! isset($row[$key])) {
                $row[$key] = 0.0;
            }
        }

        return $row;
    }

    /**
     * @return array<string, float>
     */
    public static function nutritionForMealOrPlaceholderForSlot(?Meal $meal, string $slotTypeValue): array
    {
        $ph = self::placeholderCaloriesForSlot($slotTypeValue);

        if ($meal === null) {
            return self::placeholderNutrition($ph);
        }

        $n = $meal->nutritionForDisplay();
        $out = [];
        foreach (self::nutritionKeys() as $key) {
            $out[$key] = (float) ($n[$key] ?? 0);
        }

        return $out;
    }

    /**
     * @param  callable(string, int): (?Meal)  $resolveMeal  Slot type value (e.g. "breakfast"), 1-based index
     * @param  list<int>  $indices
     * @return array<string, float>
     */
    public static function averageGroup(callable $resolveMeal, string $slotTypeValue, array $indices): array
    {
        if ($indices === []) {
            return self::zeroShape();
        }

        $sum = self::zeroShape();
        foreach ($indices as $index) {
            $meal = $resolveMeal($slotTypeValue, $index);
            $vec = self::nutritionForMealOrPlaceholderForSlot($meal, $slotTypeValue);
            foreach (self::nutritionKeys() as $k) {
                $sum[$k] += $vec[$k];
            }
        }

        $count = count($indices);
        foreach (self::nutritionKeys() as $k) {
            $sum[$k] = $sum[$k] / (float) $count;
        }

        return $sum;
    }

    /**
     * Core budget: avg(breakfast) + 2×avg(mains) + avg(salads) + avg(desserts). Soup excluded.
     *
     * @param  callable(string, int): (?Meal)  $resolveMeal
     * @return array<string, float>
     */
    public static function coreBudgetNutrition(callable $resolveMeal): array
    {
        $bf = self::averageGroup($resolveMeal, MealPlanSlotType::Breakfast->value, [1, 2]);
        $mainAvg = self::averageGroup($resolveMeal, MealPlanSlotType::Main->value, [1, 2, 3, 4]);
        $mainScaled = self::scaleShape($mainAvg, 2.0);
        $salad = self::averageGroup($resolveMeal, MealPlanSlotType::Salad->value, [1, 2]);
        $dessert = self::averageGroup($resolveMeal, MealPlanSlotType::Dessert->value, [1, 2]);

        return self::sumShapes([$bf, $mainScaled, $salad, $dessert]);
    }

    /**
     * Optional soup slot only (not part of the 1,200 kcal core).
     *
     * @param  callable(string, int): (?Meal)  $resolveMeal
     * @return array<string, float>
     */
    public static function soupSlotNutrition(callable $resolveMeal): array
    {
        $meal = $resolveMeal(MealPlanSlotType::Soup->value, 1);

        return self::nutritionForMealOrPlaceholderForSlot($meal, MealPlanSlotType::Soup->value);
    }

    /**
     * Full day macros: core budget + soup.
     *
     * @param  callable(string, int): (?Meal)  $resolveMeal
     * @return array<string, float>
     */
    public static function fullDayNutrition(callable $resolveMeal): array
    {
        return self::sumShapes([
            self::coreBudgetNutrition($resolveMeal),
            self::soupSlotNutrition($resolveMeal),
        ]);
    }

    /**
     * @deprecated Use {@see fullDayNutrition()}
     *
     * @param  callable(string, int): (?Meal)  $resolveMeal
     * @return array<string, float>
     */
    public static function slotBasedDayTotals(callable $resolveMeal, float $placeholderCalories): array
    {
        unset($placeholderCalories);

        return self::fullDayNutrition($resolveMeal);
    }

    /**
     * Admin validation — core only (soup excluded).
     * High path: highest breakfast + sum of two highest mains + highest salad + highest dessert.
     * Low path: lowest breakfast + sum of two lowest mains + lowest salad + lowest dessert.
     *
     * @param  callable(string, int): (?Meal)  $resolveMeal
     * @return array{min: float, max: float}
     */
    public static function adminCorePathCalorieRange(callable $resolveMeal): array
    {
        $cal = static function (string $slotTypeValue, int $index) use ($resolveMeal): float {
            $meal = $resolveMeal($slotTypeValue, $index);

            return self::nutritionForMealOrPlaceholderForSlot($meal, $slotTypeValue)['calories'];
        };

        $bf = [
            $cal(MealPlanSlotType::Breakfast->value, 1),
            $cal(MealPlanSlotType::Breakfast->value, 2),
        ];

        $mains = [];
        foreach ([1, 2, 3, 4] as $i) {
            $mains[] = $cal(MealPlanSlotType::Main->value, $i);
        }

        $salads = [
            $cal(MealPlanSlotType::Salad->value, 1),
            $cal(MealPlanSlotType::Salad->value, 2),
        ];

        $desserts = [
            $cal(MealPlanSlotType::Dessert->value, 1),
            $cal(MealPlanSlotType::Dessert->value, 2),
        ];

        rsort($mains, SORT_NUMERIC);
        $highMains = $mains[0] + $mains[1];
        sort($mains, SORT_NUMERIC);
        $lowMains = $mains[0] + $mains[1];

        $highCore = max($bf) + $highMains + max($salads) + max($desserts);
        $lowCore = min($bf) + $lowMains + min($salads) + min($desserts);

        return [
            'min' => round($lowCore, 2),
            'max' => round($highCore, 2),
        ];
    }

    /**
     * @deprecated Use {@see adminCorePathCalorieRange()}
     *
     * @param  callable(string, int): (?Meal)  $resolveMeal
     * @return array{min: float, max: float}
     */
    public static function menuPathCalorieRange(callable $resolveMeal, float $placeholderCalories): array
    {
        unset($placeholderCalories);

        return self::adminCorePathCalorieRange($resolveMeal);
    }

    /**
     * @param  list<array<string, float>>  $shapes
     * @return array<string, float>
     */
    public static function sumShapes(array $shapes): array
    {
        $out = self::zeroShape();
        foreach ($shapes as $shape) {
            foreach (self::nutritionKeys() as $k) {
                $out[$k] += (float) ($shape[$k] ?? 0);
            }
        }

        return $out;
    }

    /**
     * @return array<string, float>
     */
    private static function scaleShape(array $shape, float $factor): array
    {
        $out = [];
        foreach (self::nutritionKeys() as $k) {
            $out[$k] = (float) ($shape[$k] ?? 0) * $factor;
        }

        return $out;
    }

    /**
     * @return array<string, float>
     */
    private static function zeroShape(): array
    {
        $out = [];
        foreach (self::nutritionKeys() as $k) {
            $out[$k] = 0.0;
        }

        return $out;
    }
}
