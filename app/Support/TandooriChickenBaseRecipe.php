<?php

namespace App\Support;

use App\Models\Ingredient;
use InvalidArgumentException;

/**
 * 1 kg raw-chicken batch for Tandoori Chicken (Base).
 *
 * Marinade oil is 30 g (~2 tbsp) per kg chicken, matching the rosemary kitchen base.
 * Meal amounts are cooked portion grams.
 */
final class TandooriChickenBaseRecipe
{
    public const NAME = 'Tandoori Chicken (Base)';

    public const RAW_CHICKEN_GRAMS = 1000.0;

    /**
     * @var array<string, float>
     */
    public const COMPONENTS = [
        'Chicken Breast' => 1000.0,
        'Olive Oil (Extra Virgin)' => 30.0,
        'Coconut Milk' => 86.0,
        'Lemon Juice' => 43.0,
        'Tandoori Spice Mix (Base)' => 71.0,
        'Garlic (Raw)' => 23.0,
    ];

    public const DESCRIPTION = 'Tandoori-marinated chicken breast using house tandoori spice mix, lemon, and coconut.';

    public const INSTRUCTIONS = 'Step 1: Coat chicken evenly with Tandoori Spice Mix (Base), lemon juice, coconut milk, and olive oil.
Step 2: Marinate at least 4 hours or overnight.
Step 3: Grill or pan-sear until golden then bake in the oven for 20 minutes exactly.
Step 4: Rest and slice.';

    public static function finishedWeightGrams(): float
    {
        $retained = 0.0;

        foreach (self::COMPONENTS as $name => $grams) {
            if ($name === 'Chicken Breast') {
                continue;
            }

            $retained += $grams;
        }

        return ChickenBreastYield::estimateMarinatedFinishedWeight(self::RAW_CHICKEN_GRAMS, $retained);
    }

    public static function cookedGramsForRawChicken(float $rawChickenGrams): float
    {
        return round($rawChickenGrams / self::RAW_CHICKEN_GRAMS * self::finishedWeightGrams(), 1);
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
