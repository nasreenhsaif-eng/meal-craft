<?php

namespace App\Support;

use App\Models\Ingredient;
use InvalidArgumentException;

/**
 * Ten-portion shawarma beef batch — chuck rubbed with house shawarma spice, then slow-roasted and shredded.
 *
 * Meal amounts are cooked plated grams of this finished base per serving.
 */
final class BeefShawarmaBaseRecipe
{
    public const NAME = 'Beef Shawarma (Base)';

    public const SERVINGS = 10;

    public const RAW_BEEF_GRAMS = 1500.0;

    /** Moisture loss when chuck is slow-roasted and shredded (matches {@see IngredientCookingYield}). */
    public const RAW_TO_COOKED_BEEF_RATIO = 0.70;

    /**
     * @var array<string, float>
     */
    public const COMPONENTS = [
        'Beef Chuck Roast' => self::RAW_BEEF_GRAMS,
        'Shawarma Spice Blend (Base)' => 30.0,
        'Olive Oil (Extra Virgin)' => 20.0,
        'Lemon Juice' => 10.0,
        'Garlic (Raw)' => 10.0,
    ];

    /** Cooked plated grams of finished shawarma base for one 500 kcal reference plate. */
    public const PER_SERVING_GRAMS = 112.0;

    public const DESCRIPTION = 'Shredded shawarma-spiced beef chuck rubbed with house spice blend, lemon, garlic, and olive oil, then slow-roasted until tender.';

    public const INSTRUCTIONS = 'Step 1: Prepare Shawarma Spice Blend (Base) per base recipe instructions.
Step 2: Rub beef chuck evenly with shawarma spice blend, olive oil, lemon juice, and minced garlic. Marinate at least 2 hours or overnight.
Step 3: Slow-roast or braise until fork-tender; rest, then shred.
Step 4: Hold hot for service.';

    public static function finishedWeightGrams(): float
    {
        $retained = 0.0;

        foreach (self::COMPONENTS as $name => $grams) {
            if ($name === 'Beef Chuck Roast') {
                continue;
            }

            $retained += $grams;
        }

        return round(self::RAW_BEEF_GRAMS * self::RAW_TO_COOKED_BEEF_RATIO + $retained, 1);
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
