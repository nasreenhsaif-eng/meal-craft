<?php

namespace App\Support;

/**
 * Meal Craft USDA rules: exclude restaurant / fast-food / branded categories; tighten chicken to poultry when category is known.
 */
final class UsdaMealCraftCategoryFilter
{
    private const USDA_POULTRY_PRODUCTS = 'Poultry Products';

    /** @var list<string> */
    private const EXCLUDED_CATEGORY_EXACT = [
        'Restaurant Foods',
        'Fast Foods',
        'Branded Foods',
    ];

    public static function ingredientNameSignalsChicken(string $name): bool
    {
        return preg_match('/\bchicken\b/ui', $name) === 1;
    }

    /**
     * FoodData Central {@see foodCategory.description}; empty string means unknown (allowed).
     */
    public static function categoryExcluded(?string $categoryDescription): bool
    {
        $c = trim((string) $categoryDescription);

        if ($c === '') {
            return false;
        }

        foreach (self::EXCLUDED_CATEGORY_EXACT as $excluded) {
            if (strcasecmp($c, $excluded) === 0) {
                return true;
            }
        }

        return preg_match('/\bbranded\b/ui', $c) === 1;
    }

    /**
     * @param  string|null  $ingredientStandardizedName  When set and chicken, require Poultry Products if category is present.
     */
    public static function categoryPassesMealCraftRules(?string $categoryDescription, ?string $ingredientStandardizedName): bool
    {
        if (self::categoryExcluded($categoryDescription)) {
            return false;
        }

        $cat = trim((string) $categoryDescription);

        if ($ingredientStandardizedName !== null
            && self::ingredientNameSignalsChicken($ingredientStandardizedName)
            && $cat !== ''
            && ! self::categoryIsUsdaPoultryLike($cat)) {
            return false;
        }

        return true;
    }

    /**
     * True for canonical FDC poultry category or any label that clearly denotes poultry (not e.g. beef or fish).
     */
    public static function categoryIsUsdaPoultryLike(string $categoryDescription): bool
    {
        $c = strtolower(trim($categoryDescription));

        if ($c === '') {
            return false;
        }

        if (strcasecmp(trim($categoryDescription), self::USDA_POULTRY_PRODUCTS) === 0) {
            return true;
        }

        return str_contains($c, 'poultry');
    }

    /**
     * Refined FDC search query for ingredient pickers (Livewire) and analysis flows.
     */
    public static function mealCraftSearchQueryForIngredient(string $ingredientName): string
    {
        $name = trim($ingredientName);

        if ($name === '') {
            return '';
        }

        if (! self::ingredientNameSignalsChicken($name)) {
            return $name;
        }

        if (preg_match('/raw\s+meat\s+only/ui', $name) === 1) {
            return $name;
        }

        $base = preg_replace('/\s*,\s*raw\s*$/ui', '', $name);

        return trim($base).' raw meat only';
    }
}
