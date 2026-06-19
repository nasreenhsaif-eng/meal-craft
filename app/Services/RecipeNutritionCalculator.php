<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Meal;
use App\Support\SickleCellNutrientRdi;

final class RecipeNutritionCalculator
{
    /**
     * @param  array<int, array{ingredient_id: int|null, amount?: float|int|string|null, unit?: string|null, amount_grams?: float|int|string|null}>  $rows
     * @return array<string, float>
     */
    public static function fromRows(array $rows): array
    {
        $ingredientIds = collect($rows)
            ->map(fn (array $r): ?int => isset($r['ingredient_id']) && is_numeric($r['ingredient_id']) ? (int) $r['ingredient_id'] : null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $byId = Ingredient::query()
            ->whereIn('id', $ingredientIds)
            ->with(['components'])
            ->get()
            ->keyBy('id');

        $nutrition = [
            'calories' => 0.0,
            'protein' => 0.0,
            'carbs' => 0.0,
            'fat' => 0.0,
            'b6' => 0.0,
            'b9_folate' => 0.0,
            'b12' => 0.0,
            'iron' => 0.0,
            'magnesium' => 0.0,
            'fiber' => 0.0,
            'sugar' => 0.0,
            'calcium' => 0.0,
            'potassium' => 0.0,
            'sodium' => 0.0,
            'zinc' => 0.0,
            'vitamin_c' => 0.0,
            'vitamin_a' => 0.0,
            'vitamin_e' => 0.0,
            'vitamin_d' => 0.0,
            'vitamin_k2' => 0.0,
        ];

        $jsonKeys = ['fiber', 'sugar', 'calcium', 'potassium', 'sodium', 'zinc', 'vitamin_c', 'vitamin_a', 'vitamin_e', 'vitamin_d', 'vitamin_k2'];

        foreach ($rows as $row) {
            $ingredientId = isset($row['ingredient_id']) && is_numeric($row['ingredient_id']) ? (int) $row['ingredient_id'] : null;

            if ($ingredientId === null) {
                continue;
            }

            /** @var Ingredient|null $ingredient */
            $ingredient = $byId->get($ingredientId);

            if ($ingredient === null) {
                continue;
            }

            $grams = self::resolvedGramsForRow($row, $ingredient);

            if ($grams <= 0) {
                continue;
            }

            $factor = $grams / 100.0;
            $per100 = self::per100gNutritionForIngredient($ingredient);

            foreach ($nutrition as $key => $_value) {
                $nutrition[$key] += ((float) ($per100[$key] ?? 0)) * $factor;
            }
        }

        foreach ($nutrition as $key => $value) {
            $nutrition[$key] = round($value, 2);
        }

        return $nutrition;
    }

    /**
     * Whole-meal nutrition from ingredient pivots (amount + unit when set, else grams).
     */
    public static function fromMeal(Meal $meal): array
    {
        $meal->loadMissing('ingredients');

        $rows = $meal->ingredients->map(function (Ingredient $ingredient): array {
            $pivot = $ingredient->pivot;
            $pivotAmount = $pivot->amount;
            $hasDisplayAmount = $pivotAmount !== null && $pivotAmount !== '' && (float) $pivotAmount > 0;
            $unitRaw = $pivot->unit ?? '';

            if ($hasDisplayAmount && is_string($unitRaw) && $unitRaw !== '') {
                return [
                    'ingredient_id' => $ingredient->id,
                    'amount' => (float) $pivotAmount,
                    'unit' => $unitRaw,
                ];
            }

            return [
                'ingredient_id' => $ingredient->id,
                'amount_grams' => (float) ($pivot->amount_grams ?? 0),
            ];
        })->all();

        return self::fromRows($rows);
    }

    /**
     * Per-serving High Source flags (≥20% RDI). See {@see SickleCellNutrientRdi}.
     *
     * @param  array<string, float>  $nutrition
     * @return array<string, bool>
     */
    public static function sickleCellHighlights(array $nutrition): array
    {
        return SickleCellNutrientRdi::highlightFlags($nutrition);
    }

    /**
     * @param  array<string, float>  $nutrition  Per-serving totals.
     */
    public static function sickleCellProgramMealHighlight(array $nutrition): bool
    {
        return SickleCellNutrientRdi::hasAnyHighlight($nutrition);
    }

    /**
     * Per-100 g nutrition for a library row. Prepared base ingredients prefer live rollup from
     * {@see Ingredient::components()} when stored parent macros are empty or components exist.
     *
     * @return array<string, float>
     */
    public static function per100gNutritionForIngredient(Ingredient $ingredient): array
    {
        $ingredient->loadMissing('components');

        if ($ingredient->isPreparedBaseIngredient() && $ingredient->components->isNotEmpty()) {
            $fromFormulation = self::per100gFromComponentFormulation($ingredient);
            if (self::per100gHasMeaningfulCalories($fromFormulation)) {
                return $fromFormulation;
            }
        }

        return self::per100gFromStoredColumns($ingredient);
    }

    /**
     * @return array<string, float>
     */
    private static function per100gFromStoredColumns(Ingredient $ingredient): array
    {
        $micros = is_array($ingredient->micronutrients) ? $ingredient->micronutrients : [];

        $nutrition = [
            'calories' => (float) $ingredient->calories,
            'protein' => (float) $ingredient->protein,
            'carbs' => (float) $ingredient->carbs,
            'fat' => (float) $ingredient->fat,
            'b6' => (float) ($ingredient->b6 ?? 0),
            'b9_folate' => (float) ($ingredient->b9_folate ?? 0),
            'b12' => (float) ($ingredient->b12 ?? 0),
            'iron' => (float) ($ingredient->iron ?? 0),
            'magnesium' => (float) ($ingredient->magnesium ?? 0),
            'fiber' => self::micronutrientPer100g($micros, 'fiber'),
            'sugar' => self::micronutrientPer100g($micros, 'sugar'),
            'calcium' => self::micronutrientPer100g($micros, 'calcium'),
            'potassium' => self::micronutrientPer100g($micros, 'potassium'),
            'sodium' => self::micronutrientPer100g($micros, 'sodium'),
            'zinc' => self::micronutrientPer100g($micros, 'zinc'),
            'vitamin_c' => self::micronutrientPer100g($micros, 'vitamin_c'),
            'vitamin_a' => self::micronutrientPer100g($micros, 'vitamin_a'),
            'vitamin_e' => self::micronutrientPer100g($micros, 'vitamin_e'),
            'vitamin_d' => self::micronutrientPer100g($micros, 'vitamin_d'),
            'vitamin_k2' => self::micronutrientPer100g($micros, 'vitamin_k2'),
        ];

        foreach ($nutrition as $key => $value) {
            $nutrition[$key] = round($value, 4);
        }

        return $nutrition;
    }

    /**
     * @return array<string, float>
     */
    private static function per100gFromComponentFormulation(Ingredient $parent): array
    {
        $rows = [];
        $totalGrams = 0.0;

        foreach ($parent->components as $child) {
            $grams = (float) ($child->pivot->amount_grams ?? 0);
            if ($grams <= 0) {
                continue;
            }

            $rows[] = [
                'ingredient_id' => (int) $child->getKey(),
                'amount_grams' => $grams,
            ];
            $totalGrams += $grams;
        }

        if ($rows === [] || $totalGrams <= 0) {
            return self::per100gFromStoredColumns($parent);
        }

        $batch = self::fromRows($rows);
        $factor = 100.0 / $totalGrams;

        return self::scaleNutritionValues($batch, $factor);
    }

    /**
     * @param  array<string, float>  $nutrition
     * @return array<string, float>
     */
    private static function scaleNutritionValues(array $nutrition, float $factor): array
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
     * @param  array<string, float>  $per100
     */
    private static function per100gHasMeaningfulCalories(array $per100): bool
    {
        $calories = (float) ($per100['calories'] ?? 0);

        return is_finite($calories) && $calories > 0;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function resolvedGramsForRow(array $row, Ingredient $ingredient): float
    {
        $density = (float) ($ingredient->getAttribute('density') ?? 1.0);

        $hasAmountUnit = array_key_exists('amount', $row)
            && array_key_exists('unit', $row)
            && $row['unit'] !== null
            && (string) $row['unit'] !== ''
            && is_numeric($row['amount'] ?? null);

        if ($hasAmountUnit) {
            return RecipeIngredientUnitConverter::toGrams(
                max(0.0, (float) $row['amount']),
                (string) $row['unit'],
                $density
            );
        }

        if (isset($row['amount_grams']) && is_numeric($row['amount_grams'])) {
            return max(0.0, (float) $row['amount_grams']);
        }

        return 0.0;
    }

    private static function micronutrientPer100g(array $micronutrients, string $key): float
    {
        $v = $micronutrients[$key] ?? 0;

        return is_numeric($v) ? (float) $v : 0.0;
    }
}
