<?php

namespace App\Support;

/**
 * Restores legacy menu ingredient ids from the May 2026 snapshot into current library names.
 *
 * @phpstan-type LegacyMap array<int, string>
 */
final class LegacyMenuIngredientIdMap
{
    /** @var LegacyMap|null */
    private static ?array $map = null;

    /**
     * @return LegacyMap
     */
    public static function legacyIdToName(): array
    {
        if (self::$map !== null) {
            return self::$map;
        }

        $path = database_path('data/menu/legacy_ingredient_id_map.json');
        if (! is_file($path)) {
            self::$map = [];

            return self::$map;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded)) {
            self::$map = [];

            return self::$map;
        }

        $map = [];
        foreach ($decoded as $id => $name) {
            if (is_string($name) && trim($name) !== '' && (is_int($id) || ctype_digit((string) $id))) {
                $map[(int) $id] = trim($name);
            }
        }

        foreach (self::manualOverrides() as $legacyId => $ingredientName) {
            $map[$legacyId] = $ingredientName;
        }

        self::$map = $map;

        return self::$map;
    }

    /**
     * Authoritative legacy-id → library-name fixes when git inference or recycled DB ids disagree.
     *
     * @return LegacyMap
     */
    private static function manualOverrides(): array
    {
        return [
            309 => 'Cashew Nuts',
            316 => 'Chicken Breast',
            337 => 'Eggplant',
            359 => 'Curry Spice Mix',
            372 => 'Water (Filtered)',
            384 => 'Quinoa (White)',
            387 => 'Rosemary (Fresh)',
            403 => 'Tahini',
            420 => 'Zucchini',
            433 => 'Za\'atar',
            450 => 'Basil',
            465 => 'Turmeric Powder',
            515 => 'Fresh Coriander',
            516 => 'Ginger',
            522 => 'Mustard Oil',
            584 => 'Oregano',
            436 => 'Beef Chuck Roast',
            466 => 'Basmati Rice (White)',
            495 => 'Cherry Tomatoes',
            498 => 'Quinoa Flour',
            503 => 'Apple Cider Vinegar',
            335 => 'Fresh Parsley',
            587 => 'Chili Powder',
        ];
    }

    public static function nameForLegacyId(int $legacyId): ?string
    {
        return self::legacyIdToName()[$legacyId] ?? null;
    }

    public static function resetCacheForTesting(): void
    {
        self::$map = null;
    }
}
