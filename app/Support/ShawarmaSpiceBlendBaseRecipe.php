<?php

namespace App\Support;

use App\Models\Ingredient;
use InvalidArgumentException;

/**
 * House shawarma spice blend batch — cumin, coriander, paprika, cinnamon, allspice, and garlic.
 */
final class ShawarmaSpiceBlendBaseRecipe
{
    public const NAME = 'Shawarma Spice Blend (Base)';

    /**
     * @var array<string, float>
     */
    public const COMPONENTS = [
        'coriander powder' => 12.0,
        'cumin powder' => 12.0,
        'Paprika' => 8.0,
        'Garlic Powder' => 4.0,
        'Cinnamon' => 3.0,
        'Allspice' => 3.0,
        'Turmeric' => 2.0,
        'Black Pepper' => 2.0,
        'Sea Salt' => 2.0,
    ];

    public const DESCRIPTION = 'Levant shawarma spice mix with cumin, coriander, paprika, cinnamon, allspice, and garlic powder.';

    public const INSTRUCTIONS = 'Step 1: Toast cumin powder and coriander powder lightly in a dry pan until fragrant; cool.
Step 2: Whisk together paprika, garlic powder, cinnamon, allspice, turmeric, black pepper, and sea salt.
Step 3: Stir in the toasted cumin and coriander until evenly blended.
Step 4: Store airtight away from heat and light. Rub onto beef, chicken, or lamb before roasting.';

    public static function finishedWeightGrams(): float
    {
        return round(array_sum(self::COMPONENTS), 1);
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
