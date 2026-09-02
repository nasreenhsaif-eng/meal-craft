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
        if (EggIngredientPresentation::isEggFamilyIngredient($ingredient)) {
            return false;
        }

        $label = IngredientCookingYield::amountStateLabel($ingredient);

        return $label === __('raw, before cooking');
    }

    public static function isDryWeightIngredient(Ingredient $ingredient): bool
    {
        return IngredientCookingYield::amountStateLabel($ingredient) === __('dry weight');
    }

    public static function isCannedPrepIngredient(Ingredient $ingredient): bool
    {
        return IngredientCookingYield::isCannedIngredient($ingredient);
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

    public static function formatCannedLine(float $grams, string $formattedGrams, Ingredient $ingredient): string
    {
        $displayName = self::displayName($ingredient->name);
        $suffix = __('drained canned');

        if ($grams <= 0) {
            return $displayName.' ('.$suffix.')';
        }

        return sprintf('%sg %s (%s)', $formattedGrams, $displayName, $suffix);
    }

    public static function formatBaseLine(float $grams, string $formattedGrams, Ingredient $ingredient): string
    {
        if (QuinoaFlatbreadBaseRecipe::is($ingredient)) {
            $breads = max(1, (int) round($grams / QuinoaFlatbreadBaseRecipe::GRAMS_PER_BREAD));

            return $breads === 1
                ? __('1 Quinoa Flatbread (1 bread)')
                : __(':count Quinoa Flatbread (:count breads)', ['count' => $breads]);
        }

        $displayName = self::displayName($ingredient->name);
        $suffix = IngredientCookingYield::amountStateLabel($ingredient) ?? __('cooked plated portion');

        if ($grams <= 0) {
            return $displayName.' ('.$suffix.')';
        }

        return sprintf('%sg %s (%s)', $formattedGrams, $displayName, $suffix);
    }

    /**
     * Legend shown above every recipe ingredient list.
     */
    public static function ingredientsPrepNote(): string
    {
        return __('Listed amounts are prep weights: meats and fish are raw before cooking; canned fish is drained weight; dry grains and legumes are dry weight; (Base) items are cooked plated portions.');
    }

    private static function displayName(string $ingredientName): string
    {
        if ($ingredientName === 'Salmon (Raw)') {
            return 'Salmon';
        }

        if (in_array($ingredientName, ['Hamour Fillet', 'Hamour (Fish)'], true)) {
            return 'Hamour';
        }

        if (in_array($ingredientName, ['Shrimp (Raw)', 'Prawns'], true)) {
            return 'Shrimp';
        }

        if ($ingredientName === 'Sardines (Canned)') {
            return 'Sardines';
        }

        if ($ingredientName === 'Tuna (Canned)') {
            return 'Tuna';
        }

        if (str_ends_with($ingredientName, ' (Base)')) {
            return substr($ingredientName, 0, -7);
        }

        return $ingredientName;
    }
}
