<?php

namespace App\Support;

/**
 * Resolves clinically relevant vitamin K2 (menaquinone) per 100 g from USDA FDC maps and category rules.
 *
 * K1 (phylloquinone) is never counted as K2. Plant-forward categories default to 0 unless FDC reports menaquinone.
 */
final class VitaminK2Resolver
{
    /** Clinical MK-7 reference for natto when USDA SR Legacy only reports phylloquinone. µg per 100 g. */
    public const NATTO_MK7_MCG_PER_100G = 1034.0;

    /** Typical MK-4 in butter / clarified butter when FDC lacks menaquinone rows. µg per 100 g. */
    public const BUTTER_GHEE_MK4_MCG_PER_100G = 8.0;

    /** Egg yolk MK-4 literature estimate. µg per 100 g. */
    public const EGG_YOLK_MK4_MCG_PER_100G = 32.0;

    /** Chicken / beef liver MK-4 literature estimate. µg per 100 g. */
    public const LIVER_MK4_MCG_PER_100G = 12.0;

    /** Red meat (beef, lamb) MK-4 when USDA omits menaquinone rows. µg per 100 g. */
    public const RED_MEAT_MK4_MCG_PER_100G = 1.5;

    /** Poultry muscle meat MK-4 estimate. µg per 100 g. */
    public const POULTRY_MEAT_MK4_MCG_PER_100G = 1.0;

    /** Poultry dark meat / thigh MK-4 estimate. µg per 100 g. */
    public const POULTRY_DARK_MEAT_MK4_MCG_PER_100G = 2.0;

    /** Pork MK-4 estimate. µg per 100 g. */
    public const PORK_MK4_MCG_PER_100G = 1.2;

    /**
     * Categories where K1 dominates and K2 should be 0 unless FDC reports menaquinone explicitly.
     *
     * @var list<string>
     */
    private const K1_DOMINANT_CATEGORY_FRAGMENTS = [
        'vegetable',
        'herb',
        'fruit',
        'spice',
        'grain',
        'legume',
        'pantry',
        'liquid',
        'beverage',
        'sweetener',
        'condiment',
        'supplement',
        'soup',
        'broth',
    ];

    /**
     * Animal / fermented categories where literature overrides apply when FDC menaquinone is missing.
     *
     * @var list<string>
     */
    private const ANIMAL_K2_CATEGORY_FRAGMENTS = [
        'dairy',
        'fat',
        'protein',
        'meat',
        'poultry',
        'fish',
        'seafood',
        'egg',
    ];

    /**
     * @param  array<string, float>  $byNumber  FDC nutrient map from {@see UsdaNutrientMath::mapByNutrientNumber()}.
     */
    public static function menaquinoneMcgPer100gFromFdcMap(array $byNumber): float
    {
        return UsdaNutrientMath::valueForNutrientKeys(
            $byNumber,
            UsdaNutrientMath::FDC_MENAQUINONE_4,
            UsdaNutrientMath::NDB_MENAQUINONE_4,
        );
    }

    /**
     * @param  array<string, float>  $byNumber
     */
    public static function phylloquinoneMcgPer100gFromFdcMap(array $byNumber): float
    {
        return UsdaNutrientMath::valueForNutrientKeys(
            $byNumber,
            UsdaNutrientMath::FDC_PHYLLOQUINONE,
            UsdaNutrientMath::NDB_PHYLLOQUINONE,
        );
    }

    /**
     * Resolve vitamin K2 (menaquinone, µg per 100 g) for an ingredient row.
     */
    public static function resolve(string $ingredientName, string $category, array $byNumber): float
    {
        $fdcMenaquinone = self::menaquinoneMcgPer100gFromFdcMap($byNumber);
        $fdcPhylloquinone = self::phylloquinoneMcgPer100gFromFdcMap($byNumber);

        $manual = self::manualOverrideMcgPer100g($ingredientName, $category);
        if ($manual !== null) {
            return round($manual, 2);
        }

        if ($fdcMenaquinone > 0) {
            return round($fdcMenaquinone, 2);
        }

        if (self::isK1DominantCategory($category, $ingredientName)) {
            return 0.0;
        }

        if (self::isAnimalK2Category($category)) {
            return round(self::animalCategoryEstimateMcgPer100g($ingredientName, $category, $fdcPhylloquinone), 2);
        }

        return 0.0;
    }

    public static function isK1DominantCategory(string $category, string $ingredientName = ''): bool
    {
        if (IngredientLibraryCategory::isPrepared($category)) {
            return false;
        }

        $normalizedName = self::normalize($ingredientName);

        if (self::nameMatchesAny($normalizedName, ['almond butter', 'peanut butter', 'cashew butter', 'coconut butter', 'nut butter'])) {
            return true;
        }

        if (self::nameMatchesAny($normalizedName, ['butter bean', 'butternut'])) {
            return true;
        }

        $normalizedCategory = self::normalize($category);

        foreach (self::K1_DOMINANT_CATEGORY_FRAGMENTS as $fragment) {
            if (str_contains($normalizedCategory, $fragment)) {
                return true;
            }
        }

        return false;
    }

    public static function isAnimalK2Category(string $category): bool
    {
        if (IngredientLibraryCategory::isPrepared($category)) {
            return false;
        }

        $normalizedCategory = self::normalize($category);

        foreach (self::ANIMAL_K2_CATEGORY_FRAGMENTS as $fragment) {
            if (str_contains($normalizedCategory, $fragment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{menaquinone: float, phylloquinone: float, vitamin_k2: float}
     */
    public static function resolveWithBreakdown(string $ingredientName, string $category, array $byNumber): array
    {
        $menaquinone = self::menaquinoneMcgPer100gFromFdcMap($byNumber);
        $phylloquinone = self::phylloquinoneMcgPer100gFromFdcMap($byNumber);

        return [
            'menaquinone' => $menaquinone,
            'phylloquinone' => $phylloquinone,
            'vitamin_k2' => self::resolve($ingredientName, $category, $byNumber),
        ];
    }

    private static function manualOverrideMcgPer100g(string $ingredientName, string $category): ?float
    {
        $name = self::normalize($ingredientName);

        if (self::nameMatchesAny($name, ['natto'])) {
            return self::NATTO_MK7_MCG_PER_100G;
        }

        if (self::nameMatchesAny($name, ['egg yolk'])) {
            return self::EGG_YOLK_MK4_MCG_PER_100G;
        }

        if (self::nameMatchesAny($name, ['liver'])) {
            return self::LIVER_MK4_MCG_PER_100G;
        }

        if (
            self::nameMatchesAny($name, ['ghee', 'clarified butter'])
            || ($name === 'butter' || str_starts_with($name, 'butter '))
        ) {
            if (! self::nameMatchesAny($name, ['butter bean', 'butternut', 'peanut', 'almond', 'cashew', 'coconut'])) {
                return self::BUTTER_GHEE_MK4_MCG_PER_100G;
            }
        }

        if (self::nameMatchesAny($name, ['fermented chimichurri', 'kimchi', 'sauerkraut', 'miso', 'tempeh'])) {
            return 0.0;
        }

        if (IngredientLibraryCategory::isPrepared($category)) {
            return null;
        }

        return null;
    }

    private static function animalCategoryEstimateMcgPer100g(
        string $ingredientName,
        string $category,
        float $fdcPhylloquinone,
    ): float {
        $name = self::normalize($ingredientName);

        if (str_contains($name, 'cheese') || str_contains(self::normalize($category), 'dairy')) {
            return $fdcPhylloquinone > 0 ? min(15.0, max(2.0, $fdcPhylloquinone)) : 2.0;
        }

        if (str_contains(self::normalize($category), 'fat')) {
            return self::BUTTER_GHEE_MK4_MCG_PER_100G;
        }

        if (str_contains($name, 'egg')) {
            return 15.0;
        }

        if (self::isRedMeatName($name)) {
            return self::RED_MEAT_MK4_MCG_PER_100G;
        }

        if (self::isPorkName($name)) {
            return self::PORK_MK4_MCG_PER_100G;
        }

        if (self::isPoultryName($name)) {
            if (self::nameMatchesAny($name, ['thigh', 'leg', 'drumstick', 'dark meat', 'wing'])) {
                return self::POULTRY_DARK_MEAT_MK4_MCG_PER_100G;
            }

            return self::POULTRY_MEAT_MK4_MCG_PER_100G;
        }

        if (str_contains(self::normalize($category), 'protein')) {
            return self::RED_MEAT_MK4_MCG_PER_100G;
        }

        return 0.0;
    }

    private static function isRedMeatName(string $normalizedName): bool
    {
        return self::nameMatchesAny($normalizedName, [
            'beef',
            'steak',
            'sirloin',
            'brisket',
            'ribeye',
            'chuck',
            'topside',
            'ground beef',
            'veal',
            'lamb',
            'mutton',
            'goat meat',
            'venison',
            'bison',
        ]);
    }

    private static function isPorkName(string $normalizedName): bool
    {
        return self::nameMatchesAny($normalizedName, [
            'pork',
            'bacon',
            'ham',
            'prosciutto',
            'sausage',
            'salami',
        ]);
    }

    private static function isPoultryName(string $normalizedName): bool
    {
        return self::nameMatchesAny($normalizedName, [
            'chicken',
            'turkey',
            'duck',
            'poultry',
            'hen',
        ]);
    }

    private static function normalize(string $value): string
    {
        $collapsed = preg_replace('/\s+/', ' ', trim($value)) ?? trim($value);

        return strtolower($collapsed);
    }

    /**
     * @param  list<string>  $needles
     */
    private static function nameMatchesAny(string $normalizedHaystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle === '') {
                continue;
            }

            if ($normalizedHaystack === $needle || str_contains($normalizedHaystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
