<?php

namespace App\Support;

/**
 * Raw vs cooked chicken breast weights and protein (USDA SR Legacy 171077).
 *
 * Library convention:
 * - {@see ChickenBreastYield::RAW_INGREDIENT_NAME} amounts in meals are raw prep weight (grams).
 * - Chicken (Base) recipe components are raw; finished_weight_grams is the cooked batch yield.
 * - Chicken (Base) amounts in meals are cooked portion grams.
 */
final class ChickenBreastYield
{
    public const RAW_INGREDIENT_NAME = 'Chicken Breast';

    /** Per 100 g raw boneless skinless breast (USDA FDC 171077). */
    public const RAW_CALORIES_PER_100G = 120.0;

    public const RAW_PROTEIN_PER_100G = 23.0;

    public const RAW_FAT_PER_100G = 2.6;

    /** Moisture loss when grilled or baked to 74 °C (typical breast yield). */
    public const RAW_TO_COOKED_WEIGHT_RATIO = 0.75;

    public static function cookedGramsFromRaw(float $rawGrams): float
    {
        return round($rawGrams * self::RAW_TO_COOKED_WEIGHT_RATIO, 4);
    }

    public static function rawGramsFromCooked(float $cookedGrams): float
    {
        if (self::RAW_TO_COOKED_WEIGHT_RATIO <= 0) {
            return 0.0;
        }

        return round($cookedGrams / self::RAW_TO_COOKED_WEIGHT_RATIO, 4);
    }

    /** Raw grams needed to deliver a target amount of chicken protein (conserved through cooking). */
    public static function rawGramsForProtein(float $proteinGrams): float
    {
        if (self::RAW_PROTEIN_PER_100G <= 0) {
            return 0.0;
        }

        return round($proteinGrams / self::RAW_PROTEIN_PER_100G * 100, 4);
    }

    /** Implied protein per 100 g cooked plain breast when only water is lost. */
    public static function cookedProteinPer100g(): float
    {
        if (self::RAW_TO_COOKED_WEIGHT_RATIO <= 0) {
            return 0.0;
        }

        return round(self::RAW_PROTEIN_PER_100G / self::RAW_TO_COOKED_WEIGHT_RATIO, 2);
    }

    /**
     * Estimated total cooked batch weight for a marinated chicken base recipe.
     * Assumes only chicken moisture is lost; marinade solids and fats remain in the yield.
     */
    public static function estimateMarinatedFinishedWeight(float $rawChickenGrams, float $retainedNonChickenGrams): float
    {
        return round(self::cookedGramsFromRaw($rawChickenGrams) + max(0, $retainedNonChickenGrams), 1);
    }

    /**
     * @return array{
     *     raw_grams: float,
     *     cooked_plain_grams: float,
     *     protein_grams: float,
     *     calories: float,
     * }
     */
    public static function rawPortionSummary(float $rawGrams): array
    {
        $protein = round($rawGrams / 100 * self::RAW_PROTEIN_PER_100G, 2);
        $calories = round($rawGrams / 100 * self::RAW_CALORIES_PER_100G, 2);

        return [
            'raw_grams' => round($rawGrams, 1),
            'cooked_plain_grams' => self::cookedGramsFromRaw($rawGrams),
            'protein_grams' => $protein,
            'calories' => $calories,
        ];
    }
}
