<?php

namespace App\Support;

use App\Models\Ingredient;

/**
 * Customer-facing amounts for proteins weighed before cooking (USDA raw nutrition basis).
 */
final class RawPrepIngredientPresentation
{
    /** @var list<string> */
    private const RAW_PREP_INGREDIENT_NAMES = [
        'Salmon',
        'Salmon (Raw)',
        ChickenBreastYield::RAW_INGREDIENT_NAME,
    ];

    public static function isRawPrepIngredient(Ingredient $ingredient): bool
    {
        return in_array($ingredient->name, self::RAW_PREP_INGREDIENT_NAMES, true);
    }

    public static function formatLine(float $grams, string $formattedGrams, Ingredient $ingredient): string
    {
        $displayName = self::displayName($ingredient->name);
        $suffix = __('raw, before cooking');

        if ($grams <= 0) {
            return $displayName.' ('.$suffix.')';
        }

        return sprintf('%sg %s (%s)', $formattedGrams, $displayName, $suffix);
    }

    private static function displayName(string $ingredientName): string
    {
        if ($ingredientName === 'Salmon (Raw)') {
            return 'Salmon';
        }

        return $ingredientName;
    }
}
