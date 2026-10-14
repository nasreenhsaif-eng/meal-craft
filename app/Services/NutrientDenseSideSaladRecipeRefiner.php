<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Meal;
use App\Support\MealLibraryEditGuard;
use App\Support\MealLibraryRefinerOverrides;
use App\Support\WholeFoodDietPolicy;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Rewrites vegan side salads to be legume-free and fully plant-based, and promotes
 * legume-forward former side salads into vegan main-slot recipes.
 */
final class NutrientDenseSideSaladRecipeRefiner
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
        $veganTags = array_merge(WholeFoodDietPolicy::REQUIRED_MEAL_DIET_TAGS, ['Vegan']);

        $definitions = [
            'Citrus Beet Arugula Salad' => [
                'ingredients' => [
                    'Arugula' => 80,
                    'Beetroot' => 60,
                    'Orange Sections' => 40,
                    'Cucumber' => 40,
                    'Walnuts' => 6,
                    'Fresh Mint' => 3,
                    'Classic Lemon Garlic Dressing (Base)' => 15,
                ],
                'diet_tags' => $veganTags,
            ],
            'Roasted Eggplant Rocca Salad' => [
                'ingredients' => [
                    'Eggplant' => 120,
                    'Cherry Tomatoes' => 50,
                    'Rocca' => 45,
                    'Pomegranate Seeds' => 15,
                    'Lemon Juice' => 8,
                ],
                'diet_tags' => $veganTags,
            ],
            'Marinated Strawberry Beet Salad' => [
                'ingredients' => [
                    'Romaine Lettuce' => 80,
                    'Beetroot' => 60,
                    'Strawberries' => 50,
                    'Celery' => 30,
                    'White Onion' => 10,
                    'Fresh Mint' => 4,
                    'Walnuts' => 8,
                    'Apple Cider Beet Marinade (Base)' => 15,
                ],
                'diet_tags' => $veganTags,
            ],
            'Coconut Grapefruit Salad' => [
                'ingredients' => [
                    'Romaine Lettuce' => 60,
                    'Broccoli' => 40,
                    'Grapefruit Sections' => 55,
                    'Cucumber' => 40,
                    'Pomegranate Seeds' => 10,
                    'Red Onion' => 10,
                    'Coconut Meat' => 7,
                    'Grapefruit Lime Dressing (Base)' => 15,
                ],
                'diet_tags' => $veganTags,
            ],
            'Vegan Curry Lentil Salad' => [
                'ingredients' => [
                    'French Lentils' => 60,
                    'White Onion' => 40,
                    'Kale' => 40,
                    'Broccoli' => 50,
                    'Carrots' => 40,
                    'Bell Pepper (Red)' => 35,
                    'Fresh Coriander' => 5,
                    'Mint Coconut Chutney Dressing (Base)' => 20,
                    'Pumpkin Seeds' => 10,
                    'Sesame Seeds' => 5,
                    'Cumin Ground' => 2,
                    'Sea Salt' => 1,
                    'Black Pepper' => 1,
                    'Olive Oil' => 8,
                ],
                'diet_tags' => $veganTags,
            ],
            'Spiced Cauliflower Chickpea Salad' => [
                'ingredients' => [
                    'Cauliflower Florets' => 100,
                    'Cooked Chickpeas (Base)' => 50,
                    'Romaine Lettuce' => 40,
                    'Cumin Seeds' => 2,
                    'Smoked Paprika' => 1,
                    'Lemon Juice' => 8,
                ],
                'diet_tags' => $veganTags,
            ],
            'Thai Rainbow Peanut Salad' => [
                'ingredients' => [
                    'Cabbage (Purple)' => 80,
                    'Carrots' => 45,
                    'Cucumber' => 45,
                    'Bell Pepper (Red)' => 35,
                    'Red Onion' => 12,
                    'Fresh Coriander' => 5,
                    'Peanuts (Crushed)' => 5,
                    'Peanut Butter Dressing (Base)' => 15,
                ],
                'diet_tags' => $veganTags,
            ],
        ];

        return MealLibraryRefinerOverrides::mergeRecipeDefinitionMap($definitions);
    }
}
