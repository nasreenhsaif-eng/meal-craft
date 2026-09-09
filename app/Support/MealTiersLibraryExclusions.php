<?php

namespace App\Support;

use App\Models\Meal;

/**
 * Classic Meal Library rows that must not appear in the Meal Tiers Library.
 */
final class MealTiersLibraryExclusions
{
    /**
     * @return list<string>
     */
    public static function names(): array
    {
        /** @var list<string>|mixed $excluded */
        $excluded = config('meal_tiers_library.excluded_classic_meal_names', []);

        if (! is_array($excluded)) {
            return [];
        }

        $names = [];
        foreach ($excluded as $name) {
            if (! is_string($name)) {
                continue;
            }

            $trimmed = trim($name);
            if ($trimmed !== '') {
                $names[] = $trimmed;
            }
        }

        return array_values(array_unique($names));
    }

    public static function isExcluded(Meal|string $meal): bool
    {
        $name = $meal instanceof Meal ? trim((string) $meal->name) : trim($meal);

        if ($name === '') {
            return false;
        }

        return in_array($name, self::names(), true);
    }

    public static function weeklyProtocolReplacement(Meal|string $meal): ?string
    {
        $name = $meal instanceof Meal ? trim((string) $meal->name) : trim($meal);

        if ($name === '') {
            return null;
        }

        /** @var array<string, mixed>|mixed $replacements */
        $replacements = config('meal_tiers_library.weekly_protocol_replacements', []);

        if (! is_array($replacements)) {
            return null;
        }

        $replacement = $replacements[$name] ?? null;

        if (! is_string($replacement)) {
            return null;
        }

        $trimmed = trim($replacement);

        return $trimmed !== '' ? $trimmed : null;
    }
}
