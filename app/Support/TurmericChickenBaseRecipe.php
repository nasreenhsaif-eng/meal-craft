<?php

namespace App\Support;

use App\Models\Ingredient;
use InvalidArgumentException;

/**
 * 1 kg raw-chicken batch for Turmeric Chicken (Base).
 *
 * Marinade oil is 30 g (~2 tbsp) per kg chicken, matching the rosemary kitchen base.
 * Meal amounts are cooked portion grams.
 */
final class TurmericChickenBaseRecipe
{
    public const NAME = 'Turmeric Chicken (Base)';

    public const RAW_CHICKEN_GRAMS = 1000.0;

    /**
     * @var array<string, float>
     */
    public const COMPONENTS = [
        'Chicken Breast' => 1000.0,
        'Olive Oil (Extra Virgin)' => 30.0,
        'Garlic (Raw)' => 44.0,
        'Ginger (Raw)' => 33.0,
        'Turmeric' => 22.0,
        'Lemon Zest' => 17.0,
        'Sea Salt' => 11.0,
        'White Peppercorns' => 6.0,
    ];

    public const DESCRIPTION = 'Chicken breast marinated with turmeric, ginger, garlic, lemon zest, sea salt, and white pepper.';

    public const INSTRUCTIONS = 'Step 1: Mince garlic and grate ginger.
Step 2: Mix turmeric, lemon zest, sea salt, white pepper, and olive oil with the aromatics.
Step 3: Rub evenly over the chicken breast and rest at least 15 minutes.
Step 4: Grill or pan-sear until golden, then bake for 20 minutes exactly.
Step 5: Rest and slice.';

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
