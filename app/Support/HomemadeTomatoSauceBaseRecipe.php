<?php

namespace App\Support;

use App\Models\Ingredient;
use InvalidArgumentException;

/**
 * Ten-portion simple stew tomato sauce — raw tomatoes and paste, not marinara.
 */
final class HomemadeTomatoSauceBaseRecipe
{
    public const NAME = 'Homemade Tomato Sauce (Base)';

    public const SERVINGS = 10;

    /** Finished sauce grams for one 500 kcal reference portion when used in a stew. */
    public const PER_SERVING_GRAMS = 60.0;

    /**
     * @var array<string, float>
     */
    public const BATCH_COMPONENTS = [
        'Tomato (Raw)' => 500.0,
        'Tomato Paste' => 40.0,
        'Garlic (Raw)' => 10.0,
        'Olive Oil (Extra Virgin)' => 15.0,
        'Sea Salt' => 2.0,
        'Black Pepper' => 1.0,
    ];

    public const DESCRIPTION = 'Simple from-scratch tomato sauce simmered from fresh tomatoes and paste with garlic and olive oil — for stews and curries, not marinara.';

    public const INSTRUCTIONS = 'Step 1: Warm olive oil in a saucepan over medium heat. Sauté minced garlic until fragrant (30 seconds).
Step 2: Add chopped tomatoes and tomato paste. Simmer 20–25 minutes, stirring occasionally, until thickened.
Step 3: Season with sea salt and black pepper. Blend smooth if desired. Hold hot for stew assembly.';

    public static function finishedWeightGrams(): float
    {
        return round(self::PER_SERVING_GRAMS * self::SERVINGS, 1);
    }

    public static function recipeComponentsCsvCell(): string
    {
        $segments = [];

        foreach (self::BATCH_COMPONENTS as $name => $grams) {
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

        foreach (self::BATCH_COMPONENTS as $name => $grams) {
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
