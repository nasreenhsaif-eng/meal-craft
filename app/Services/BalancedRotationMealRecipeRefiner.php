<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Meal;
use App\Support\WholeFoodDietPolicy;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Rewrites Balanced weekly rotation meals not owned by other refiners — fixing missing
 * signature ingredients, banned pantry shortcuts, and whole-food dessert recipes.
 */
final class BalancedRotationMealRecipeRefiner
{
    public const ROASTED_POMEGRANATE_CHICKEN_NAME = 'Roasted Chicken in Pomegranate & Sumac Sauce w Turmeric Rice';

    /**
     * @return list<string>
     */
    public static function refinedMealNames(): array
    {
        return array_keys((new self)->recipeDefinitions());
    }

    /**
     * @return list<string>
     */
    public function refine(?string $onlyMealName = null): array
    {
        return DB::transaction(function () use ($onlyMealName): array {
            $updated = [];

            foreach ($this->recipeDefinitions() as $mealName => $definition) {
                if ($onlyMealName !== null && $mealName !== $onlyMealName) {
                    continue;
                }

                /** @var Meal|null $meal */
                $meal = Meal::queryForMealLibrary()->where('name', $mealName)->first();

                if ($meal === null) {
                    continue;
                }

                $this->syncMeal(
                    $meal,
                    $definition['ingredients'],
                    $definition['diet_tags'] ?? WholeFoodDietPolicy::REQUIRED_MEAL_DIET_TAGS,
                    $definition['short_description'] ?? null,
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
    private function syncMeal(Meal $meal, array $ingredientGrams, array $dietTags, ?string $shortDescription = null): void
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

        $update = array_merge(
            Meal::nutritionSummaryToPersistedAttributes($nutrition),
            [
                'nutrition_aggregates_synced' => true,
                'diet_tags' => $dietTags,
            ],
        );

        if ($shortDescription !== null) {
            $update['short_description'] = $shortDescription;
        }

        $meal->update($update);

        MealRecipeAsIngredientSyncService::syncFromPersistedMeal($fresh->fresh(['ingredients']), false);

        $violations = WholeFoodDietPolicy::violationsForMeal($meal->fresh(['ingredients']));

        if ($violations !== []) {
            throw new InvalidArgumentException(implode('; ', $violations));
        }
    }

    /**
     * @return array<string, array{ingredients: array<string, float>, diet_tags?: list<string>, short_description?: string}>
     */
    private function recipeDefinitions(): array
    {
        $tags = WholeFoodDietPolicy::REQUIRED_MEAL_DIET_TAGS;
        $spicyTags = array_merge($tags, ['Spicy']);
        $veganTags = array_merge($tags, ['Vegan']);
        $vegetarianTags = array_merge($tags, ['Vegetarian']);

        return [
            'Spicy Harissa Grilled Chicken w Roasted Sweet Potato & Zucchini' => [
                'ingredients' => [
                    'Chicken Breast' => 110,
                    'Harissa Paste (Base)' => 18,
                    'Sweet Potato' => 90,
                    'Zucchini' => 80,
                    'Garlic (Raw)' => 3,
                    'Olive Oil (Extra Virgin)' => 4,
                    'Lemon Juice' => 10,
                    'Black Pepper' => 0.5,
                ],
                'diet_tags' => $spicyTags,
            ],
            self::ROASTED_POMEGRANATE_CHICKEN_NAME => [
                'ingredients' => [
                    'Chicken Breast' => 110,
                    'Turmeric Rice (Base)' => 75,
                    'Pomegranate Sumac Sauce (Base)' => 28,
                    'Red Onion' => 25,
                    'Pomegranate Seeds' => 12,
                    'Parsley' => 5,
                ],
                'diet_tags' => $tags,
            ],
            'Pepper Chicken in Creamy Cajun Sauce w Roasted Potato' => [
                'ingredients' => [
                    'Chicken Breast' => 115,
                    'Potato' => 90,
                    'Bell Pepper (Red)' => 40,
                    'Cherry Tomatoes' => 30,
                    'Red Onion' => 15,
                    'Garlic (Raw)' => 4,
                    'Homemade Coconut Milk' => 25,
                    'Cajun Powder' => 2,
                    'Smoked Paprika' => 1,
                    'Olive Oil (Extra Virgin)' => 5,
                    'Lime Juice' => 8,
                    'Black Pepper' => 1,
                ],
                'diet_tags' => $spicyTags,
            ],
            'Vegan Smoky Cauliflower & Lentil Stew w Quinoa Bread & Tahini' => [
                'ingredients' => [
                    'Cauliflower' => 100,
                    'Lentils (Red)' => 80,
                    'Carrots' => 30,
                    'White Onion' => 28,
                    'Cherry Tomatoes' => 38,
                    'Garlic (Raw)' => 3,
                    'Smoked Paprika' => 2,
                    'Cumin Seeds' => 2,
                    'Coriander Seeds' => 1,
                    'Chili Flakes' => 1,
                    'Tahini' => 12,
                    'Lemon Juice' => 10,
                    'Olive Oil (Extra Virgin)' => 4,
                    'Water (Filtered)' => 120,
                    'Vegetable Stock' => 30,
                    'Quinoa Bread (Base)' => 45,
                ],
                'diet_tags' => $veganTags,
            ],
            'Vegan Sri Lankan Red Lentil Dal w Quinoa Bread' => [
                'ingredients' => [
                    'Lentils (Red)' => 70,
                    'Homemade Coconut Milk' => 35,
                    'White Onion' => 25,
                    'Garlic (Raw)' => 4,
                    'Ginger (Raw)' => 6,
                    'Tomato (Raw)' => 35,
                    'Turmeric Powder' => 1,
                    'cumin powder' => 1,
                    'Coriander Seeds' => 1,
                    'mustard seeds' => 1,
                    'Chili Flakes' => 1,
                    'Chili Powder' => 1,
                    'Olive Oil (Extra Virgin)' => 4,
                    'Water (Filtered)' => 150,
                    'Quinoa Bread (Base)' => 45,
                ],
                'diet_tags' => $veganTags,
            ],
            'Apple Pie Balls' => [
                'ingredients' => [
                    'Medjool Dates' => 35,
                    'Apple Green' => 25,
                    'Almond Butter' => 15,
                    'Walnuts' => 12,
                    'Cinnamon' => 2,
                    'Honey (Raw)' => 5,
                ],
                'diet_tags' => $vegetarianTags,
            ],
            'Cinnamon Raisin Balls' => [
                'ingredients' => [
                    'Medjool Dates' => 40,
                    'Raisins' => 15,
                    'Almond Butter' => 15,
                    'Walnuts' => 10,
                    'Cinnamon' => 3,
                    'Honey (Raw)' => 4,
                ],
                'diet_tags' => $vegetarianTags,
            ],
            'Saffron Pumpkin Muffin' => [
                'ingredients' => [
                    'Butternut Squash' => 80,
                    'Egg' => 55,
                    'Almond Flour (Base)' => 25,
                    'Honey (Raw)' => 8,
                    'Saffron Threads' => 0.2,
                    'Cinnamon' => 1,
                ],
                'diet_tags' => $vegetarianTags,
            ],
            'Chocolate PB Banana Muffin' => [
                'ingredients' => [
                    'Banana' => 60,
                    'Egg' => 55,
                    'Peanut Butter' => 18,
                    'Cocoa Powder' => 12,
                    'Almond Flour (Base)' => 22,
                    'Honey (Raw)' => 6,
                ],
                'diet_tags' => $vegetarianTags,
            ],
            'Chocolate Orange Brownie (N)' => [
                'ingredients' => [
                    'Almond Flour (Base)' => 45,
                    'Egg' => 55,
                    'Cocoa Powder' => 18,
                    'Honey (Raw)' => 12,
                    'Orange Juice' => 25,
                    'Orange Zest' => 3,
                    'Olive Oil' => 8,
                    'Walnuts' => 12,
                ],
                'diet_tags' => $vegetarianTags,
            ],
            'Salted Caramel Chocolate Bar' => [
                'ingredients' => [
                    'Almond Flour (Base)' => 35,
                    'Cocoa Powder' => 15,
                    'Tahini' => 20,
                    'Honey (Raw)' => 15,
                    'Coconut Meat' => 12,
                    'Walnuts' => 10,
                ],
                'diet_tags' => $vegetarianTags,
            ],
        ];
    }
}
