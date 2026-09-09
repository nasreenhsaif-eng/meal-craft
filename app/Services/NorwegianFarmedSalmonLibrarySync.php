<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\MealCalorieTier;
use App\Support\NorwegianFarmedSalmonNutrition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class NorwegianFarmedSalmonLibrarySync
{
    public function __construct(
        private BaseIngredientService $baseIngredientService,
        private MealTiersLibraryCopyService $mealTiersLibraryCopyService,
    ) {}

    /**
     * @return array{ingredients: int, bases: int, meals: int, tiers: int}
     */
    public function apply(): array
    {
        if (! Schema::hasTable('ingredients')) {
            return ['ingredients' => 0, 'bases' => 0, 'meals' => 0, 'tiers' => 0];
        }

        $salmon = Ingredient::query()
            ->whereIn('name', NorwegianFarmedSalmonNutrition::INGREDIENT_NAMES)
            ->orderBy('id')
            ->get();

        foreach ($salmon as $ingredient) {
            $this->applyVitaminDToIngredient($ingredient);
        }

        $salmonIds = $salmon->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        $bases = $this->rerollParentBases($salmonIds);
        $affectedIngredientIds = array_values(array_unique([
            ...$salmonIds,
            ...$bases->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(),
        ]));

        $mealsUpdated = $this->resyncMealsUsingIngredients($affectedIngredientIds);
        $tiersUpdated = $this->refreshCalorieTiersUsingIngredients($affectedIngredientIds);

        return [
            'ingredients' => $salmon->count(),
            'bases' => $bases->count(),
            'meals' => $mealsUpdated,
            'tiers' => $tiersUpdated,
        ];
    }

    private function applyVitaminDToIngredient(Ingredient $ingredient): void
    {
        $vitaminD = NorwegianFarmedSalmonNutrition::vitaminDMcgPer100g($ingredient->name);

        if ($vitaminD === null) {
            return;
        }

        $micros = is_array($ingredient->micronutrients) ? $ingredient->micronutrients : [];
        $micros['vitamin_d'] = $vitaminD;

        $ingredient->forceFill([
            'micronutrients' => $micros,
            'fdc_id' => null,
            'description' => NorwegianFarmedSalmonNutrition::DESCRIPTION,
            'is_verified' => true,
            'library_edited_at' => now(),
        ])->save();
    }

    /**
     * @param  list<int>  $salmonIds
     * @return Collection<int, Ingredient>
     */
    private function rerollParentBases(array $salmonIds): Collection
    {
        if ($salmonIds === [] || ! Schema::hasTable('ingredient_component')) {
            return collect();
        }

        $parentIds = DB::table('ingredient_component')
            ->whereIn('child_ingredient_id', $salmonIds)
            ->pluck('parent_ingredient_id')
            ->unique()
            ->filter()
            ->values();

        if ($parentIds->isEmpty()) {
            return collect();
        }

        $parents = Ingredient::query()
            ->with('components')
            ->whereIn('id', $parentIds)
            ->orderBy('id')
            ->get();

        foreach ($parents as $parent) {
            $rows = [];
            foreach ($parent->components as $child) {
                $grams = (float) ($child->pivot->amount_grams ?? 0);
                if ($grams <= 0) {
                    continue;
                }

                $rows[] = [
                    'ingredient_id' => (int) $child->id,
                    'amount_grams' => $grams,
                ];
            }

            if ($rows === []) {
                continue;
            }

            $this->baseIngredientService->upsert(
                $parent,
                $parent->name,
                $rows,
                $parent->finished_weight_grams,
                [
                    'description' => $parent->description,
                    'instructions' => $parent->instructions,
                ],
            );
        }

        return $parents;
    }

    /**
     * @param  list<int>  $ingredientIds
     */
    private function resyncMealsUsingIngredients(array $ingredientIds): int
    {
        if ($ingredientIds === [] || ! Schema::hasTable('ingredient_meal')) {
            return 0;
        }

        $mealIds = DB::table('ingredient_meal')
            ->whereIn('ingredient_id', $ingredientIds)
            ->distinct()
            ->pluck('meal_id');

        $updated = 0;

        foreach ($mealIds as $mealId) {
            $meal = Meal::query()->with('ingredients')->find($mealId);

            if ($meal === null || $meal->ingredients->isEmpty() || $meal->is_bulk) {
                continue;
            }

            $nutrition = RecipeNutritionCalculator::fromMeal($meal);
            $meal->update(array_merge(
                Meal::nutritionSummaryToPersistedAttributes($nutrition),
                ['nutrition_aggregates_synced' => true],
            ));
            $updated++;
        }

        return $updated;
    }

    /**
     * @param  list<int>  $ingredientIds
     */
    private function refreshCalorieTiersUsingIngredients(array $ingredientIds): int
    {
        if ($ingredientIds === [] || ! Schema::hasTable('ingredient_meal_tier')) {
            return 0;
        }

        $tierIds = DB::table('ingredient_meal_tier')
            ->whereIn('ingredient_id', $ingredientIds)
            ->distinct()
            ->pluck('meal_calorie_tier_id');

        $updated = 0;

        foreach ($tierIds as $tierId) {
            $tier = MealCalorieTier::query()
                ->with(['ingredients', 'meal'])
                ->find($tierId);

            if (! $tier instanceof MealCalorieTier || $tier->meal === null || $tier->ingredients->isEmpty()) {
                continue;
            }

            $gramsByIngredientId = [];
            foreach ($tier->ingredients as $ingredient) {
                $gramsByIngredientId[(int) $ingredient->id] = (float) ($ingredient->pivot->amount_grams ?? 0);
            }

            $this->mealTiersLibraryCopyService->persistTier(
                $tier->meal,
                (int) $tier->calorie_tier,
                $gramsByIngredientId,
            );
            $updated++;
        }

        return $updated;
    }
}
