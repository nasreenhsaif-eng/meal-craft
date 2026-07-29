<?php

namespace App\Support;

use App\Models\Ingredient;

/**
 * Customer-facing amount labels for raw/dry prep weights and pre-cooked bases.
 */
final class RawPrepIngredientPresentation
{
    public static function isRawPrepIngredient(Ingredient $ingredient): bool
    {
        $label = IngredientCookingYield::amountStateLabel($ingredient);

        return $label === __('raw, before cooking');
    }

    public static function isDryWeightIngredient(Ingredient $ingredient): bool
    {
        return IngredientCookingYield::amountStateLabel($ingredient) === __('dry weight');
    }

    public static function isPreCookedBaseIngredient(Ingredient $ingredient): bool
    {
        return IngredientCookingYield::isFinishedBaseComponent($ingredient);
    }

    public static function formatLine(float $grams, string $formattedGrams, Ingredient $ingredient): string
    {
        $displayName = self::displayName($ingredient->name);
        $suffix = IngredientCookingYield::amountStateLabel($ingredient) ?? __('raw, before cooking');

        if ($grams <= 0) {
            return $displayName.' ('.$suffix.')';
        }

        return sprintf('%sg %s (%s)', $formattedGrams, $displayName, $suffix);
    }

    public static function formatDryLine(float $grams, string $formattedGrams, Ingredient $ingredient): string
    {
        return self::formatLine($grams, $formattedGrams, $ingredient);
    }

    public static function formatBaseLine(float $grams, string $formattedGrams, Ingredient $ingredient): string
    {
        $suffix = __('pre-cooked base');

        if ($grams <= 0) {
            return $ingredient->name.' ('.$suffix.')';
        }

        return sprintf('%sg %s (%s)', $formattedGrams, $ingredient->name, $suffix);
    }

    private static function displayName(string $ingredientName): string
    {
        if ($ingredientName === 'Salmon (Raw)') {
            return 'Salmon';
        }

        return $ingredientName;
    }
}
