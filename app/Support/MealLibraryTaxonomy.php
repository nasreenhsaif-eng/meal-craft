<?php

namespace App\Support;

/**
 * Canonical labels for the admin Meal Library create form and CSV-aligned workflows.
 */
final class MealLibraryTaxonomy
{
    /** @var list<string> */
    public const MEAL_PLAN_TAGS = [
        'Balanced',
        'Hormone Feast',
        'Ketogenic',
        'Sickle Cell Anemia',
    ];

    /** @var list<string> */
    public const DIETARY_TAGS = [
        'Vegan',
        'Vegetarian',
        'Dairy-free',
        'Gluten-free',
        'Nut-free',
        'Spicy',
    ];

    /**
     * Return the canonical meal-plan tag string if {@code $raw} matches one of {@see self::MEAL_PLAN_TAGS} (case-insensitive).
     */
    public static function resolveMealPlanTagCanonical(string $raw): ?string
    {
        $t = trim($raw);
        if ($t === '') {
            return null;
        }

        foreach (self::MEAL_PLAN_TAGS as $canonical) {
            if (strcasecmp($canonical, $t) === 0) {
                return $canonical;
            }
        }

        return null;
    }
}
