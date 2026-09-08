<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Maps a consultation craft + daily calorie total to Meal Tiers Library plate tabs.
 *
 * @phpstan-type LibrarySlotRow array{
 *     breakfast: int,
 *     main_each: int,
 *     main_count: int,
 *     include_side_salad: bool,
 *     include_dessert: bool,
 *     include_soup: bool,
 * }
 */
final class CraftLibraryTierMap
{
    public const DEFAULT_CRAFT = 'full';

    /**
     * Full Craft totals used as the onboarding daily-need snap.
     *
     * @return list<int>
     */
    public static function planTiers(): array
    {
        return self::totalsForCraft(self::DEFAULT_CRAFT);
    }

    /**
     * @return list<int>
     */
    public static function totalsForCraft(string $craftKey): array
    {
        $rows = self::craftRows($craftKey);

        $totals = array_map(intval(...), array_keys($rows));
        sort($totals);

        return array_values($totals);
    }

    public static function snapToCraftTotal(float $calories, string $craftKey = self::DEFAULT_CRAFT): int
    {
        $tiers = self::totalsForCraft($craftKey);

        if ($tiers === []) {
            return (int) max(0, round($calories));
        }

        $nearest = $tiers[0];
        $smallestDistance = abs($calories - $nearest);

        foreach ($tiers as $tier) {
            $distance = abs($calories - $tier);

            if ($distance < $smallestDistance) {
                $smallestDistance = $distance;
                $nearest = $tier;
            }
        }

        return $nearest;
    }

    /**
     * @return LibrarySlotRow
     */
    public static function row(string $craftKey, float $calories): array
    {
        $total = self::snapToCraftTotal($calories, $craftKey);
        $rows = self::craftRows($craftKey);
        $row = $rows[$total] ?? null;

        if (! is_array($row)) {
            throw new InvalidArgumentException("Missing craft library tier [{$craftKey}] at {$total} kcal.");
        }

        return [
            'breakfast' => (int) ($row['breakfast'] ?? 0),
            'main_each' => (int) ($row['main_each'] ?? 0),
            'main_count' => max(1, (int) ($row['main_count'] ?? 1)),
            'include_side_salad' => (bool) ($row['include_side_salad'] ?? false),
            'include_dessert' => (bool) ($row['include_dessert'] ?? false),
            'include_soup' => (bool) ($row['include_soup'] ?? false),
        ];
    }

    public static function breakfastTab(string $craftKey, float $calories): int
    {
        return self::row($craftKey, $calories)['breakfast'];
    }

    public static function mainEachTab(string $craftKey, float $calories): int
    {
        return self::row($craftKey, $calories)['main_each'];
    }

    public static function calorieTabForSlot(string $craftKey, float $calories, string $slot): int
    {
        $row = self::row($craftKey, $calories);

        return match ($slot) {
            'breakfast' => $row['breakfast'],
            'main' => $row['main_each'],
            default => 0,
        };
    }

    /**
     * @return array<int, LibrarySlotRow>
     */
    private static function craftRows(string $craftKey): array
    {
        $key = is_array(config('craft_library_tiers.'.$craftKey))
            ? $craftKey
            : self::DEFAULT_CRAFT;

        /** @var array<int|string, mixed> $rows */
        $rows = config('craft_library_tiers.'.$key, []);

        if (! is_array($rows) || $rows === []) {
            throw new InvalidArgumentException("Unknown craft library map [{$key}].");
        }

        $normalized = [];

        foreach ($rows as $total => $row) {
            if (! is_array($row)) {
                continue;
            }

            $normalized[(int) $total] = $row;
        }

        return $normalized;
    }
}
