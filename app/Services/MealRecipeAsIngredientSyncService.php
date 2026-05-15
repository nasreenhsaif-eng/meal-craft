<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Meal;
use App\Support\IngredientLibraryCategory;
use Illuminate\Support\Facades\Log;

final class MealRecipeAsIngredientSyncService
{
    /**
     * @param  array<int, array{amount_grams: float|int|string}>  $sync
     */
    public static function totalGramsFromSync(array $sync): float
    {
        $sum = 0.0;

        foreach ($sync as $row) {
            $g = $row['amount_grams'] ?? 0;
            $sum += is_numeric($g) ? (float) $g : 0.0;
        }

        return $sum;
    }

    /**
     * @param  array<string, float|int>  $nutrition  Calculator-shaped keys (matches RecipeNutritionCalculator output)
     * @return array<string, mixed>
     */
    public static function ingredientAttributesFromMeal(Meal $meal, array $nutrition, float $divisorGrams): array
    {
        if ($divisorGrams <= 0) {
            return [];
        }

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
            $micros[$key] = round((float) ($nutrition[$key] ?? 0) * $factor, 4);
        }

        $libraryCategory = $meal->isBaseRecipeCategory()
            ? IngredientLibraryCategory::BaseIngredient
            : __('Recipe');

        return [
            'name' => $meal->name,
            'usda_food_category' => $libraryCategory,
            'fdc_id' => null,
            'calories' => round((float) ($nutrition['calories'] ?? 0) * $factor, 2),
            'protein' => round((float) ($nutrition['protein'] ?? 0) * $factor, 2),
            'carbs' => round((float) ($nutrition['carbs'] ?? 0) * $factor, 2),
            'fat' => round((float) ($nutrition['fat'] ?? 0) * $factor, 2),
            'b6' => round((float) ($nutrition['b6'] ?? 0) * $factor, 4),
            'b9_folate' => round((float) ($nutrition['b9_folate'] ?? 0) * $factor, 4),
            'b12' => round((float) ($nutrition['b12'] ?? 0) * $factor, 4),
            'iron' => round((float) ($nutrition['iron'] ?? 0) * $factor, 4),
            'magnesium' => round((float) ($nutrition['magnesium'] ?? 0) * $factor, 4),
            'density' => 1.0,
            'is_verified' => $meal->isBaseRecipeCategory(),
            'micronutrients' => $micros,
            'source_meal_id' => $meal->id,
        ];
    }

    /**
     * Create or update the library ingredient derived from this meal's batch totals.
     *
     * @param  array<string, float|int>  $nutrition
     * @param  array<int, array{amount_grams: float|int|string}>  $sync
     * @param  float|null  $finishedWeightGrams  Batch weight after cooking/reduction; used as divisor for per-100 g density (must be > 0 when exposing).
     */
    public static function sync(Meal $meal, array $nutrition, array $sync, bool $exposeAsIngredient, ?float $finishedWeightGrams = null): void
    {
        $meal->refresh();

        $effectiveExpose = $exposeAsIngredient || $meal->isBaseRecipeCategory();

        $rawGrams = self::totalGramsFromSync($sync);
        $divisorGrams = ($finishedWeightGrams !== null && $finishedWeightGrams > 0)
            ? $finishedWeightGrams
            : $rawGrams;

        Log::info('meal_recipe_as_ingredient.sync_called', [
            'meal_id' => $meal->id,
            'meal_name' => $meal->name,
            'expose_as_ingredient' => $exposeAsIngredient,
            'effective_expose' => $effectiveExpose,
            'sync_pivot_count' => count($sync),
            'raw_ingredient_grams' => $rawGrams,
            'finished_weight_grams' => $finishedWeightGrams,
            'divisor_grams' => $divisorGrams,
        ]);

        if (! $effectiveExpose) {
            Log::info('meal_recipe_as_ingredient.cleared_not_exposed', ['meal_id' => $meal->id]);
            self::clearLinkForMeal($meal);

            return;
        }

        if ($rawGrams <= 0) {
            Log::warning('meal_recipe_as_ingredient.skipped_zero_raw_grams', [
                'meal_id' => $meal->id,
                'sync_keys' => array_keys($sync),
            ]);

            return;
        }

        if ($divisorGrams <= 0) {
            Log::warning('meal_recipe_as_ingredient.skipped_zero_divisor_grams', [
                'meal_id' => $meal->id,
                'raw_grams' => $rawGrams,
                'finished_weight_grams' => $finishedWeightGrams,
            ]);

            return;
        }

        $attrs = self::ingredientAttributesFromMeal($meal, $nutrition, $divisorGrams);

        if ($attrs === []) {
            Log::warning('meal_recipe_as_ingredient.skipped_empty_attrs', [
                'meal_id' => $meal->id,
                'divisor_grams' => $divisorGrams,
            ]);

            return;
        }

        $existing = Ingredient::query()->where('source_meal_id', $meal->id)->first();

        if ($existing !== null) {
            $existing->update($attrs);
            Log::info('meal_recipe_as_ingredient.updated', [
                'meal_id' => $meal->id,
                'ingredient_id' => $existing->id,
            ]);
        } else {
            $created = Ingredient::query()->create($attrs);
            Log::info('meal_recipe_as_ingredient.created', [
                'meal_id' => $meal->id,
                'ingredient_id' => $created->id,
            ]);
        }
    }

    public static function clearLinkForMeal(Meal $meal): void
    {
        Ingredient::query()->where('source_meal_id', $meal->id)->update(['source_meal_id' => null]);
    }

    /**
     * Sync the derived library ingredient from the meal as currently persisted (pivot rows + totals).
     *
     * @param  bool  $requestedExpose  True when the operator opted in via “use as base ingredient”.
     */
    public static function syncFromPersistedMeal(Meal $meal, bool $requestedExpose): void
    {
        $meal->loadMissing('ingredients');

        $syncPayload = [];
        foreach ($meal->ingredients as $ingredient) {
            $grams = (float) ($ingredient->pivot->amount_grams ?? 0);
            if ($grams <= 0) {
                continue;
            }

            $syncPayload[(int) $ingredient->getKey()] = [
                'amount' => (float) ($ingredient->pivot->amount ?? $grams),
                'unit' => (string) ($ingredient->pivot->unit ?? 'g'),
                'amount_grams' => round($grams, 4),
            ];
        }

        $isBulk = (bool) $meal->is_bulk;
        $nutrition = $meal->ingredients->isNotEmpty() && ! $isBulk
            ? RecipeNutritionCalculator::fromMeal($meal)
            : $meal->persistedNutritionAsCalculatorShape();

        $finished = $meal->finished_weight_grams !== null && (float) $meal->finished_weight_grams > 0
            ? (float) $meal->finished_weight_grams
            : null;

        self::sync($meal, $nutrition, $syncPayload, $requestedExpose, $finished);
    }
}
