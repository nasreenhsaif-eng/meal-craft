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
    public const BAKED_SALMON_NAME = 'Baked Salmon with Fermented Chimichurri & Steamed Basmati Rice';

    public const BAKED_SALMON_QUINOA_LEGACY_NAME = 'Baked Salmon with Fermented Chimichurri & Quinoa';

    public const CARROT_DESSERT_LEGACY_NAME = 'Carrot Oatmeal Cake';

    public const CARROT_DESSERT_NAME = 'Carrot Walnut Spice Cake';

    public const ROSEMARY_GARLIC_CHICKEN_PLATE_LEGACY_NAME = 'Grilled Rosemary Garlic Chicken Salad w Rocca & Red Pepper Dressing';

    public const ROSEMARY_GARLIC_CHICKEN_PLATE_NAME = 'Rosemary Garlic Chicken w Mushroom, Spinach & Roasted Sweet Potato';

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
                $meal = $this->resolveMealForRefinement($mealName);

                if ($meal === null) {
                    continue;
                }

                if ($mealName === self::CARROT_DESSERT_NAME && $meal->name === self::CARROT_DESSERT_LEGACY_NAME) {
                    $meal->update([
                        'name' => self::CARROT_DESSERT_NAME,
                        'short_description' => 'Moist whole-food carrot bake with walnuts, warm cinnamon, and raw honey.',
                    ]);
                }

                if ($mealName === self::BAKED_SALMON_NAME && $meal->name === self::BAKED_SALMON_QUINOA_LEGACY_NAME) {
                    $meal->update([
                        'name' => self::BAKED_SALMON_NAME,
                        'short_description' => 'Premium baked salmon with fermented chimichurri over fluffy steamed basmati rice and broccoli.',
                    ]);
                }

                if ($mealName === self::ROSEMARY_GARLIC_CHICKEN_PLATE_NAME && $meal->name === self::ROSEMARY_GARLIC_CHICKEN_PLATE_LEGACY_NAME) {
                    $meal->update([
                        'name' => self::ROSEMARY_GARLIC_CHICKEN_PLATE_NAME,
                        'short_description' => 'Grilled rosemary garlic chicken with sautéed mushrooms and spinach over roasted sweet potato wedges.',
                    ]);
                }

                $this->syncMealIngredients($meal, $definition['ingredients'], $definition['diet_tags'] ?? null);
                $updated[] = $meal->fresh()->name;
            }

            return $updated;
        });
    }

    private function resolveMealForRefinement(string $mealName): ?Meal
    {
        $query = Meal::queryForMealLibrary();

        if ($mealName === self::CARROT_DESSERT_NAME) {
            return $query->whereIn('name', [self::CARROT_DESSERT_NAME, self::CARROT_DESSERT_LEGACY_NAME])->first();
        }

        if ($mealName === self::BAKED_SALMON_NAME) {
            return $query->whereIn('name', [self::BAKED_SALMON_NAME, self::BAKED_SALMON_QUINOA_LEGACY_NAME])->first();
        }

        if ($mealName === self::ROSEMARY_GARLIC_CHICKEN_PLATE_NAME) {
            return $query->whereIn('name', [self::ROSEMARY_GARLIC_CHICKEN_PLATE_NAME, self::ROSEMARY_GARLIC_CHICKEN_PLATE_LEGACY_NAME])->first();
        }

        return $query->where('name', $mealName)->first();
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
                    'Olive Oil (Extra Virgin)' => 6,
                    'Black Pepper' => 1,
                ],
                'diet_tags' => array_merge($wholeFoodTags, ['Vegetarian']),
            ],
            'Tamarind Honey & Sesame Chicken w Garlicky Green Beans' => [
                'ingredients' => [
                    'Chicken thigh' => 100,
                    'Garlicky Green Beans (Base)' => 85,
                    'Broccoli' => 60,
                    'Cucumber' => 25,
                    'Garlic (Raw)' => 2,
                    'Ginger (Raw)' => 3,
                    'Tamarind Paste' => 18,
                    'Rice Vinegar' => 4,
                    'Honey (Raw)' => 5,
                    'Sesame Oil' => 2,
                    'Sesame Seeds' => 2,
                    'Spring Onion' => 20,
                ],
                'diet_tags' => $wholeFoodTags,
            ],
            self::ROSEMARY_GARLIC_CHICKEN_PLATE_NAME => [
                'ingredients' => [
                    'Rosemary Garlic Chicken (Base)' => 95,
                    'Sweet Potato' => 85,
                    'Spinach (Fresh)' => 55,
                    'Mushrooms' => 45,
                    'Olive Oil (Extra Virgin)' => 4,
                    'Black Pepper' => 0.5,
                ],
                'diet_tags' => $wholeFoodTags,
            ],
            self::BAKED_SALMON_NAME => [
                'ingredients' => [
                    'Salmon' => 125,
                    'Steamed Basmati Rice (Base)' => 75,
                    'Broccoli' => 60,
                    'Fermented Chimichurri (Base)' => 22,
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
                    'White Onion' => 25,
                    'Garlic (Raw)' => 9,
                    'Almond Butter' => 11,
                    'Walnuts' => 8,
                    'Coriander Seeds' => 2,
                    'Chili Flakes' => 1,
                    'Turmeric Powder' => 1,
                    'Olive Oil' => 3,
                    'Lime Juice' => 10,
                    'Water (Filtered)' => 120,
                    'Vegetable Stock' => 30,
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
                    'Water (Filtered)' => 140,
                    'Vegetable Stock' => 40,
                    'Garlic' => 3,
                    'Olive Oil' => 3,
                    'Turmeric Powder' => 2,
                    'Thyme (Fresh)' => 3,
                ],
                'diet_tags' => array_merge($wholeFoodTags, ['Vegan']),
            ],
            'Tomato Basil Soup' => [
                'ingredients' => [
                    'Tomato (Raw)' => 250,
                    'Fresh Basil' => 12,
                    'Garlic' => 4,
                    'Olive Oil' => 4,
                    'Water (Filtered)' => 150,
                    'Vegetable Broth (Base)' => 50,
                    'White Onion' => 35,
                    'Smoked Paprika' => 1,
                ],
                'diet_tags' => array_merge($wholeFoodTags, ['Vegan']),
            ],
            'Red Lentil Turmeric Soup' => [
                'ingredients' => [
                    'Lentils (Red)' => 80,
                    'Carrots' => 80,
                    'Spinach (Fresh)' => 40,
                    'Turmeric Powder' => 2,
                    'Ginger (Raw)' => 8,
                    'Garlic' => 4,
                    'Cumin Seeds' => 2,
                    'Water (Filtered)' => 150,
                    'Vegetable Broth (Base)' => 50,
                    'Olive Oil' => 3,
                    'Lemon Juice' => 8,
                    'White Onion' => 30,
                ],
                'diet_tags' => array_merge($wholeFoodTags, ['Vegan']),
            ],
            'Cauliflower Ginger Soup' => [
                'ingredients' => [
                    'Cauliflower Florets' => 220,
                    'Ginger (Raw)' => 12,
                    'Homemade Coconut Milk' => 40,
                    'Water (Filtered)' => 110,
                    'Vegetable Stock' => 40,
                    'White Onion' => 30,
                    'Garlic' => 4,
                    'Olive Oil' => 4,
                    'Turmeric Powder' => 2,
                    'Black Pepper' => 1,
                ],
                'diet_tags' => array_merge($wholeFoodTags, ['Vegan']),
            ],
            'Carrot Cumin Soup' => [
                'ingredients' => [
                    'Carrots' => 150,
                    'French Lentils' => 70,
                    'Cumin Seeds' => 3,
                    'Coriander Seeds' => 2,
                    'Water (Filtered)' => 130,
                    'Vegetable Broth (Base)' => 50,
                    'White Onion' => 35,
                    'Garlic' => 4,
                    'Olive Oil' => 4,
                    'Fresh Parsley' => 5,
                    'Lemon Juice' => 8,
                ],
                'diet_tags' => array_merge($wholeFoodTags, ['Vegan']),
            ],
            'Sweet Potato Fennel Soup' => [
                'ingredients' => [
                    'Sweet Potato' => 120,
                    'Fennel Bulb' => 80,
                    'Homemade Coconut Milk' => 35,
                    'Water (Filtered)' => 130,
                    'Vegetable Broth (Base)' => 50,
                    'White Onion' => 30,
                    'Ginger (Raw)' => 10,
                    'Garlic' => 3,
                    'Olive Oil' => 4,
                    'Turmeric Powder' => 2,
                ],
                'diet_tags' => array_merge($wholeFoodTags, ['Vegan']),
            ],
            BalancedMealLibraryConfigurator::BONE_BROTH_MEAL_NAME => [
                'ingredients' => [
                    'Bone Broth (Base)' => 120,
                ],
                'diet_tags' => $wholeFoodTags,
            ],
        ];
    }
}
