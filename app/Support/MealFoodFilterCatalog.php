<?php

namespace App\Support;

/**
 * Canonical meal-library food filter slugs (aligned with customer onboarding filters plus fish and sesame).
 */
final class MealFoodFilterCatalog
{
    public const SESAME = 'sesame';

    public const GLUTEN = 'gluten';

    public const SOY = 'soy';

    public const SHELLFISH = 'shellfish';

    public const EGGS = 'eggs';

    public const DAIRY = 'dairy';

    public const FISH = 'fish';

    public const NUTS = 'nuts';

    public const BEANS = 'beans';

    public const NIGHTSHADES = 'nightshades';

    public const SPICY = 'spicy';

    /** @var list<string> */
    public const SLUGS = [
        self::SESAME,
        self::GLUTEN,
        self::SOY,
        self::SHELLFISH,
        self::EGGS,
        self::DAIRY,
        self::FISH,
        self::NUTS,
        self::BEANS,
        self::NIGHTSHADES,
        self::SPICY,
    ];

    /**
     * @return array<string, string> slug => safety alert label
     */
    public static function safetyLabelsBySlug(): array
    {
        return [
            self::SESAME => 'Sesame',
            self::GLUTEN => 'Gluten',
            self::SOY => 'Soy',
            self::SHELLFISH => 'Shellfish',
            self::EGGS => 'Eggs',
            self::DAIRY => 'Dairy',
            self::FISH => 'Fish',
            self::NUTS => 'Nuts',
            self::BEANS => 'Beans',
            self::NIGHTSHADES => 'Nightshades',
            self::SPICY => 'Spicy',
        ];
    }

    public static function resolveSlugCanonical(string $raw): ?string
    {
        $key = strtolower(trim($raw));
        if ($key === '') {
            return null;
        }

        return in_array($key, self::SLUGS, true) ? $key : null;
    }

    /**
     * @param  list<string>|null  $slugs
     * @return list<string>
     */
    public static function canonicalSlugsFromList(?array $slugs): array
    {
        if ($slugs === null || $slugs === []) {
            return [];
        }

        $out = [];
        foreach ($slugs as $slug) {
            if (! is_string($slug)) {
                continue;
            }
            $canonical = self::resolveSlugCanonical($slug);
            if ($canonical !== null) {
                $out[$canonical] = true;
            }
        }

        $keys = array_keys($out);
        sort($keys);

        return $keys;
    }

    /**
     * @param  list<string>|null  $slugs
     * @return list<string>
     */
    public static function safetyLabelsFromSlugs(?array $slugs): array
    {
        $map = self::safetyLabelsBySlug();
        $labels = [];

        foreach (self::canonicalSlugsFromList($slugs) as $slug) {
            $labels[$map[$slug]] = true;
        }

        $out = array_keys($labels);
        sort($out);

        return $out;
    }
}
