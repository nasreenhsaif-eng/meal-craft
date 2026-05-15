<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Support\IngredientLibraryCategory;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class BaseIngredientService
{
    /**
     * @param  array<int, array{amount_grams: float|int|string}>  $sync
     */
    public static function totalGramsFromSync(array $sync): float
    {
        return MealRecipeAsIngredientSyncService::totalGramsFromSync($sync);
    }

    /**
     * @param  array<int, array{ingredient_id: int, amount_grams: float}>  $componentRows
     * @param  array<string, string|null>|null  $libraryText  When set, may include {@code description} and/or {@code instructions} keys to upsert (empty string → null).
     */
    public function upsert(
        ?Ingredient $existing,
        string $name,
        array $componentRows,
        ?float $finishedWeightGrams = null,
        ?array $libraryText = null,
    ): Ingredient {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException(__('Base ingredient name is required.'));
        }

        $sync = [];
        foreach ($componentRows as $row) {
            $childId = (int) ($row['ingredient_id'] ?? 0);
            $grams = (float) ($row['amount_grams'] ?? 0);
            if ($childId <= 0 || $grams <= 0) {
                continue;
            }
            if ($existing !== null && $childId === (int) $existing->getKey()) {
                throw new InvalidArgumentException(__('A base ingredient cannot include itself as a component.'));
            }
            $sync[$childId] = [
                'amount_grams' => round($grams, 4),
            ];
        }

        if ($sync === []) {
            throw new InvalidArgumentException(__('Add at least one library ingredient with a positive amount.'));
        }

        $calculatorRows = [];
        foreach ($sync as $ingredientId => $pivot) {
            $calculatorRows[] = [
                'ingredient_id' => $ingredientId,
                'amount_grams' => $pivot['amount_grams'],
            ];
        }

        $batchNutrition = RecipeNutritionCalculator::fromRows($calculatorRows);
        $rawGrams = self::totalGramsFromSync($sync);
        $divisorGrams = ($finishedWeightGrams !== null && $finishedWeightGrams > 0)
            ? $finishedWeightGrams
            : $rawGrams;

        if ($divisorGrams <= 0) {
            throw new InvalidArgumentException(__('Total component weight must be greater than zero.'));
        }

        $attrs = $this->ingredientAttributesFromBatch($name, $batchNutrition, $divisorGrams);
        if ($libraryText !== null) {
            foreach (['description', 'instructions'] as $key) {
                if (array_key_exists($key, $libraryText)) {
                    $raw = $libraryText[$key];
                    $trimmed = $raw === null ? '' : trim((string) $raw);
                    $attrs[$key] = $trimmed !== '' ? $trimmed : null;
                }
            }
        }

        return DB::transaction(function () use ($existing, $attrs, $sync): Ingredient {
            if ($existing !== null) {
                $existing->update($attrs);
                $ingredient = $existing->fresh();
            } else {
                $ingredient = Ingredient::query()->create($attrs);
            }

            $ingredient->components()->sync($sync);

            if ($ingredient->source_meal_id !== null) {
                $ingredient->update(['source_meal_id' => null]);
            }

            return $ingredient->fresh(['components']);
        });
    }

    /**
     * @param  array<string, float>  $batchNutrition
     * @return array<string, mixed>
     */
    private function ingredientAttributesFromBatch(string $name, array $batchNutrition, float $divisorGrams): array
    {
        $factor = 100.0 / $divisorGrams;

        $microKeys = [
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
            'vitamin_k',
        ];

        $micros = [];
        foreach ($microKeys as $key) {
            $micros[$key] = round((float) ($batchNutrition[$key] ?? 0) * $factor, 4);
        }

        return [
            'name' => $name,
            'usda_food_category' => IngredientLibraryCategory::BaseIngredient,
            'fdc_id' => null,
            'calories' => round((float) ($batchNutrition['calories'] ?? 0) * $factor, 2),
            'protein' => round((float) ($batchNutrition['protein'] ?? 0) * $factor, 2),
            'carbs' => round((float) ($batchNutrition['carbs'] ?? 0) * $factor, 2),
            'fat' => round((float) ($batchNutrition['fat'] ?? 0) * $factor, 2),
            'b6' => round((float) ($batchNutrition['b6'] ?? 0) * $factor, 4),
            'b9_folate' => round((float) ($batchNutrition['b9_folate'] ?? 0) * $factor, 4),
            'b12' => round((float) ($batchNutrition['b12'] ?? 0) * $factor, 4),
            'iron' => round((float) ($batchNutrition['iron'] ?? 0) * $factor, 4),
            'magnesium' => round((float) ($batchNutrition['magnesium'] ?? 0) * $factor, 4),
            'density' => 1.0,
            'is_verified' => true,
            'micronutrients' => $micros,
            'source_meal_id' => null,
        ];
    }

    public static function isBaseIngredientCategoryInput(string $raw): bool
    {
        return IngredientLibraryCategory::isPrepared($raw);
    }
}
