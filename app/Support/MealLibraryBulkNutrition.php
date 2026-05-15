<?php

namespace App\Support;

use App\Models\Meal;
use App\Services\RecipeNutritionCalculator;

/**
 * Aligns bulk meal persistence with the Meal Library edit UI:
 * ingredient rollup yields batch totals; persisted {@code total_*} columns are per serving.
 */
final class MealLibraryBulkNutrition
{
    /**
     * @param  array<string, float>  $batchNutrition  Full-batch nutrition from ingredient rollup.
     * @param  array{calories?: float, protein?: float, carbs?: float, fat?: float}|null  $csvBatchMacros  Optional explicit batch macros from CSV columns.
     * @return array{
     *     attributes: array<string, mixed>,
     *     nutrition_aggregates_synced: bool,
     *     sickle_cell_program_highlight: bool
     * }
     */
    public static function resolvePersistedNutrition(
        array $batchNutrition,
        bool $isBulk,
        ?float $servingsCount,
        ?array $csvBatchMacros,
        bool $hasResolvedIngredients,
    ): array {
        if (! $isBulk || $servingsCount === null || $servingsCount <= 0) {
            return [
                'attributes' => Meal::nutritionSummaryToPersistedAttributes($batchNutrition),
                'nutrition_aggregates_synced' => $hasResolvedIngredients,
                'sickle_cell_program_highlight' => RecipeNutritionCalculator::sickleCellProgramMealHighlight($batchNutrition),
            ];
        }

        $perServing = self::perServingNutritionForBulk(
            $batchNutrition,
            $servingsCount,
            $csvBatchMacros,
            $hasResolvedIngredients,
        );

        return [
            'attributes' => Meal::nutritionSummaryToPersistedAttributes($perServing),
            'nutrition_aggregates_synced' => false,
            'sickle_cell_program_highlight' => RecipeNutritionCalculator::sickleCellProgramMealHighlight($perServing),
        ];
    }

    /**
     * @param  array<string, mixed>  $optionalAttributes
     * @return array{calories?: float, protein?: float, carbs?: float, fat?: float}|null
     */
    public static function batchMacrosFromOptionalAttributes(array $optionalAttributes): ?array
    {
        $keys = ['batch_calories', 'batch_protein', 'batch_carbs', 'batch_fat'];
        $hasAny = false;
        $macros = [];

        foreach ($keys as $key) {
            if (! array_key_exists($key, $optionalAttributes)) {
                continue;
            }
            $hasAny = true;
            $macros[str_replace('batch_', '', $key)] = (float) $optionalAttributes[$key];
        }

        return $hasAny ? $macros : null;
    }

    /**
     * @param  array<string, mixed>  $optionalAttributes
     * @return array<string, mixed>
     */
    public static function withoutBatchMacroKeys(array $optionalAttributes): array
    {
        return array_diff_key($optionalAttributes, array_flip([
            'batch_calories',
            'batch_protein',
            'batch_carbs',
            'batch_fat',
        ]));
    }

    /**
     * @param  array<string, float>  $batchNutrition
     * @param  array{calories?: float, protein?: float, carbs?: float, fat?: float}|null  $csvBatchMacros
     * @return array<string, float>
     */
    private static function perServingNutritionForBulk(
        array $batchNutrition,
        float $servingsCount,
        ?array $csvBatchMacros,
        bool $hasResolvedIngredients,
    ): array {
        $factor = 1 / $servingsCount;

        if ($hasResolvedIngredients && self::nutritionHasMeaningfulCalories($batchNutrition)) {
            return self::scaleNutrition($batchNutrition, $factor);
        }

        if ($csvBatchMacros !== null && self::nutritionHasMeaningfulCalories($csvBatchMacros)) {
            return self::scaleNutrition(self::expandMacroShape($csvBatchMacros), $factor);
        }

        if (self::nutritionHasMeaningfulCalories($batchNutrition)) {
            return self::scaleNutrition($batchNutrition, $factor);
        }

        return self::expandMacroShape($csvBatchMacros ?? []);
    }

    /**
     * @param  array{calories?: float, protein?: float, carbs?: float, fat?: float}  $macros
     * @return array<string, float>
     */
    private static function expandMacroShape(array $macros): array
    {
        return [
            'calories' => (float) ($macros['calories'] ?? 0),
            'protein' => (float) ($macros['protein'] ?? 0),
            'carbs' => (float) ($macros['carbs'] ?? 0),
            'fat' => (float) ($macros['fat'] ?? 0),
        ];
    }

    /**
     * @param  array<string, float>  $nutrition
     * @return array<string, float>
     */
    private static function scaleNutrition(array $nutrition, float $factor): array
    {
        if (! is_finite($factor) || $factor <= 0) {
            return [];
        }

        $out = [];
        foreach ($nutrition as $key => $value) {
            if (! is_numeric($value)) {
                continue;
            }
            $out[$key] = round((float) $value * $factor, 4);
        }

        return $out;
    }

    /**
     * @param  array<string, float>  $nutrition
     */
    private static function nutritionHasMeaningfulCalories(array $nutrition): bool
    {
        $calories = (float) ($nutrition['calories'] ?? 0);

        return is_finite($calories) && $calories > 0;
    }
}
