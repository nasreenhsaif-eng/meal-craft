<?php

namespace App\Support;

use App\Models\Ingredient;
use InvalidArgumentException;

/**
 * Single-serving (1 bread) crepe-style quinoa flatbread. Scale ×10 for a batch of 10.
 */
final class QuinoaFlatbreadBaseRecipe
{
    public const NAME = 'Quinoa Flatbread (Base)';

    public const BATCH_SERVINGS = 10;

    /**
     * One bread = one serving. Batter pour is ~90–95 g; stored components sum to this finished weight.
     */
    public const GRAMS_PER_BREAD = 97.5;

    /**
     * Single-serving components (1 bread). Batch = each × {@see BATCH_SERVINGS}.
     *
     * @var array<string, float>
     */
    public const SERVING_COMPONENTS = [
        'Quinoa Flour' => 25.0,
        'Flaxseeds' => 3.0,
        'Psyllium Husks' => 2.0,
        'Olive Oil (Extra Virgin)' => 2.0,
        'Water (Filtered)' => 65.0,
        'Sea Salt' => 0.5,
    ];

    public const DESCRIPTION = 'One pliable, crepe-style gluten-free quinoa flatbread with ground flax and psyllium — pan-seared thin. Nutrition and ingredients are for 1 bread (1 serving). Scale ×10 for a batch of 10.';

    public const INSTRUCTIONS = 'Step 1: Whisk the Dry Base — For 1 bread, whisk together 25g quinoa flour, 3g finely ground flaxseed, 2g psyllium husk, and 0.5g fine sea salt. For a batch of 10, multiply each by 10 (250g / 30g / 20g / 5g).
Step 2: Incorporate Liquids — For 1 bread, pour in 65ml filtered water and 1g olive oil (reserve 1g for the pan). For a batch of 10, use 650ml water and 10g oil in the batter (reserve 10g for the pan). Whisk until smooth.
Step 3: Rest the Batter — Let sit 5 to 8 minutes until pourable and crepe-like. If it gels like dough, whisk in up to 5ml more water per bread (50ml for a batch of 10).
Step 4: Sear — Heat a 9–10 inch skillet over medium heat. Lightly wipe with reserved olive oil.
Step 5: Pour & Swirl — Pour roughly 90g to 95g of batter (one bread / one ladle) into the center. Swirl thin and wide across the base.
Step 6: Flip — Cook 2 to 3 minutes until the surface is matte and edges lift. Flip; cook 1 to 2 minutes until lightly golden.
Step 7: Stack & Steam — Stack finished flatbreads under a clean kitchen towel so steam keeps them soft and rollable.';

    public static function is(Ingredient|string $ingredient): bool
    {
        $name = $ingredient instanceof Ingredient ? trim((string) $ingredient->name) : trim($ingredient);

        return $name === self::NAME;
    }

    public static function finishedWeightGrams(): float
    {
        return self::GRAMS_PER_BREAD;
    }

    public static function gramsForBreadCount(int $breads = 1): float
    {
        return round(self::GRAMS_PER_BREAD * max(1, $breads), 1);
    }

    /**
     * @return array<string, float>
     */
    public static function batchComponents(): array
    {
        $batch = [];

        foreach (self::SERVING_COMPONENTS as $name => $grams) {
            $batch[$name] = round($grams * self::BATCH_SERVINGS, 1);
        }

        return $batch;
    }

    public static function recipeComponentsCsvCell(): string
    {
        $segments = [];

        foreach (self::SERVING_COMPONENTS as $name => $grams) {
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

        foreach (self::SERVING_COMPONENTS as $name => $grams) {
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

    /**
     * Scale stored per-100 g nutrition to one bread serving.
     *
     * @param  array<string, float>  $nutritionPer100g
     * @return array<string, float>
     */
    public static function nutritionPerServing(array $nutritionPer100g): array
    {
        $factor = self::GRAMS_PER_BREAD / 100.0;
        $out = [];

        foreach ($nutritionPer100g as $key => $value) {
            $out[$key] = round((float) $value * $factor, 4);
        }

        return $out;
    }

    private static function formatGrams(float $grams): string
    {
        $formatted = rtrim(rtrim(number_format($grams, 1, '.', ''), '0'), '.');

        return $formatted !== '' ? $formatted : '0';
    }
}
