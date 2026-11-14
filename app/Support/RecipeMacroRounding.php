<?php

namespace App\Support;

/**
 * Single-stage rounding and Atwater (4-9-4) calorie audit for recipe macro totals.
 *
 * Aggregation must sum unrounded floats first; this class only finalizes the dish total.
 */
final class RecipeMacroRounding
{
    public const PROTEIN_KCAL_PER_G = 4.0;

    public const CARB_KCAL_PER_G = 4.0;

    public const FAT_KCAL_PER_G = 9.0;

    /** Max allowed |database calories − Atwater(macros)| before a recalculation sweep. */
    public const CALORIE_DRIFT_TOLERANCE_KCAL = 5.0;

    /** @var list<string> */
    private const MACRO_KEYS_ONE_DECIMAL = [
        'protein',
        'carbs',
        'fat',
    ];

    /** @var list<string> */
    private const MICRO_KEYS_FOUR_DECIMAL = [
        'b6',
        'b9_folate',
        'b12',
        'iron',
        'magnesium',
        'fiber',
        'sugar',
        'calcium',
        'potassium',
        'sodium',
        'zinc',
        'vitamin_c',
        'vitamin_a',
        'vitamin_e',
        'vitamin_d',
        'vitamin_k2',
    ];

    /**
     * Atwater energy from finalized (or raw) macro grams: 4P + 4C + 9F.
     */
    public static function atwaterCalories(float $proteinG, float $carbsG, float $fatG): float
    {
        return ($proteinG * self::PROTEIN_KCAL_PER_G)
            + ($carbsG * self::CARB_KCAL_PER_G)
            + ($fatG * self::FAT_KCAL_PER_G);
    }

    /**
     * @param  array<string, float|int>  $nutrition
     */
    public static function atwaterCaloriesFromNutrition(array $nutrition): float
    {
        return self::atwaterCalories(
            (float) ($nutrition['protein'] ?? 0),
            (float) ($nutrition['carbs'] ?? 0),
            (float) ($nutrition['fat'] ?? 0),
        );
    }

    /**
     * Absolute drift between aggregate calories and Atwater energy from P/C/F.
     *
     * @param  array<string, float|int>  $nutrition
     */
    public static function calorieDriftFromAtwater(array $nutrition): float
    {
        $aggregate = (float) ($nutrition['calories'] ?? 0);
        $atwater = self::atwaterCaloriesFromNutrition($nutrition);

        return abs($aggregate - $atwater);
    }

    /**
     * @param  array<string, float|int>  $nutrition
     */
    public static function caloriesNeedRecalculationSweep(array $nutrition): bool
    {
        return self::calorieDriftFromAtwater($nutrition) > self::CALORIE_DRIFT_TOLERANCE_KCAL;
    }

    /**
     * Round finalized dish totals only:
     * - calories → nearest integer of the unrounded calorie aggregate
     * - protein / carbs / fat → one decimal place
     * - micros → four decimal places
     *
     * Cross-check: when aggregate calories drift more than
     * {@see self::CALORIE_DRIFT_TOLERANCE_KCAL} from Atwater(P,C,F), the result is
     * flagged via {@see calories_recalculated_from_atwater} so callers can queue a
     * recalculation sweep. Calories stay on the database aggregate unless
     * {@see $applyAtwaterSweep} is true.
     *
     * @param  array<string, float|int>  $nutrition  Unrounded aggregate.
     * @return array{
     *     nutrition: array<string, float>,
     *     calories_recalculated_from_atwater: bool,
     *     calorie_drift_kcal: float
     * }
     */
    public static function finalizeWithAudit(array $nutrition, bool $applyAtwaterSweep = false): array
    {
        $out = [];

        foreach ($nutrition as $key => $value) {
            if (! is_numeric($value)) {
                continue;
            }
            $out[$key] = (float) $value;
        }

        foreach (self::MACRO_KEYS_ONE_DECIMAL as $key) {
            $out[$key] = round((float) ($out[$key] ?? 0), 1);
        }

        foreach (self::MICRO_KEYS_FOUR_DECIMAL as $key) {
            if (! array_key_exists($key, $out)) {
                continue;
            }
            $out[$key] = round((float) $out[$key], 4);
        }

        $aggregateCalories = (float) ($out['calories'] ?? 0);
        $atwater = self::atwaterCalories(
            (float) ($out['protein'] ?? 0),
            (float) ($out['carbs'] ?? 0),
            (float) ($out['fat'] ?? 0),
        );
        $drift = abs($aggregateCalories - $atwater);
        $needsSweep = $drift > self::CALORIE_DRIFT_TOLERANCE_KCAL;

        if ($needsSweep && $applyAtwaterSweep) {
            $out['calories'] = (float) (int) round($atwater);
        } else {
            $out['calories'] = (float) (int) round($aggregateCalories);
        }

        return [
            'nutrition' => $out,
            'calories_recalculated_from_atwater' => $needsSweep && $applyAtwaterSweep,
            'calorie_drift_kcal' => round($drift, 2),
            'needs_calorie_recalculation_sweep' => $needsSweep,
        ];
    }

    /**
     * @param  array<string, float|int>  $nutrition
     * @return array<string, float>
     */
    public static function finalize(array $nutrition, bool $applyAtwaterSweep = false): array
    {
        return self::finalizeWithAudit($nutrition, $applyAtwaterSweep)['nutrition'];
    }
}
