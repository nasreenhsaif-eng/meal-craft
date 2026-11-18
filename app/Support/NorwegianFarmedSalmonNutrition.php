<?php

namespace App\Support;

/**
 * Kitchen salmon is Norwegian farmed Atlantic (Salmo salar), not USDA wild Atlantic.
 *
 * Vitamin D is the Matvaretabellen analytical value for farmed salmon, raw
 * (https://www.matvaretabellen.no/en/salmon-farmed-raw/). Library amounts are
 * raw prep weight ({@see IngredientCookingYield}), so the raw figure is used
 * for both catalog names.
 */
final class NorwegianFarmedSalmonNutrition
{
    public const COOKED_LABEL_NAME = 'Salmon';

    public const RAW_LABEL_NAME = 'Salmon (Raw)';

    /** @var list<string> */
    public const INGREDIENT_NAMES = [
        self::COOKED_LABEL_NAME,
        self::RAW_LABEL_NAME,
    ];

    public const VITAMIN_D_MCG_PER_100G = 7.0;

    public const DESCRIPTION = 'Norwegian farmed salmon (Salmo salar). Vitamin D from Matvaretabellen: 7 mcg/100g raw.';

    public static function vitaminDMcgPer100g(string $name): ?float
    {
        $name = trim($name);

        if (! in_array($name, self::INGREDIENT_NAMES, true)) {
            return null;
        }

        return self::VITAMIN_D_MCG_PER_100G;
    }
}
