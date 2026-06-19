<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Meal;
use App\Support\WholeFoodDietPolicy;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Rewrites canonical Balanced deck recipes using whole-food library ingredients only.
 */
final class BalancedCanonicalMealRecipeRefiner
{
    public const BAKED_SALMON_LEGACY_NAME = 'Baked Salmon with Fermented Chimichurri & Steamed Basmati Rice';

    public const BAKED_SALMON_NAME = 'Baked Salmon with Fermented Chimichurri & Quinoa';

    public const CARROT_DESSERT_LEGACY_NAME = 'Carrot Oatmeal Cake';

    public const CARROT_DESSERT_NAME = 'Carrot Walnut Spice Cake';

    /**
     * @return list<string> Meal names updated
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
                $meal = Meal::queryForMealLibrary()
                    ->when(
                        $mealName === self::CARROT_DESSERT_NAME,
                        fn ($query) => $query->whereIn('name', [self::CARROT_DESSERT_NAME, self::CARROT_DESSERT_LEGACY_NAME]),
                        fn ($query) => $mealName === self::BAKED_SALMON_NAME
                            ? $query->whereIn('name', [self::BAKED_SALMON_NAME, self::BAKED_SALMON_LEGACY_NAME])
                            : $query->where('name', $mealName),
                    )
                    ->first();

                if ($meal === null) {
                    continue;
                }

                if ($mealName === self::CARROT_DESSERT_NAME && $meal->name === self::CARROT_DESSERT_LEGACY_NAME) {
                    $meal->update([
                        'name' => self::CARROT_DESSERT_NAME,
                        'short_description' => 'Moist whole-food carrot bake with walnuts, warm cinnamon, and raw honey.',
                    ]);
                }

                if ($mealName === self::BAKED_SALMON_NAME && $meal->name === self::BAKED_SALMON_LEGACY_NAME) {
                    $meal->update([
                        'name' => self::BAKED_SALMON_NAME,
                        'short_description' => 'Premium baked salmon with bright fermented chimichurri over fluffy quinoa and steamed broccoli.',
                    ]);
                }

                $this->syncMealIngredients($meal, $definition['ingredients'], $definition['diet_tags'] ?? null);
                $updated[] = $meal->fresh()->name;
            }

            return $updated;
        });
    }

    /**
     * @param  array<string, float>  $ingredientGrams
     * @param  list<string>|null  $dietTags
     */
    private function syncMealIngredients(Meal $meal, array $ingredientGrams, ?array $dietTags = null): void
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
            ['nutrition_aggregates_synced' => true],
        );

        if ($dietTags !== null) {
            $update['diet_tags'] = $dietTags;
        }

        $meal->update($update);

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
        $wholeFoodTags = WholeFoodDietPolicy::REQUIRED_MEAL_DIET_TAGS;

        return [
            'Blueberry Walnut Chia Pudding' => [
                'ingredients' => [
                    'Chia Seeds' => 35,
                    'Coconut Water' => 70,
                    'Homemade Coconut Milk' => 50,
                    'Blueberries' => 60,
                    'Walnuts' => 8,
                    'Pumpkin Seeds' => 12,
                    'Fresh Mint' => 3,
                    'Cinnamon' => 2,
                ],
                'diet_tags' => array_merge($wholeFoodTags, ['Vegan']),
            ],
            'Mediterranean Omelet' => [
                'ingredients' => [
                    'Egg' => 100,
                    'Bell Pepper (Red)' => 30,
                    'Tomato (Raw)' => 30,
                    'Shallots' => 20,
                    'Kalamata Olives' => 12,
                    'Avocado' => 20,
                    'Basil' => 5,
                    'Parsley' => 5,
                    'Thyme (Fresh)' => 2,
                    'Ghee' => 3,
                    'Olive Oil (Extra Virgin)' => 3,
                    'Sea Salt' => 1,
                    'Black Pepper' => 1,
                ],
                'diet_tags' => array_merge($wholeFoodTags, ['Vegetarian']),
            ],
            'Tamarind Honey & Sesame Chicken w Garlicky Green Beans' => [
                'ingredients' => [
                    'Chicken thigh' => 100,
                    'Green Beans' => 80,
                    'Broccoli' => 60,
                    'Cucumber Pickle (Base)' => 30,
                    'Garlic (Raw)' => 6,
                    'Ginger (Raw)' => 3,
                    'Tamarind Paste' => 18,
                    'Rice Vinegar' => 4,
                    'Honey (Raw)' => 5,
                    'Sesame Oil' => 2,
                    'Sesame Seeds' => 2,
                    'Spring Onion' => 20,
                    'Sea Salt' => 1,
                ],
                'diet_tags' => $wholeFoodTags,
            ],
            'Grilled Rosemary Garlic Chicken Salad w Rocca & Red Pepper Dressing' => [
                'ingredients' => [
                    'Rosemary Garlic Chicken (Base)' => 95,
                    'Rocca' => 40,
                    'Roasted Cherry Tomato (Base)' => 40,
                    'Red Pepper Dressing (Base)' => 20,
                    'Walnuts' => 10,
                    'Fresh Basil' => 4,
                    'Parsley' => 4,
                ],
                'diet_tags' => $wholeFoodTags,
            ],
            self::BAKED_SALMON_NAME => [
                'ingredients' => [
                    'Salmon' => 125,
                    'Quinoa (Base)' => 80,
                    'Broccoli' => 60,
                    'Fermented Chimichurri (Base)' => 25,
                    'Fresh Coriander' => 2,
                ],
                'diet_tags' => $wholeFoodTags,
            ],
            'Vegan Butternut Squash, Lentil & Nut Stew w Brown Rice' => [
                'ingredients' => [
                    'Butternut Squash' => 70,
                    'Lentils (Red)' => 22,
                    'Basmati Rice (Brown)' => 45,
                    'Bell Pepper (Red)' => 40,
                    'Cabbage (Purple)' => 15,
                    'Mushrooms' => 35,
                    'Zucchini' => 35,
                    'Spinach (Fresh)' => 20,
                    'Tomato (Raw)' => 60,
                    'Garlic (Raw)' => 9,
                    'Almond Butter' => 11,
                    'Walnuts' => 8,
                    'Coriander Seeds' => 2,
                    'Chili Flakes' => 1,
                    'Turmeric Powder' => 1,
                    'Olive Oil' => 3,
                    'Lime Juice' => 10,
                    'Vegetable Stock' => 150,
                    'Sea Salt' => 1,
                    'Black Pepper' => 1,
                ],
                'diet_tags' => array_merge($wholeFoodTags, ['Vegan']),
            ],
            'Marinated Pineapple, Peppers, Red Onion & Cilantro Side Salad' => [
                'ingredients' => [
                    'Pineapple' => 40,
                    'Bell Pepper (Red)' => 25,
                    'Cabbage (Purple)' => 45,
                    'Cucumber' => 35,
                    'Red Onion' => 12,
                    'Avocado' => 20,
                    'Fresh Coriander' => 4,
                    'Red Thai Chillies' => 2,
                    'Zesty Lime Chili Salad Dressing (Base)' => 12,
                ],
                'diet_tags' => array_merge($wholeFoodTags, ['Vegan']),
            ],
            'Classic Garden Salad' => [
                'ingredients' => [
                    'Romaine Lettuce' => 50,
                    'Tomato (Raw)' => 60,
                    'Cucumber' => 60,
                    'Bell Pepper (Red)' => 40,
                    'Cabbage (Purple)' => 30,
                    'Red Onion' => 25,
                    'Fresh Basil' => 5,
                    'Fresh Mint' => 5,
                    'Olive Oil' => 4,
                    'Lemon Juice' => 8,
                    'Sea Salt' => 0.5,
                ],
                'diet_tags' => array_merge($wholeFoodTags, ['Vegan']),
            ],
            self::CARROT_DESSERT_NAME => [
                'ingredients' => [
                    'Carrots' => 70,
                    'Egg' => 55,
                    'Walnuts' => 18,
                    'Cinnamon' => 2,
                    'Honey (Raw)' => 6,
                    'Coconut Meat' => 15,
                ],
                'diet_tags' => array_merge($wholeFoodTags, ['Vegetarian']),
            ],
            'Fruit Salad Bowl' => [
                'ingredients' => [
                    'Apple Green' => 40,
                    'Blueberries' => 40,
                    'Pomegranate Seeds' => 30,
                    'Pineapple' => 40,
                    'Strawberries' => 50,
                    'Fresh Mint' => 3,
                    'Honey (Raw)' => 1,
                    'Lemon Juice' => 5,
                ],
                'diet_tags' => array_merge($wholeFoodTags, ['Vegan']),
            ],
            'Vegan Mushroom Soup' => [
                'ingredients' => [
                    'Mushrooms' => 200,
                    'Wild Rice (Cooked)' => 70,
                    'White Onion' => 30,
                    'Homemade Coconut Milk' => 25,
                    'Vegetable Stock' => 180,
                    'Garlic' => 3,
                    'Olive Oil' => 3,
                    'Turmeric Powder' => 2,
                    'Thyme (Fresh)' => 3,
                ],
                'diet_tags' => array_merge($wholeFoodTags, ['Vegan']),
            ],
            BalancedMealLibraryConfigurator::BONE_BROTH_MEAL_NAME => [
                'ingredients' => [
                    'Bone Broth (Base)' => 240,
                ],
                'diet_tags' => $wholeFoodTags,
            ],
        ];
    }
}
