<?php

namespace App\Support;

use App\Models\Ingredient;
use InvalidArgumentException;

/**
 * 1 kg raw-chicken batch for Rosemary Garlic Chicken (Base).
 *
 * Marinade oil is 30 g (~2 tbsp) per kg chicken so a plated serving of this
 * base can hit 40 / 40 / 20 without extra raw chicken. Meal amounts are cooked
 * portion grams; 155 g raw chicken is {@see self::cookedGramsForRawChicken()}.
 */
final class RosemaryGarlicChickenBaseRecipe
{
    public const NAME = 'Rosemary Garlic Chicken (Base)';

    public const RAW_CHICKEN_GRAMS = 1000.0;

    /**
     * Cooked yield after dropping 37 g of marinade oil from the previous 775 g batch.
     * Water loss is unchanged; oil stays in the cooked chicken.
     */
    public const FINISHED_WEIGHT_GRAMS = 738.0;

    /**
     * @var array<string, float>
     */
    public const COMPONENTS = [
        'Chicken Breast' => 1000.0,
        'Olive Oil (Extra Virgin)' => 30.0,
        'Lemon Juice' => 67.0,
        'Dijon Mustard' => 56.0,
        'Garlic (Raw)' => 44.0,
        'Rosemary (Fresh)' => 28.0,
        'Sea Salt' => 11.0,
    ];

    public const DESCRIPTION = 'Premium grilled chicken breast marinated with garlic, rosemary, Dijon mustard, and lemon.';

    public const INSTRUCTIONS = 'Step 1: Whisk the olive oil, lemon juice, Dijon mustard, minced garlic, chopped rosemary, and sea salt together in a bowl to form the marinade.
Step 2: Coat the chicken breasts evenly and marinate for at least 30 minutes, or up to 12 hours refrigerated.
Step 3: Sear on a hot grill or heavy skillet for 5–6 minutes per side until charred and the internal temperature hits 74°C.
Step 4: Rest for 5 minutes before slicing.';

    public static function cookedGramsForRawChicken(float $rawChickenGrams): float
    {
        return round($rawChickenGrams / self::RAW_CHICKEN_GRAMS * self::FINISHED_WEIGHT_GRAMS, 1);
    }

    public static function recipeComponentsCsvCell(): string
    {
        $segments = [];

        foreach (self::COMPONENTS as $name => $grams) {
            $segments[] = $name.' ('.self::formatGrams($grams).'g)';
        }

        sort($segments, SORT_NATURAL | SORT_FLAG_CASE);

        return implode(' | ', $segments);
    }

    /**
     * @return list<array{ingredient_id: int, amount_grams: float}>
     */
    public static function componentRows(): array
    {
        $rows = [];

        foreach (self::COMPONENTS as $name => $grams) {
            $ingredient = Ingredient::query()
                ->where('name', $name)
                ->orderByDesc('is_verified')
                ->orderBy('id')
                ->first();

            if ($ingredient === null) {
                throw new InvalidArgumentException("Missing library ingredient: {$name}");
            }

            $rows[] = [
                'ingredient_id' => (int) $ingredient->id,
                'amount_grams' => $grams,
            ];
        }

        return $rows;
    }

    private static function formatGrams(float $grams): string
    {
        $formatted = rtrim(rtrim(number_format($grams, 1, '.', ''), '0'), '.');

        return $formatted !== '' ? $formatted : '0';
    }
}
