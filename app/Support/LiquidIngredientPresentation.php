<?php

namespace App\Support;

use App\Enums\RecipeAmountUnit;
use App\Models\Ingredient;
use App\Services\RecipeIngredientUnitConverter;

/**
 * Customer-facing volume amounts for pourable / liquid library ingredients.
 */
final class LiquidIngredientPresentation
{
    public const MLS_PER_TABLESPOON = 15.0;

    /** @var list<string> */
    private const LIQUID_CATEGORIES = [
        'Liquids',
        'Beverages',
        'Beverage',
        'Soups & Broths',
    ];

    /** @var list<string> */
    private const NON_LIQUID_NAMES = [
        'Coconut Meat',
        'Peanut Butter',
        'Almond Butter',
        'Cashew Butter',
        'Tahini',
        'Hummus',
    ];

    public static function isLiquidIngredient(Ingredient $ingredient): bool
    {
        if (in_array($ingredient->name, self::NON_LIQUID_NAMES, true)) {
            return false;
        }

        $category = trim((string) ($ingredient->usda_food_category ?? ''));

        if ($category !== '' && in_array($category, self::LIQUID_CATEGORIES, true)) {
            return true;
        }

        $name = $ingredient->name;

        return (bool) preg_match(
            '/\b(Oil|Juice|Vinegar|Broth|Stock|Water|Milk|Sauce|Dressing|Syrup|Cream)\b/i',
            $name,
        );
    }

    /**
     * Cooking oils (olive, avocado, sesame, …) — kitchen prefers tablespoons over grams/ml.
     */
    public static function isCookingOilIngredient(Ingredient $ingredient): bool
    {
        return KitchenPortionRounding::isOilIngredient($ingredient);
    }

    public static function millilitersFromGrams(float $grams, Ingredient $ingredient): float
    {
        if ($grams <= 0) {
            return 0.0;
        }

        if (PureCookingFatNutrition::isPureCookingFat($ingredient)) {
            return PureCookingFatNutrition::millilitersFromGrams($ingredient, $grams);
        }

        $density = (float) ($ingredient->density ?? 0);

        if ($density <= 0) {
            $density = preg_match('/\bOil\b/i', $ingredient->name) ? PureCookingFatNutrition::OIL_DENSITY_G_PER_ML : 1.0;
        }

        return $grams / $density;
    }

    public static function tablespoonsFromGrams(float $grams, Ingredient $ingredient): float
    {
        return self::millilitersFromGrams($grams, $ingredient) / self::MLS_PER_TABLESPOON;
    }

    public static function millilitersFromAmountAndUnit(float $amount, string $unit, Ingredient $ingredient): float
    {
        if ($amount <= 0) {
            return 0.0;
        }

        $enum = RecipeAmountUnit::tryFrom(strtolower(trim($unit)));

        if ($enum === RecipeAmountUnit::Milliliters) {
            return $amount;
        }

        if ($enum === RecipeAmountUnit::Liters) {
            return $amount * 1000.0;
        }

        if ($enum !== null && $enum->usesDensity()) {
            $grams = RecipeIngredientUnitConverter::toGrams(
                $amount,
                $enum,
                PureCookingFatNutrition::densityGramsPerMl($ingredient),
            );

            return self::millilitersFromGrams($grams, $ingredient);
        }

        return self::millilitersFromGrams($amount, $ingredient);
    }

    public static function formatLine(float $grams, Ingredient $ingredient): string
    {
        if ($grams <= 0) {
            return $ingredient->name;
        }

        return self::formatKitchenQuantity($grams, $ingredient).' '.$ingredient->name;
    }

    public static function formatLineFromAmountAndUnit(float $amount, string $unit, Ingredient $ingredient): string
    {
        if ($amount <= 0) {
            return $ingredient->name;
        }

        $ml = self::millilitersFromAmountAndUnit($amount, $unit, $ingredient);

        if (self::isCookingOilIngredient($ingredient)) {
            $tbsp = self::snapKitchenTablespoons($ml / self::MLS_PER_TABLESPOON);

            return self::formatTablespoonLabel($tbsp).' '.$ingredient->name;
        }

        $ml = self::snapKitchenMilliliters($ml);

        return self::formatTrimmedDecimal($ml, 2).'ml '.$ingredient->name;
    }

    /**
     * Quantity only (no ingredient name): "1 tbsp", "5ml", or "12 g".
     */
    public static function formatKitchenQuantity(float $grams, Ingredient $ingredient): string
    {
        if ($grams <= 0) {
            return '0';
        }

        if (self::isCookingOilIngredient($ingredient)) {
            return self::formatTablespoonLabel(
                self::snapKitchenTablespoons(self::tablespoonsFromGrams($grams, $ingredient)),
            );
        }

        if (self::isLiquidIngredient($ingredient)) {
            $ml = self::snapKitchenMilliliters(self::millilitersFromGrams($grams, $ingredient));

            return self::formatTrimmedDecimal($ml, 2).'ml';
        }

        $formatted = number_format($grams, 1, '.', '');

        return (rtrim(rtrim($formatted, '0'), '.') ?: '0').' g';
    }

    /**
     * Round displayed tablespoons to half-spoon kitchen steps (minimum ½ tbsp when present).
     */
    public static function snapKitchenTablespoons(float $tablespoons): float
    {
        if ($tablespoons <= 0) {
            return 0.0;
        }

        $snapped = round($tablespoons * 2) / 2;

        return max(0.5, $snapped);
    }

    public static function formatTablespoonLabel(float $tablespoons): string
    {
        if ($tablespoons <= 0) {
            return '0 tbsp';
        }

        $whole = (int) floor($tablespoons + 1e-9);
        $fraction = round($tablespoons - $whole, 2);

        if ($fraction < 0.01) {
            return $whole === 1 ? '1 tbsp' : $whole.' tbsp';
        }

        if (abs($fraction - 0.5) < 0.01) {
            return $whole === 0 ? '½ tbsp' : $whole.'½ tbsp';
        }

        return self::formatTrimmedDecimal($tablespoons, 1).' tbsp';
    }

    /**
     * Round displayed milliliters to spoon-friendly steps (5 ml / 10 ml).
     */
    public static function snapKitchenMilliliters(float $milliliters): float
    {
        if ($milliliters <= 0) {
            return 0.0;
        }

        if ($milliliters < 4.0) {
            return max(1.0, round($milliliters));
        }

        $snapped = round($milliliters / 5.0) * 5.0;

        return $snapped > 0 ? $snapped : 5.0;
    }

    public static function formatTrimmedDecimal(float $value, int $decimals): string
    {
        if (! is_finite($value)) {
            return '0';
        }

        $formatted = number_format($value, $decimals, '.', '');

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }
}
