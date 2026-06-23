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

                $this->syncMeal(
                    $meal,
                    $definition['ingredients'],
                    $definition['diet_tags'] ?? WholeFoodDietPolicy::REQUIRED_MEAL_DIET_TAGS,
                    $definition['highlight'] ?? null,
                );
                $updated[] = $mealName;
            }

            return $updated;
        });
    }

    /**
     * @param  array<string, float>  $ingredientGrams
     * @param  list<string>  $dietTags
     */
    private function syncMeal(Meal $meal, array $ingredientGrams, array $dietTags, ?string $highlight = null): void
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
            $highlight !== null && trim($highlight) !== '' ? [
                'short_description' => trim($highlight),
                'highlight' => trim($highlight),
            ] : [],
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
                    'Creamy Cumin Hummus (Base)' => 100,
                    'Egg' => 100,
                    'Spinach (Fresh)' => 45,
                    'Cherry Tomatoes' => 45,
                    'Cucumber' => 40,
                    'Olive Oil' => 3,
                    'Black Pepper' => 1,
                ],
                'highlight' => 'Soft-boiled eggs stacked over sautéed spinach and cherry tomatoes on a bed of creamy house cumin hummus.',
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
                    'Barberries' => 5,
                    'Olive Oil' => 4,
                    'Sea Salt' => 1,
                    'Black Pepper' => 1,
                ],
                'highlight' => 'Traditional Persian-style baked egg muffins packed with minced fresh herbs, walnuts, barberries (zereshk), and seasoning.',
                'diet_tags' => $tags,
            ],
            'Sweet Potato Egg Hash' => [
                'ingredients' => [
                    'Egg' => 100,
                    'Sweet Potato' => 90,
                    'Bell Pepper (Red)' => 30,
                    'White Onion' => 25,
                    'Olive Oil' => 4,
                    'Rosemary (Fresh)' => 2,
                    'Thyme (Fresh)' => 2,
                    'Sea Salt' => 1,
                    'Black Pepper' => 1,
                    'Fresh Coriander' => 3,
                ],
                'highlight' => 'Rosemary-thyme roasted sweet potato hash with sautéed onion and pepper, finished with softly scrambled eggs.',
                'diet_tags' => $tags,
            ],
            'Butternut Squash Fritters & Eggs' => [
                'ingredients' => [
                    'Butternut Squash' => 200,
                    'Egg' => 100,
                    'Quinoa Flour' => 10,
                    'Fresh Coriander' => 10,
                    'Lemon Juice' => 30,
                    'Garlic (Raw)' => 1.5,
                    'Olive Oil' => 4,
                    'Sea Salt' => 1,
                    'Chili Flakes' => 0.5,
                    'Fennel Seeds' => 0.5,
                    'Cumin Seeds' => 0.5,
                    'Coriander Seeds' => 0.5,
                    'Marinara Sauce (Base)' => 80,
                ],
                'highlight' => 'Spiced roasted butternut squash fritters bound with eggs, fresh coriander, and lemon — served with warm marinara on the side.',
                'diet_tags' => array_merge(WholeFoodDietPolicy::REQUIRED_MEAL_DIET_TAGS, ['Vegetarian', 'Gluten-Free']),
            ],
            'Smashed Beans & Eggs' => [
                'ingredients' => [
                    'Smashed White Beans (Base)' => 80,
                    'Egg' => 100,
                    'Tomato (Raw)' => 50,
                    'Fresh Coriander' => 4,
                ],
                'diet_tags' => $tags,
            ],
        ];
    }
}
