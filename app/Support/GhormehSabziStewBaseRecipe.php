<?php

namespace App\Support;

use App\Models\Ingredient;
use InvalidArgumentException;

/**
 * Ten-portion sabzi + bean batch for Persian ghormeh sabzi plates (beef portioned separately).
 *
 * Meal amounts are cooked plated grams of this finished stew base per serving.
 */
final class GhormehSabziStewBaseRecipe
{
    public const NAME = 'Ghormeh Sabzi Stew (Base)';

    public const SERVINGS = 10;

    /**
     * Cooked plated grams of finished stew base for one 500 kcal reference plate.
     *
     * @var array<string, float>
     */
    public const PER_SERVING_COMPONENTS = [
        'Cooked Cannellini Beans (Base)' => 60.0,
        'Chard' => 20.0,
        'Parsley' => 15.0,
        'Fresh Coriander' => 12.0,
        'Spinach (Fresh)' => 20.0,
        'Dill (Fresh)' => 8.0,
        'Purslane' => 10.0,
        'Fenugreek Leaves (Fresh)' => 6.0,
        'White Onion' => 25.0,
        'Garlic (Raw)' => 5.0,
        'Olive Oil' => 8.0,
    ];

    public const PER_SERVING_GRAMS = 189.0;

    public const DESCRIPTION = 'Classic Persian sabzi — cannellini beans with chard, parsley, coriander, dill, spinach, fenugreek, and purslane wilted in olive oil.';

    public const INSTRUCTIONS = 'Step 1: Prepare Cooked Cannellini Beans (Base) per base recipe instructions (or use prepped batch).
Step 2: Sauté diced onion and minced garlic in olive oil until softened.
Step 3: Add cannellini beans with enough water to simmer; cook 15 minutes.
Step 4: Finely chop chard, parsley, coriander, dill, spinach, fenugreek leaves, and purslane.
Step 5: Wilt the herb sabzi in olive oil over medium heat until deep green and fragrant.
Step 6: Fold the wilted herbs into the bean mixture; simmer 10 minutes. Hold hot for service.';

    public static function finishedWeightGrams(): float
    {
        return round(self::PER_SERVING_GRAMS * self::SERVINGS, 1);
    }

    /**
     * @return array<string, float>
     */
    public static function batchComponents(): array
    {
        $batch = [];

        foreach (self::PER_SERVING_COMPONENTS as $name => $grams) {
            $batch[$name] = round($grams * self::SERVINGS, 1);
        }

        return $batch;
    }

    public static function recipeComponentsCsvCell(): string
    {
        $segments = [];

        foreach (self::batchComponents() as $name => $grams) {
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

        foreach (self::batchComponents() as $name => $grams) {
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
