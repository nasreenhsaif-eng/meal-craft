<?php

namespace App\Support;

/**
 * Canonical common-allergen keys stored on {@see \App\Models\Ingredient::$common_allergens}
 * and surfaced as human-readable safety tags on meals.
 */
final class IngredientAllergenCatalog
{
    public const PEANUTS = 'peanuts';

    public const TREE_NUTS = 'tree_nuts';

    public const DAIRY = 'dairy';

    public const EGGS = 'eggs';

    public const SOY = 'soy';

    public const WHEAT = 'wheat';

    public const FISH = 'fish';

    public const SHELLFISH = 'shellfish';

    public const SESAME = 'sesame';

    /**
     * @return array<string, string> slug => display label (unique per meal)
     */
    public static function labelsBySlug(): array
    {
        return [
            self::PEANUTS => 'Contains: Peanuts',
            self::TREE_NUTS => 'Contains: Tree nuts',
            self::DAIRY => 'Contains: Dairy',
            self::EGGS => 'Contains: Eggs',
            self::SOY => 'Contains: Soy',
            self::WHEAT => 'Contains: Wheat / Gluten',
            self::FISH => 'Contains: Fish',
            self::SHELLFISH => 'Contains: Shellfish',
            self::SESAME => 'Contains: Sesame',
        ];
    }

    /**
     * @param  list<string>|null  $slugs
     * @return list<string>
     */
    public static function labelsFromSlugs(?array $slugs): array
    {
        if ($slugs === null || $slugs === []) {
            return [];
        }

        $map = self::labelsBySlug();
        $out = [];
        foreach ($slugs as $slug) {
            if (! is_string($slug)) {
                continue;
            }
            $key = strtolower(trim($slug));
            if ($key === '' || ! isset($map[$key])) {
                continue;
            }
            $out[$map[$key]] = true;
        }

        $labels = array_keys($out);
        sort($labels);

        return $labels;
    }
}
