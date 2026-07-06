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
            // Recycled legacy ids in recipe_components (numeric ids no longer match library rows).
            4 => 'Smoked Paprika',
            5 => 'Coconut Cream',
            12 => 'Gochugaru',
            17 => 'Green Beans',
            18 => 'Onion Powder',
            24 => 'Dijon Mustard',
            28 => 'Fresh Coriander',
            29 => 'Fresh Mint',
            31 => 'Cumin Seeds',
            38 => 'Napa Cabbage',
            39 => 'Beef Bones (Feet)',
            40 => 'Bell Pepper (Red)',
            41 => 'Garlic (Raw)',
            42 => 'Carrots',
            55 => 'Black Lentils',
            56 => 'Black Pepper',
            58 => 'Garlic (Raw)',
            59 => 'Black Beans',
            65 => 'Cucumber',
            68 => 'Coconut Milk',
            71 => 'Basmati Rice (White)',
            72 => 'Basmati Rice (Brown)',
            80 => 'Lemon Juice',
            87 => 'Chicken Broth',
            90 => 'Tomato Paste',
            102 => 'Spring Onion',
            108 => 'Cardamom',
            111 => 'Almond Flour',
            113 => 'Beef Chuck Roast',
            124 => 'Coconut Milk',
            125 => 'Baking Soda',
            129 => 'Paprika',
            140 => 'Blackened Seasoning (Base)',
            143 => 'Sea Salt',
            150 => 'Zucchini',
            156 => 'Cannellini Beans',
            164 => 'Dried Oregano',
            167 => 'Honey (Raw)',
            170 => 'Chickpeas',
            171 => 'French Lentils',
            172 => 'Fresh Basil',
            176 => 'Fresh Parsley',
            179 => 'Garam Masala',
            183 => 'Garlic (Raw)',
            184 => 'Garlic (Raw)',
            185 => 'Ghee (Clarified)',
            187 => 'Fresh Dill',
            189 => 'Eggplant',
            190 => 'Eggs (Large)',
            193 => 'Garlic (Raw)',
            194 => 'Ginger (Raw)',
            195 => 'Greek Yogurt',
            210 => 'Olive Oil',
            212 => 'Olive Oil',
            100 => 'Honey (Raw)',
            215 => 'Chia Seeds',
            220 => 'Zucchini',
            223 => 'Nutmeg',
            224 => 'Jasmine Rice',
            235 => 'Almonds',
            236 => 'Quinoa (White)',
            238 => 'Rice Vinegar',
            239 => 'Lemon Juice',
            244 => 'Sea Salt',
            250 => 'Star Anise',
            255 => 'Tahini',
            256 => 'Tomato Paste',
            259 => 'Thyme (Fresh)',
            263 => 'Lavender (Ground)',
            272 => 'Psyllium Husks',
            279 => 'Cumin Powder',
            284 => 'Water (Filtered)',
            286 => 'Basmati Rice (White)',
            292 => 'Apple Cider Vinegar',
            310 => 'Pumpkin Seeds',
            313 => 'Quinoa (White)',
            331 => 'Grapefruit',
            338 => 'Fresh Parsley',
            342 => 'Saffron',
            344 => 'Mint (Fresh)',
            345 => 'Curry Powder',
            349 => 'Sea Salt',
            350 => 'Sea Salt',
            374 => 'Sumac',
            382 => 'Tamari Sauce',
            395 => 'Cayenne Pepper',
            396 => 'Onion Powder',
            398 => 'Fire Roasted Tomatoes (Base)',
            401 => 'Dried Parsley',
            402 => 'Chives (Dried)',
            407 => 'Cooked Chickpeas (Base)',
            81 => 'Napa Cabbage',
            131 => 'Turmeric Powder',
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
            516 => 'Ginger (Raw)',
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
