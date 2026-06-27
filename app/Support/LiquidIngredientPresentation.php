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

    public static function millilitersFromGrams(float $grams, Ingredient $ingredient): float
    {
        if ($grams <= 0) {
            return 0.0;
        }

        $density = (float) ($ingredient->density ?? 0);

        if ($density <= 0) {
            $density = preg_match('/\bOil\b/i', $ingredient->name) ? 0.92 : 1.0;
        }

        return $grams / $density;
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
                (float) ($ingredient->density ?? 0) > 0 ? (float) $ingredient->density : 1.0,
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

        $ml = self::millilitersFromGrams($grams, $ingredient);

        return self::formatTrimmedDecimal($ml, 2).'ml '.$ingredient->name;
    }

    public static function formatLineFromAmountAndUnit(float $amount, string $unit, Ingredient $ingredient): string
    {
        if ($amount <= 0) {
            return $ingredient->name;
        }

        $ml = self::millilitersFromAmountAndUnit($amount, $unit, $ingredient);

        return self::formatTrimmedDecimal($ml, 2).'ml '.$ingredient->name;
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
