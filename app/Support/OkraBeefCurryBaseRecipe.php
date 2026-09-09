<?php

namespace App\Support;

use App\Models\Ingredient;
use InvalidArgumentException;

/**
 * Ten-portion okra beef curry batch — chuck, okra, and homemade tomato sauce finished with garlic-coriander tadka.
 *
 * Meal amounts are cooked plated grams of beef, okra, and sauce portioned separately per serving.
 */
final class OkraBeefCurryBaseRecipe
{
    public const NAME = 'Okra Beef Curry (Base)';

    public const SERVINGS = 10;

    /** Moisture loss when beef chuck is slow-braised (matches {@see IngredientCookingYield}). */
    public const RAW_TO_COOKED_BEEF_RATIO = 0.70;

    /**
     * Raw batch inputs per serving before cooking (beef yield applied for finished weight).
     *
     * @var array<string, float>
     */
    public const PER_SERVING_COMPONENTS = [
        'Beef Chuck Roast' => 150.0,
        'Okra' => 120.0,
        'Homemade Tomato Sauce (Base)' => HomemadeTomatoSauceBaseRecipe::PER_SERVING_GRAMS,
        'White Onion' => 35.0,
        'Garlic (Raw)' => 5.0,
        'Coriander Seeds' => 2.0,
        'Olive Oil (Extra Virgin)' => 5.0,
    ];

    /** Cooked plated grams of finished stew (beef + okra + sauce) for one 500 kcal reference plate. */
    public const PER_SERVING_GRAMS = 332.0;

    public const DESCRIPTION = 'Slow-braised beef chuck and okra in from-scratch homemade tomato sauce, finished with garlic and coriander seeds bloomed in olive oil.';

    public const INSTRUCTIONS = 'Step 1: Prepare Homemade Tomato Sauce (Base) per base recipe instructions.
Step 2: Sear cubed beef chuck in a little olive oil until deeply browned.
Step 3: Sauté diced white onion until softened. Add homemade tomato sauce and a splash of water; return beef and simmer until fork-tender (2–3 hours on low, or 6–8 hours in a slow cooker).
Step 4: Trim okra and add whole pods for the final 45 minutes until tender but intact.
Step 5: Bloom minced garlic and coriander seeds in olive oil until fragrant; stir into the stew.
Step 6: Hold hot for service. Portion beef, okra, and sauce separately when plating.';

    public static function finishedWeightGrams(): float
    {
        $retained = 0.0;

        foreach (self::PER_SERVING_COMPONENTS as $name => $grams) {
            if ($name === 'Beef Chuck Roast') {
                continue;
            }

            $retained += $grams;
        }

        $beefCooked = self::PER_SERVING_COMPONENTS['Beef Chuck Roast']
            * self::SERVINGS
            * self::RAW_TO_COOKED_BEEF_RATIO;

        return round($beefCooked + ($retained * self::SERVINGS), 1);
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
