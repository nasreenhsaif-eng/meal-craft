<?php

namespace App\Support;

use App\Models\Ingredient;

/**
 * Standard raw primary protein portion for beef, chicken, and fish mains.
 */
final class StandardMeatPortion
{
    public const GRAMS = 150.0;

    /**
     * Target grams for the beef/ground component when the meal also includes a minced liver blend.
     */
    public static function beefGramsForLiverBlendMeal(float $liverBlendGrams): float
    {
        return max(0.0, round(self::GRAMS - $liverBlendGrams, 2));
    }

    /**
     * Beef liver minced into ground beef (not a dedicated liver main).
     */
    public static function isLiverBlendIngredient(string $ingredientName, ?string $mealName = null): bool
    {
        $name = strtolower(trim($ingredientName));

        if (! str_contains($name, 'liver')) {
            return false;
        }

        return ! self::isLiverMainMeal(strtolower(trim((string) $mealName)));
    }

    /**
     * @param  iterable<int, Ingredient>  $ingredients
     */
    public static function liverBlendGramsOnMeal(iterable $ingredients, ?string $mealName = null): float
    {
        $total = 0.0;

        foreach ($ingredients as $ingredient) {
            if (! self::isLiverBlendIngredient($ingredient->name, $mealName)) {
                continue;
            }

            $total += (float) ($ingredient->pivot->amount_grams ?? $ingredient->pivot->amount ?? 0);
        }

        return round($total, 2);
    }

    /**
     * Primary beef portion target — 150 g alone, or 150 g minus liver blend on combo meals.
     *
     * @param  iterable<int, Ingredient>  $ingredients
     */
    public static function targetPrimaryBeefGrams(iterable $ingredients, ?string $mealName = null): float
    {
        $liverBlend = self::liverBlendGramsOnMeal($ingredients, $mealName);

        if ($liverBlend > 0.0) {
            return self::beefGramsForLiverBlendMeal($liverBlend);
        }

        return self::GRAMS;
    }

    /**
     * Whether this ingredient is the primary beef/chicken/fish protein for a meal.
     */
    public static function isPrimaryMeatIngredient(string $ingredientName, ?string $mealName = null): bool
    {
        $name = strtolower(trim($ingredientName));
        $meal = strtolower(trim((string) $mealName));

        if ($name === '' || self::isExcluded($name)) {
            return false;
        }

        if (str_contains($name, 'liver')) {
            return self::isLiverMainMeal($meal);
        }

        foreach (self::primaryMeatPatterns() as $pattern) {
            if ($name === $pattern || str_contains($name, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function primaryMeatPatterns(): array
    {
        return [
            'beef brisket',
            'beef chuck',
            'beef ground',
            'beef ribeye',
            'beef sirloin',
            'beef topside',
            'chicken breast',
            'chicken thigh',
            'salmon',
            'hamour',
            'shrimp',
            'prawn',
            'tuna (canned)',
            'sardines',
            'rosemary garlic chicken (base)',
            'tandoori chicken (base)',
            'tandoori salmon (base)',
            'spiced aleppo ground beef (base)',
            'italian meatballs (base)',
        ];
    }

    private static function isExcluded(string $name): bool
    {
        foreach (['fish sauce', 'broth', 'bone', 'coconut chicken curry'] as $excluded) {
            if (str_contains($name, $excluded)) {
                return true;
            }
        }

        return false;
    }

    private static function isLiverMainMeal(string $meal): bool
    {
        return str_contains($meal, 'seared beef liver')
            || str_contains($meal, 'seared chicken liver')
            || str_contains($meal, 'sautéed chicken liver')
            || str_contains($meal, 'sauteed chicken liver');
    }
}
