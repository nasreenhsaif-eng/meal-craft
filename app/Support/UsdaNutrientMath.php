<?php

namespace App\Support;

/**
 * USDA FoodData Central nutrient extraction and scaling (per 100 g → portion).
 *
 * Full-format foods include nutrient.id (FDC) and nutrient.number (NDB-style). Some exports use attrId aligned with FDC nutrient id.
 * Search payloads often use nutrientId + value. Rows without amount/value are structural headers and are ignored so they do not
 * overwrite real nutrient amounts with zero.
 */
final class UsdaNutrientMath
{
    /** FDC nutrient.id — Vitamin A, RAE (µg) */
    public const FDC_VITAMIN_A_RAE = '1106';

    /** FDC nutrient.id — Vitamin B-6 (mg) */
    public const FDC_VITAMIN_B6 = '1175';

    /** FDC nutrient.id — Vitamin B-12 (µg) */
    public const FDC_VITAMIN_B12 = '1178';

    /** FDC nutrient.id — Calcium (mg) */
    public const FDC_CALCIUM = '1087';

    /** FDC nutrient.id — Folate, total (µg) */
    public const FDC_FOLATE = '1177';

    /** FDC nutrient.id — Iron, Fe (mg) */
    public const FDC_IRON = '1089';

    /** FDC nutrient.id — Magnesium, Mg (mg) */
    public const FDC_MAGNESIUM = '1090';

    /** FDC nutrient.id — Potassium, K (mg) */
    public const FDC_POTASSIUM = '1092';

    /** FDC nutrient.id — Zinc, Zn (mg) */
    public const FDC_ZINC = '1095';

    /** FDC nutrient.id — Vitamin E (alpha-tocopherol) (mg) */
    public const FDC_VITAMIN_E = '1109';

    /** Meal Craft Analysis / library: per 100 g — folate above this supports sickle-cell planning labeling. */
    public const SICKLE_CELL_PLANNING_FOLATE_PER_100G_MIN_UG = 40.0;

    /** Meal Craft Analysis / library: per 100 g — vitamin B12 above this supports sickle-cell planning labeling. */
    public const SICKLE_CELL_PLANNING_B12_PER_100G_MIN_UG = 1.0;

    /**
     * @param  array<int, array<string, mixed>>  $foodNutrients
     * @return array<string, float> Keys are NDB-style numbers and stringified FDC nutrient ids (and attrId when present)
     */
    public static function mapByNutrientNumber(array $foodNutrients): array
    {
        $byNumber = [];

        foreach ($foodNutrients as $n) {
            if (! is_array($n)) {
                continue;
            }

            $amount = self::explicitAmountFromFoodNutrientRow($n);

            if ($amount === null) {
                continue;
            }

            if (isset($n['nutrientId'])) {
                $byNumber[(string) $n['nutrientId']] = $amount;
            }

            if (isset($n['nutrientNumber'])) {
                $byNumber[(string) $n['nutrientNumber']] = $amount;
            }

            if (isset($n['attrId'])) {
                $byNumber[(string) $n['attrId']] = $amount;
            }

            if (isset($n['nutrient']) && is_array($n['nutrient'])) {
                if (isset($n['nutrient']['number'])) {
                    $byNumber[(string) $n['nutrient']['number']] = $amount;
                }

                if (isset($n['nutrient']['id'])) {
                    $byNumber[(string) $n['nutrient']['id']] = $amount;
                }

                if (isset($n['nutrient']['attrId'])) {
                    $byNumber[(string) $n['nutrient']['attrId']] = $amount;
                }
            }
        }

        return $byNumber;
    }

    /**
     * @param  array<string, mixed>  $n
     */
    private static function explicitAmountFromFoodNutrientRow(array $n): ?float
    {
        if (array_key_exists('amount', $n)) {
            return (float) $n['amount'];
        }

        if (array_key_exists('value', $n)) {
            return (float) $n['value'];
        }

        return null;
    }

    /**
     * @param  array<string, float>  $byNumber
     */
    public static function valueForNutrientKeys(array $byNumber, string ...$keys): float
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $byNumber)) {
                return (float) $byNumber[$key];
            }
        }

        return 0.0;
    }

    /**
     * FDC nutrient ids stored on verified library rows (amounts per 100 g).
     *
     * @return list<string>
     */
    public static function fdcLibraryTrackedNutrientIds(): array
    {
        return [
            self::FDC_VITAMIN_B6,
            self::FDC_FOLATE,
            self::FDC_VITAMIN_B12,
            self::FDC_VITAMIN_A_RAE,
            self::FDC_CALCIUM,
        ];
    }

    /**
     * Key micronutrients for Meal Craft library persistence (string FDC id => amount per 100 g).
     *
     * @param  array<string, float>  $byNumber
     * @return array<string, float>
     */
    public static function fdcKeyNutrientsPer100gFromMap(array $byNumber): array
    {
        return [
            self::FDC_VITAMIN_B6 => self::valueForNutrientKeys($byNumber, self::FDC_VITAMIN_B6, '415'),
            self::FDC_FOLATE => self::valueForNutrientKeys($byNumber, self::FDC_FOLATE, '417'),
            self::FDC_VITAMIN_B12 => self::valueForNutrientKeys($byNumber, self::FDC_VITAMIN_B12, '418'),
            self::FDC_VITAMIN_A_RAE => self::valueForNutrientKeys($byNumber, self::FDC_VITAMIN_A_RAE, '318', '320'),
            self::FDC_CALCIUM => self::valueForNutrientKeys($byNumber, self::FDC_CALCIUM, '301'),
        ];
    }

    /**
     * Omega-3 (g per 100g): prefer total n-3 (1316), else sum common individual n-3 fatty acids.
     *
     * @param  array<string, float>  $byNumber
     */
    public static function omega3GramsPer100g(array $byNumber): float
    {
        $total = (float) ($byNumber['1316'] ?? 0);

        if ($total > 0) {
            return $total;
        }

        return (float) ($byNumber['1270'] ?? 0)
            + (float) ($byNumber['1278'] ?? 0)
            + (float) ($byNumber['1273'] ?? 0)
            + (float) ($byNumber['1404'] ?? 0);
    }

    /**
     * @param  array<string, float>  $byNumber
     * @return array<string, float>
     */
    public static function macrosMicronutrientsPer100g(array $byNumber): array
    {
        return [
            'calories' => (float) ($byNumber['1008'] ?? $byNumber['208'] ?? $byNumber['957'] ?? 0),
            'protein_g' => (float) ($byNumber['1003'] ?? $byNumber['203'] ?? 0),
            'fat_g' => (float) ($byNumber['1004'] ?? $byNumber['204'] ?? 0),
            'carbs_g' => (float) ($byNumber['1005'] ?? $byNumber['205'] ?? 0),
            'fiber_g' => (float) ($byNumber['291'] ?? 0),
            'omega3_g' => self::omega3GramsPer100g($byNumber),
            'vitamin_a_rae_mcg' => self::valueForNutrientKeys($byNumber, self::FDC_VITAMIN_A_RAE, '318', '320'),
            'vitamin_b6_mg' => self::valueForNutrientKeys($byNumber, self::FDC_VITAMIN_B6, '415'),
            'vitamin_b12_mcg' => self::valueForNutrientKeys($byNumber, self::FDC_VITAMIN_B12, '418'),
            'folate_mcg' => self::valueForNutrientKeys($byNumber, self::FDC_FOLATE, '417'),
            'vitamin_c_mg' => (float) ($byNumber['401'] ?? 0),
            'calcium_mg' => self::valueForNutrientKeys($byNumber, self::FDC_CALCIUM, '301'),
            'iron_mg' => self::valueForNutrientKeys($byNumber, self::FDC_IRON, '303'),
            'potassium_mg' => self::valueForNutrientKeys($byNumber, self::FDC_POTASSIUM, '306'),
            'magnesium_mg' => self::valueForNutrientKeys($byNumber, self::FDC_MAGNESIUM, '304'),
            'zinc_mg' => self::valueForNutrientKeys($byNumber, self::FDC_ZINC, '309'),
            'vitamin_e_mg' => self::valueForNutrientKeys($byNumber, self::FDC_VITAMIN_E, '323'),
        ];
    }

    /**
     * True when full-format foodNutrients lack usable B6 or B12 (FDC 1175 / 1178 or NDB 415 / 418).
     *
     * @param  array<string, mixed>  $foodDetail Full /foods payload item
     */
    public static function fullFoodDetailLacksB6OrB12(array $foodDetail): bool
    {
        $nutrients = $foodDetail['foodNutrients'] ?? null;

        if (! is_array($nutrients) || $nutrients === []) {
            return true;
        }

        $byNumber = self::mapByNutrientNumber($nutrients);
        $b6 = self::valueForNutrientKeys($byNumber, self::FDC_VITAMIN_B6, '415');
        $b12 = self::valueForNutrientKeys($byNumber, self::FDC_VITAMIN_B12, '418');

        return $b6 <= 0.0 || $b12 <= 0.0;
    }

    /**
     * True when vitamin B12 or total folate is missing or zero (FDC 1178 / 1177 or NDB 418 / 417).
     *
     * @param  array<string, mixed>  $foodDetail Full /foods payload item
     */
    public static function fullFoodDetailLacksB12OrFolate(array $foodDetail): bool
    {
        $nutrients = $foodDetail['foodNutrients'] ?? null;

        if (! is_array($nutrients) || $nutrients === []) {
            return true;
        }

        $byNumber = self::mapByNutrientNumber($nutrients);
        $b12 = self::valueForNutrientKeys($byNumber, self::FDC_VITAMIN_B12, '418');
        $folate = self::valueForNutrientKeys($byNumber, self::FDC_FOLATE, '417');

        return $b12 <= 0.0 || $folate <= 0.0;
    }

    /**
     * True when B6, B12, or total folate is missing or zero (FDC 1175 / 1178 / 1177 or NDB 415 / 418 / 417).
     *
     * @param  array<string, mixed>  $foodDetail Full /foods payload item
     */
    public static function fullFoodDetailLacksPositiveB6B12AndFolate(array $foodDetail): bool
    {
        $nutrients = $foodDetail['foodNutrients'] ?? null;

        if (! is_array($nutrients) || $nutrients === []) {
            return true;
        }

        $byNumber = self::mapByNutrientNumber($nutrients);
        $b6 = self::valueForNutrientKeys($byNumber, self::FDC_VITAMIN_B6, '415');
        $b12 = self::valueForNutrientKeys($byNumber, self::FDC_VITAMIN_B12, '418');
        $folate = self::valueForNutrientKeys($byNumber, self::FDC_FOLATE, '417');

        return $b6 <= 0.0 || $b12 <= 0.0 || $folate <= 0.0;
    }

    /**
     * Foundation foods sometimes report 0 for B-vitamins / folate while SR Legacy has authoritative values — trigger SR fallback.
     *
     * @param  array<string, mixed>  $foodDetail Full /foods payload item
     */
    public static function foundationDetailNeedsSrLegacyMicronutrientFallback(array $foodDetail): bool
    {
        if (strcasecmp(trim((string) ($foodDetail['dataType'] ?? '')), 'Foundation') !== 0) {
            return false;
        }

        return self::fullFoodDetailLacksPositiveB6B12AndFolate($foodDetail);
    }

    /**
     * Scale USDA “per 100 g” nutrient values to the user portion: value_portion = value_per_100g × (portion_g / 100).
     *
     * @param  array<string, float>  $per100
     * @return array<string, float>
     */
    public static function scaleToPortion(array $per100, float $quantityGrams): array
    {
        if ($quantityGrams <= 0) {
            return array_fill_keys(array_keys($per100), 0.0);
        }

        $factor = $quantityGrams / 100.0;
        $out = [];

        foreach ($per100 as $k => $v) {
            $out[$k] = round($v * $factor, 3);
        }

        return $out;
    }
}
