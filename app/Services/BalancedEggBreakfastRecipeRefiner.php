<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Meal;
use App\Support\WholeFoodDietPolicy;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Whole-food egg breakfast recipes for the Balanced weekly rotation (slot 2).
 */
final class BalancedEggBreakfastRecipeRefiner
{
    /**
     * @return list<string>
     */
    public function refine(): array
    {
        return DB::transaction(function (): array {
            $updated = [];

            foreach ($this->recipeDefinitions() as $mealName => $definition) {
                /** @var Meal|null $meal */
                $meal = Meal::queryForMealLibrary()->where('name', $mealName)->first();

                if ($meal === null) {
                    continue;
                }

                $this->syncMeal($meal, $definition['ingredients'], $definition['diet_tags'] ?? WholeFoodDietPolicy::REQUIRED_MEAL_DIET_TAGS);
                $updated[] = $mealName;
            }

            return $updated;
        });
    }

    /**
     * @param  array<string, float>  $ingredientGrams
     * @param  list<string>  $dietTags
     */
    private function syncMeal(Meal $meal, array $ingredientGrams, array $dietTags): void
    {
        $sync = [];

        foreach ($ingredientGrams as $ingredientName => $grams) {
            if ($grams <= 0) {
                continue;
            }

            if (WholeFoodDietPolicy::isBannedIngredientName($ingredientName)) {
                throw new InvalidArgumentException("Refiner attempted to use banned ingredient: {$ingredientName}");
            }

            /** @var Ingredient|null $ingredient */
            $ingredient = Ingredient::query()->where('name', $ingredientName)->first();

            if ($ingredient === null) {
                throw new InvalidArgumentException("Missing library ingredient: {$ingredientName}");
            }

            $sync[$ingredient->id] = [
                'amount_grams' => round((float) $grams, 4),
                'amount' => round((float) $grams, 4),
                'unit' => 'g',
            ];
        }

        $meal->ingredients()->sync($sync);

        $fresh = $meal->fresh(['ingredients']);
        $nutrition = RecipeNutritionCalculator::fromMeal($fresh);

        $meal->update(array_merge(
            Meal::nutritionSummaryToPersistedAttributes($nutrition),
            [
                'nutrition_aggregates_synced' => true,
                'diet_tags' => $dietTags,
            ],
        ));

        MealRecipeAsIngredientSyncService::syncFromPersistedMeal($fresh->fresh(['ingredients']), false);
    }

    /**
     * @return array<string, array{ingredients: array<string, float>, diet_tags?: list<string>}>
     */
    private function recipeDefinitions(): array
    {
        $tags = array_merge(WholeFoodDietPolicy::REQUIRED_MEAL_DIET_TAGS, ['Vegetarian']);

        return [
            'Deconstructed Shakshuka Skillet' => [
                'ingredients' => [
                    'Egg' => 110,
                    'Tomato (Raw)' => 150,
                    'Bell Pepper (Red)' => 40,
                    'White Onion' => 30,
                    'Garlic (Raw)' => 4,
                    'Olive Oil' => 4,
                    'Fresh Coriander' => 4,
                    'Smoked Paprika' => 1,
                ],
                'diet_tags' => $tags,
            ],
            'Hummus Egg Stack' => [
                'ingredients' => [
                    'Egg' => 100,
                    'Chickpeas' => 60,
                    'Tahini' => 12,
                    'Lemon Juice' => 8,
                    'Cucumber' => 40,
                    'Olive Oil' => 3,
                    'Garlic (Raw)' => 2,
                ],
                'diet_tags' => $tags,
            ],
            'Kuku Sabzi Egg Muffins' => [
                'ingredients' => [
                    'Egg' => 110,
                    'Spinach (Fresh)' => 30,
                    'Fresh Coriander' => 8,
                    'Dill (Fresh)' => 4,
                    'Spring Onion' => 15,
                    'Walnuts' => 8,
                    'Olive Oil' => 4,
                ],
                'diet_tags' => $tags,
            ],
            'Sweet Potato Egg Hash' => [
                'ingredients' => [
                    'Egg' => 100,
                    'Sweet Potato' => 90,
                    'Bell Pepper (Red)' => 30,
                    'White Onion' => 25,
                    'Olive Oil' => 4,
                    'Fresh Coriander' => 3,
                ],
                'diet_tags' => $tags,
            ],
            'Butternut Squash Fritters Eggs Marinara' => [
                'ingredients' => [
                    'Egg' => 100,
                    'Butternut Squash' => 80,
                    'Tomato (Raw)' => 100,
                    'Garlic (Raw)' => 3,
                    'Olive Oil' => 4,
                    'Fresh Basil' => 4,
                ],
                'diet_tags' => $tags,
            ],
            'Smashed Beans & Eggs' => [
                'ingredients' => [
                    'Egg' => 100,
                    'Cannellini Beans' => 70,
                    'Tomato (Raw)' => 50,
                    'Garlic (Raw)' => 3,
                    'Olive Oil' => 4,
                    'Fresh Coriander' => 4,
                ],
                'diet_tags' => $tags,
            ],
        ];
    }
}
