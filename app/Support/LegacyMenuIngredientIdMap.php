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

        self::$map = $map;

        return self::$map;
    }

    public static function nameForLegacyId(int $legacyId): ?string
    {
        return self::legacyIdToName()[$legacyId] ?? null;
    }
}
