<?php

namespace App\Support;

use App\Models\Ingredient;

/**
 * Customer-facing egg amounts: grams stay authoritative; count uses one large egg = 50g.
 */
final class EggIngredientPresentation
{
    public const LARGE_EGG_GRAMS = 50.0;

    public static function isEggIngredient(Ingredient $ingredient): bool
    {
        return self::isWholeEggIngredient($ingredient);
    }

    public static function isWholeEggIngredient(Ingredient $ingredient): bool
    {
        $name = trim($ingredient->name);

        return $name === 'Egg' || $name === 'Eggs (Large)';
    }

    public static function isEggWhiteIngredient(Ingredient $ingredient): bool
    {
        $name = trim($ingredient->name);

        return $name === 'Egg White' || $name === 'Egg Whites';
    }

    public static function isEggFamilyIngredient(Ingredient $ingredient): bool
    {
        return self::isWholeEggIngredient($ingredient) || self::isEggWhiteIngredient($ingredient);
    }

    public static function formatLine(float $grams, string $formattedGrams): string
    {
        if ($grams <= 0) {
            return __('Egg').' '.__('raw');
        }

        $rawCount = $grams / self::LARGE_EGG_GRAMS;

        if ($rawCount >= 0.75) {
            $count = (int) round($rawCount);

            return $count === 1
                ? __('1 egg raw')
                : __(':count eggs raw', ['count' => $count]);
        }

        if ($rawCount >= 0.35) {
            return __('1/2 egg raw');
        }

        if ($rawCount >= 0.15) {
            return __('1/4 egg raw');
        }

        return sprintf('%sg %s %s', $formattedGrams, __('Egg'), __('raw'));
    }
}
