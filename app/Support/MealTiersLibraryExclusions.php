<?php

namespace App\Support;

use App\Models\Meal;

/**
 * Classic Meal Library rows that must not appear in the Meal Tiers Library.
 */
final class MealTiersLibraryExclusions
{
    public static function isExcluded(Meal|string $meal): bool
    {
        $name = $meal instanceof Meal ? trim((string) $meal->name) : trim($meal);

        if ($name === '') {
            return false;
        }

        /** @var list<string> $excluded */
        $excluded = config('meal_tiers_library.excluded_classic_meal_names', []);

        return in_array($name, $excluded, true);
    }
}
