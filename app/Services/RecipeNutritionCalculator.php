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
            'vitamin_k' => 0.0,
        ];

        $jsonKeys = ['fiber', 'sugar', 'calcium', 'potassium', 'sodium', 'zinc', 'vitamin_c', 'vitamin_a', 'vitamin_e', 'vitamin_d', 'vitamin_k'];

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
            $micros = is_array($ingredient->micronutrients) ? $ingredient->micronutrients : [];

            $nutrition['calories'] += ((float) $ingredient->calories) * $factor;
            $nutrition['protein'] += ((float) $ingredient->protein) * $factor;
            $nutrition['carbs'] += ((float) $ingredient->carbs) * $factor;
            $nutrition['fat'] += ((float) $ingredient->fat) * $factor;
            $nutrition['b6'] += ((float) ($ingredient->b6 ?? 0)) * $factor;
            $nutrition['b9_folate'] += ((float) ($ingredient->b9_folate ?? 0)) * $factor;
            $nutrition['b12'] += ((float) ($ingredient->b12 ?? 0)) * $factor;
            $nutrition['iron'] += ((float) ($ingredient->iron ?? 0)) * $factor;
            $nutrition['magnesium'] += ((float) ($ingredient->magnesium ?? 0)) * $factor;

            foreach ($jsonKeys as $key) {
                $nutrition[$key] += self::micronutrientPer100g($micros, $key) * $factor;
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
