<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Meal;
use App\Support\MealLibraryEditGuard;
use App\Support\WholeFoodDietPolicy;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Swaps plain white rice for varied whole-food complex carbs on Balanced rotation mains,
 * following carb bases used elsewhere in the meal library (quinoa bowls, sweet potato plates,
 * wild rice, turmeric rice, brown rice, quinoa bread).
 */
final class BalancedComplexCarbRecipeRefiner
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

                if (MealLibraryEditGuard::shouldSkipMealRefinement($meal)) {
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

            if (WholeFoodDietPolicy::isBannedIngredient($ingredient)) {
                throw new InvalidArgumentException("Refiner attempted to use banned ingredient: {$ingredientName}");
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

        $violations = WholeFoodDietPolicy::violationsForMeal($meal->fresh(['ingredients']));

        if ($violations !== []) {
            throw new InvalidArgumentException(implode('; ', $violations));
        }
    }

    /**
     * @return array<string, array{ingredients: array<string, float>, diet_tags?: list<string>}>
     */
    private function recipeDefinitions(): array
    {
        $tags = WholeFoodDietPolicy::REQUIRED_MEAL_DIET_TAGS;

        return [
            'Citrus Herb Salmon' => [
                'ingredients' => [
                    'Salmon (Raw)' => 95,
                    'Sweet Potato' => 90,
                    'Asparagus' => 55,
                    'Lemon Juice' => 10,
                    'Orange Juice' => 8,
                    'Dill (Fresh)' => 2,
                ],
                'diet_tags' => $tags,
            ],
            'Grilled Salmon Mango Salsa' => [
                'ingredients' => [
                    'Salmon (Raw)' => 110,
                    'Wild Rice (Cooked)' => 90,
                    'Mango' => 50,
                    'Bell Pepper (Red)' => 30,
                    'Cucumber' => 35,
                    'Lime Juice' => 10,
                    'Fresh Coriander' => 3,
                ],
                'diet_tags' => $tags,
            ],
            'Grilled Beef Steak Ratatouille & Saffron rice' => [
                'ingredients' => [
                    'Beef Sirloin' => 85,
                    'Saffron Rice (Base)' => 75,
                    'Zucchini' => 45,
                    'Bell Pepper (Red)' => 40,
                    'Tomato (Raw)' => 50,
                    'Eggplant' => 40,
                    'Garlic (Raw)' => 3,
                    'Fresh Basil' => 5,
                    'Parsley' => 5,
                    'Lemon Juice' => 10,
                    'Olive Oil (Extra Virgin)' => 4,
                    'Black Pepper' => 1,
                ],
                'diet_tags' => $tags,
            ],
            'Beef Bibimbap' => [
                'ingredients' => [
                    'Beef Ground Lean' => 88,
                    'Cooked Quinoa (Base)' => 84,
                    'Spinach (Fresh)' => 50,
                    'Carrots' => 40,
                    'Zucchini' => 40,
                    'Egg' => 55,
                    'Garlic (Raw)' => 3,
                    'Sesame Seeds' => 2,
                    'Spring Onion' => 18,
                ],
                'diet_tags' => $tags,
            ],
            'Persian Herb Beef Stew' => [
                'ingredients' => [
                    'Beef Chuck Roast' => 88,
                    'Cannellini Beans' => 70,
                    'Quinoa Bread (Base)' => 50,
                    'Spinach (Fresh)' => 35,
                    'Fresh Coriander' => 8,
                    'Dill (Fresh)' => 4,
                    'White Onion' => 28,
                    'Lemon Juice' => 8,
                    'Olive Oil' => 2,
                ],
                'diet_tags' => $tags,
            ],
            'Chili Beef Stuffed Peppers' => [
                'ingredients' => [
                    'Beef Ground Lean' => 88,
                    'Cooked Brown Basmati Rice (Base)' => 138,
                    'Bell Pepper (Red)' => 105,
                    'White Onion' => 28,
                    'Tomato (Raw)' => 55,
                    'Garlic (Raw)' => 4,
                    'Chili Powder' => 2,
                    'Olive Oil' => 2,
                ],
                'diet_tags' => $tags,
            ],
            'Grilled Chicken Chimichurri' => [
                'ingredients' => [
                    'Chicken Breast' => 120,
                    'Sweet Potato' => 85,
                    'Broccoli' => 60,
                    'Parsley' => 8,
                    'Fresh Coriander' => 8,
                    'Garlic (Raw)' => 4,
                    'Olive Oil (Extra Virgin)' => 7,
                    'Lemon Juice' => 10,
                    'Apple Cider Vinegar' => 4,
                ],
                'diet_tags' => $tags,
            ],
        ];
    }
}
