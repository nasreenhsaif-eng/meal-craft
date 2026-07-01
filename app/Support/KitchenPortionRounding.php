<?php

namespace App\Support;

use App\Enums\MealScalingRole as MealScalingRoleEnum;
use App\Models\Ingredient;
use App\Models\Meal;

/**
 * Snaps fat-role ingredient grams to kitchen-realistic increments (no 1.3g olive oil).
 */
final class KitchenPortionRounding
{
    private const OIL_SNAP_THRESHOLD = 4.0;

    private const STEP_GRAMS = 5.0;

    public static function snapOilGrams(float $grams): float
    {
        if ($grams < self::OIL_SNAP_THRESHOLD) {
            return 0.0;
        }

        return round($grams / self::STEP_GRAMS) * self::STEP_GRAMS;
    }

    public static function snapNutGrams(float $grams): float
    {
        if ($grams <= 0) {
            return 0.0;
        }

        $snapped = round($grams / self::STEP_GRAMS) * self::STEP_GRAMS;

        return max(self::STEP_GRAMS, $snapped);
    }

    public static function snapCheeseGrams(float $grams): float
    {
        if ($grams <= 0) {
            return 0.0;
        }

        $snapped = round($grams / self::STEP_GRAMS) * self::STEP_GRAMS;

        return max(self::STEP_GRAMS, $snapped);
    }

    public static function isOilIngredient(Ingredient $ingredient): bool
    {
        $name = strtolower(trim($ingredient->name));

        if (str_contains($name, 'olive oil') || str_contains($name, 'avocado oil')) {
            return true;
        }

        return str_contains($name, 'coconut oil') && ! str_contains($name, '(base)');
    }

    public static function isLiquidFatIngredient(Ingredient $ingredient): bool
    {
        if (self::isOilIngredient($ingredient)) {
            return true;
        }

        $name = strtolower(trim($ingredient->name));

        if (str_contains($name, '(base)')) {
            return false;
        }

        foreach (['tahini', 'peanut butter', 'almond butter', 'cashew butter', 'butter'] as $needle) {
            if (str_contains($name, $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function isNutOrSeedIngredient(Ingredient $ingredient): bool
    {
        $name = strtolower(trim($ingredient->name));

        foreach ([
            'walnut',
            'almond',
            'cashew',
            'peanut',
            'pecan',
            'pistachio',
            'sunflower seed',
            'pumpkin seed',
            'sesame seed',
            'pine nut',
            'hazelnut',
            'macadamia',
        ] as $needle) {
            if (str_contains($name, $needle) && ! str_contains($name, 'butter')) {
                return true;
            }
        }

        return false;
    }

    public static function isCheeseIngredient(Ingredient $ingredient): bool
    {
        $name = strtolower(trim($ingredient->name));

        if (str_contains($name, '(base)')) {
            return false;
        }

        return (bool) preg_match(
            '/\b(cheese|parmesan|feta|halloumi|brie|cheddar|mozzarella|ricotta|paneer|yogurt)\b/',
            $name,
        );
    }

    public static function snapGramsForIngredient(Ingredient $ingredient, float $grams): float
    {
        if ($grams <= 0) {
            return 0.0;
        }

        if (self::isOilIngredient($ingredient)) {
            return self::snapOilGrams($grams);
        }

        if (self::isNutOrSeedIngredient($ingredient)) {
            return self::snapNutGrams($grams);
        }

        if (self::isCheeseIngredient($ingredient)) {
            return self::snapCheeseGrams($grams);
        }

        if (self::isLiquidFatIngredient($ingredient)) {
            return self::snapOilGrams($grams);
        }

        return round($grams, 4);
    }

    /**
     * @param  array<int, float>  $grams
     * @return array<int, float>
     */
    public static function snapFatRoleGramsForMeal(Meal $meal, array $grams): array
    {
        $meal->loadMissing('ingredients');
        $adjusted = $grams;

        foreach ($meal->ingredients as $ingredient) {
            $role = MealScalingRole::roleForIngredient($ingredient, $meal);

            if (! in_array($role, [MealScalingRoleEnum::Fat, MealScalingRoleEnum::Sauce], true)) {
                continue;
            }

            if (! isset($adjusted[$ingredient->id])) {
                continue;
            }

            $baseline = (float) $adjusted[$ingredient->id];

            if ($baseline <= 0) {
                continue;
            }

            if (
                $role === MealScalingRoleEnum::Sauce
                && str_contains(strtolower($ingredient->name), '(base)')
            ) {
                continue;
            }

            $adjusted[$ingredient->id] = self::snapGramsForIngredient($ingredient, $baseline);
        }

        return $adjusted;
    }
}
