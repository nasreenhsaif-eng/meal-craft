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
        return $ingredient->name === 'Egg';
    }

    public static function formatLine(float $grams, string $formattedGrams): string
    {
        if ($grams <= 0) {
            return __('Egg');
        }

        $rawCount = $grams / self::LARGE_EGG_GRAMS;

        if ($rawCount >= 0.75) {
            $count = (int) round($rawCount);
            $label = $count === 1
                ? __('1 large egg')
                : __(':count large eggs', ['count' => $count]);

            return sprintf('%s (%sg)', $label, $formattedGrams);
        }

        if ($rawCount >= 0.35) {
            return sprintf('%s (%sg)', __('1/2 large egg'), $formattedGrams);
        }

        return sprintf('%sg %s', $formattedGrams, __('Egg'));
    }
}
