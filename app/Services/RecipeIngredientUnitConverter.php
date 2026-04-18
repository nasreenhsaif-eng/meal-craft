<?php

namespace App\Services;

use App\Enums\RecipeAmountUnit;

/**
 * Converts recipe line amounts to grams for nutrition scaling (per 100 g in the library).
 *
 * - {@see RecipeAmountUnit::Grams} and {@see RecipeAmountUnit::Kilograms}: direct mass; density is ignored.
 * - Volume units (ml, ltr, tsp, tbsp, cup): volume × ingredient density (g/ml) = mass.
 *
 * Kitchen volumes use US customary equivalents: 1 tsp = 5 ml, 1 tbsp = 15 ml, 1 cup = 240 ml (≈ 240 g at 1.0 g/ml).
 */
final class RecipeIngredientUnitConverter
{
    private const MLS_PER_TSP = 5.0;

    private const MLS_PER_TBSP = 15.0;

    private const MLS_PER_CUP = 240.0;

    public static function toGrams(float $amount, string|RecipeAmountUnit $unit, float $densityGramsPerMl): float
    {
        $amount = max(0.0, $amount);

        $density = $densityGramsPerMl > 0 ? $densityGramsPerMl : 1.0;

        $u = $unit instanceof RecipeAmountUnit ? $unit->value : strtolower(trim($unit));

        return match ($u) {
            RecipeAmountUnit::Grams->value => $amount,
            RecipeAmountUnit::Kilograms->value => $amount * 1000.0,
            RecipeAmountUnit::Milliliters->value => $amount * $density,
            RecipeAmountUnit::Liters->value => $amount * 1000.0 * $density,
            RecipeAmountUnit::Teaspoon->value => $amount * self::MLS_PER_TSP * $density,
            RecipeAmountUnit::Tablespoon->value => $amount * self::MLS_PER_TBSP * $density,
            RecipeAmountUnit::Cup->value => $amount * self::MLS_PER_CUP * $density,
            default => $amount,
        };
    }

    /**
     * Human hint for the conversion (e.g. "1 cup (240 ml) × 1.00 g/ml").
     *
     * @return array{line1: string, line2: string|null}
     */
    public static function explain(float $amount, string|RecipeAmountUnit $unit, float $densityGramsPerMl): array
    {
        $density = $densityGramsPerMl > 0 ? $densityGramsPerMl : 1.0;
        $enum = $unit instanceof RecipeAmountUnit ? $unit : RecipeAmountUnit::tryFrom(strtolower(trim((string) $unit)));

        if ($enum === null) {
            return [
                'line1' => (string) round(self::toGrams($amount, $unit, $density), 2).' g',
                'line2' => null,
            ];
        }

        if (! $enum->usesDensity()) {
            $grams = self::toGrams($amount, $enum, $density);

            return [
                'line1' => __(':amount :unit = :g g', [
                    'amount' => rtrim(rtrim(number_format($amount, 4, '.', ''), '0'), '.') ?: '0',
                    'unit' => $enum->value,
                    'g' => number_format($grams, 2, '.', ''),
                ]),
                'line2' => null,
            ];
        }

        $ml = match ($enum) {
            RecipeAmountUnit::Milliliters => $amount,
            RecipeAmountUnit::Liters => $amount * 1000.0,
            RecipeAmountUnit::Teaspoon => $amount * self::MLS_PER_TSP,
            RecipeAmountUnit::Tablespoon => $amount * self::MLS_PER_TBSP,
            RecipeAmountUnit::Cup => $amount * self::MLS_PER_CUP,
            default => 0.0,
        };

        $grams = self::toGrams($amount, $enum, $density);

        return [
            'line1' => __('Calculated weight: :g g', ['g' => number_format($grams, 2, '.', '')]),
            'line2' => __(':amount :unit ≈ :ml ml × :density g/ml', [
                'amount' => rtrim(rtrim(number_format($amount, 4, '.', ''), '0'), '.') ?: '0',
                'unit' => $enum->value,
                'ml' => number_format($ml, 2, '.', ''),
                'density' => number_format($density, 3, '.', ''),
            ]),
        ];
    }
}
