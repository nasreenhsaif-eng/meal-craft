<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Meal;
use App\Support\IngredientCookingYield;
use App\Support\PureCookingFatNutrition;
use App\Support\RecipeMacroRounding;
use App\Support\SickleCellNutrientRdi;

final class RecipeNutritionCalculator
{
    /**
     * @param  array<int, array{ingredient_id: int|null, amount?: float|int|string|null, unit?: string|null, amount_grams?: float|int|string|null}>  $rows
     * @param  bool  $applyMealCookingYield  When true, rescale dry-weighed cooked-macro staples for meal totals.
     *                                       Leave false for base-recipe component rollups.
     * @param  bool  $finalize  When false, return unrounded floats (for intermediate per-100 g formulation).
     * @return array<string, float>
     */
    public static function fromRows(array $rows, bool $applyMealCookingYield = false, bool $finalize = true): array
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

        /** @var list<array{ingredient: Ingredient, grams: float}> $pureFatPortions */
        $pureFatPortions = [];

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

            $inputGrams = self::resolvedGramsForRow($row, $ingredient);

            if ($inputGrams <= 0) {
                continue;
            }

            // Pure cooking fats never use cooked-yield rescale — lipid mass is conserved.
            $nutritionGrams = $applyMealCookingYield && ! PureCookingFatNutrition::isPureCookingFat($ingredient)
                ? IngredientCookingYield::nutritionMassGrams($ingredient, $inputGrams)
                : $inputGrams;

            if ($nutritionGrams <= 0) {
                continue;
            }

            if (PureCookingFatNutrition::isPureCookingFat($ingredient)) {
                $pureFatPortions[] = [
                    'ingredient' => $ingredient,
                    'grams' => $nutritionGrams,
                ];
            }

            // Unrounded: mass_g × (per-100g nutrient / 100) = mass_g × nutrient_per_gram.
            $perGram = self::unroundedNutrientsPerGram($ingredient);

            foreach ($nutrition as $key => $_value) {
                $nutrition[$key] += $nutritionGrams * ((float) ($perGram[$key] ?? 0));
            }
        }

        $nutrition = PureCookingFatNutrition::enforceFatFloor($nutrition, $pureFatPortions);

        if (! $finalize) {
            return $nutrition;
        }

        return RecipeMacroRounding::finalize($nutrition);
    }

    /**
     * Whole-meal nutrition from ingredient pivots (amount + unit when set, else grams).
     * Applies cooked-yield mass corrections for dry-weighed staples with cooked macros.
     *
     * @return array<string, float>
     */
    public static function fromMeal(Meal $meal, bool $finalize = true): array
    {
        $meal->loadMissing('ingredients');

        $rows = $meal->ingredients->map(function (Ingredient $ingredient): array {
            $pivot = $ingredient->pivot;
            $pivotAmount = $pivot->amount;
            $hasDisplayAmount = $pivotAmount !== null && $pivotAmount !== '' && (float) $pivotAmount > 0;
            $unitRaw = $pivot->unit ?? '';
            $amountGrams = (float) ($pivot->amount_grams ?? 0);

            // Always pass amount_grams so pure-fat resolution can refuse volume undercounts.
            if ($hasDisplayAmount && is_string($unitRaw) && $unitRaw !== '') {
                return [
                    'ingredient_id' => $ingredient->id,
                    'amount' => (float) $pivotAmount,
                    'unit' => $unitRaw,
                    'amount_grams' => $amountGrams,
                ];
            }

            return [
                'ingredient_id' => $ingredient->id,
                'amount_grams' => $amountGrams,
            ];
        })->all();

        return self::fromRows($rows, applyMealCookingYield: true, finalize: $finalize);
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
     * Per-100 g nutrition for a library row. Prepared base ingredients prefer stored finished
     * product density; when empty, roll up components using {@see Ingredient::$finished_weight_grams}
     * as the cooked yield divisor (matching {@see BaseIngredientService::upsert}).
     *
     * @return array<string, float>
     */
    public static function per100gNutritionForIngredient(Ingredient $ingredient): array
    {
        $ingredient->loadMissing('components');

        if ($ingredient->isPreparedBaseIngredient() && $ingredient->components->isNotEmpty()) {
            $stored = self::per100gFromStoredColumns($ingredient);

            if (self::per100gHasMeaningfulCalories($stored)) {
                return $stored;
            }

            $fromFormulation = self::per100gFromComponentFormulation($ingredient);
            if (self::per100gHasMeaningfulCalories($fromFormulation)) {
                return $fromFormulation;
            }
        }

        return self::per100gFromStoredColumns($ingredient);
    }

    /**
     * Raw nutrients per gram from the library row (no intermediate rounding).
     *
     * @return array<string, float>
     */
    public static function unroundedNutrientsPerGram(Ingredient $ingredient): array
    {
        $per100 = self::per100gNutritionForIngredient($ingredient);
        $perGram = [];

        foreach ($per100 as $key => $value) {
            $perGram[$key] = ((float) $value) / 100.0;
        }

        return $perGram;
    }

    /**
     * @return array<string, float>
     */
    private static function per100gFromStoredColumns(Ingredient $ingredient): array
    {
        $micros = is_array($ingredient->micronutrients) ? $ingredient->micronutrients : [];
        $canonical = PureCookingFatNutrition::canonicalPer100gMacros($ingredient);

        // Keep full float precision — rounding happens only on final dish totals.
        return [
            'calories' => (float) $canonical['calories'],
            'protein' => (float) $canonical['protein'],
            'carbs' => (float) $canonical['carbs'],
            'fat' => (float) $canonical['fat'],
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
    }

    /**
     * Roll up child nutrition into per-100 g of finished product.
     * Uses {@see Ingredient::$finished_weight_grams} when set (cooked yield), else raw component sum.
     *
     * @return array<string, float>
     */
    private static function per100gFromComponentFormulation(Ingredient $parent): array
    {
        $rows = [];
        $componentGrams = 0.0;

        foreach ($parent->components as $child) {
            $grams = (float) ($child->pivot->amount_grams ?? 0);
            if ($grams <= 0) {
                continue;
            }

            $rows[] = [
                'ingredient_id' => (int) $child->getKey(),
                'amount_grams' => $grams,
            ];
            $componentGrams += $grams;
        }

        if ($rows === [] || $componentGrams <= 0) {
            return self::per100gFromStoredColumns($parent);
        }

        $finished = $parent->finished_weight_grams !== null ? (float) $parent->finished_weight_grams : 0.0;
        $divisorGrams = $finished > 0 ? $finished : $componentGrams;

        // Unrounded batch → scale to per-100 g; library storage keeps float precision.
        $batch = self::fromRows($rows, applyMealCookingYield: false, finalize: false);
        $factor = 100.0 / $divisorGrams;

        return self::scaleNutritionValuesUnrounded($batch, $factor);
    }

    /**
     * @param  array<string, float>  $nutrition
     * @return array<string, float>
     */
    private static function scaleNutritionValuesUnrounded(array $nutrition, float $factor): array
    {
        if (! is_finite($factor) || $factor <= 0) {
            return [];
        }

        $out = [];
        foreach ($nutrition as $key => $value) {
            if (! is_numeric($value)) {
                continue;
            }
            $out[$key] = (float) $value * $factor;
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
        return PureCookingFatNutrition::resolvedGramsForRow($row, $ingredient);
    }

    private static function micronutrientPer100g(array $micronutrients, string $key): float
    {
        $v = $micronutrients[$key] ?? 0;

        return is_numeric($v) ? (float) $v : 0.0;
    }
}
