<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Meal;
use App\Support\MealLibraryEditGuard;
use App\Support\WholeFoodDietPolicy;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Rewrites vegan side salads to be legume-free and fully plant-based, and promotes
 * legume-forward former side salads into vegan main-slot recipes.
 */
final class BalancedVeganSideSaladRecipeRefiner
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

        return [
            'Citrus Beet Arugula Salad' => [
                'ingredients' => [
                    'Arugula' => 45,
                    'Beetroot' => 80,
                    'Orange Sections' => 45,
                    'Walnuts' => 8,
                    'Lemon Juice' => 8,
                    'Olive Oil' => 4,
                ],
                'diet_tags' => $veganTags,
            ],
            'Shaved Fennel Rocca Salad' => [
                'ingredients' => [
                    'Fennel Bulb' => 70,
                    'Rocca' => 45,
                    'Orange Sections' => 40,
                    'Lemon Juice' => 8,
                    'Olive Oil' => 4,
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
                    'Olive Oil' => 4,
                ],
                'diet_tags' => $veganTags,
            ],
            'Marinated Strawberry Beet Salad' => [
                'ingredients' => [
                    'Strawberries' => 60,
                    'Beetroot' => 70,
                    'Romaine Lettuce' => 50,
                    'White Onion' => 15,
                    'Apple Cider Vinegar' => 10,
                    'Olive Oil' => 4,
                ],
                'diet_tags' => $veganTags,
            ],
            'Coconut Grapefruit Salad' => [
                'ingredients' => [
                    'Romaine Lettuce' => 55,
                    'Grapefruit Sections' => 70,
                    'Cucumber' => 50,
                    'Lime Juice' => 10,
                    'Olive Oil' => 3,
                    'Coconut Meat' => 10,
                ],
                'diet_tags' => $veganTags,
            ],
            'Vegan Curry Lentil Salad' => [
                'ingredients' => [
                    'French Lentils' => 60,
                    'Spinach (Fresh)' => 40,
                    'Carrots' => 40,
                    'Bell Pepper (Red)' => 40,
                    'Curry Powder' => 2,
                    'Lemon Juice' => 10,
                    'Olive Oil' => 5,
                    'Wild Rice (Cooked)' => 80,
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
                    'Olive Oil' => 4,
                ],
                'diet_tags' => $veganTags,
            ],
            'Thai Rainbow Peanut Salad' => [
                'ingredients' => [
                    'Cabbage (Purple)' => 60,
                    'Carrots' => 40,
                    'Cucumber' => 40,
                    'Bell Pepper (Red)' => 30,
                    'Peanut Butter' => 10,
                    'Lime Juice' => 10,
                    'Water (Filtered)' => 10,
                    'Fresh Coriander' => 4,
                ],
                'diet_tags' => $veganTags,
            ],
        ];
    }
}
