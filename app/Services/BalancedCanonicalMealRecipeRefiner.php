<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Meal;
use App\Support\MealLibraryBulkNutrition;
use App\Support\MealLibraryEditGuard;
use App\Support\MealLibraryRefinerOverrides;
use App\Support\StandardMeatPortion;
use App\Support\WholeFoodDietPolicy;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Rewrites canonical Balanced deck recipes using whole-food library ingredients only.
 */
final class BalancedCanonicalMealRecipeRefiner
{
    public const BAKED_SALMON_NAME = 'Baked Salmon with Fermented Chimichurri & Roasted Vegetables';

    public const BAKED_SALMON_QUINOA_LEGACY_NAME = 'Baked Salmon with Fermented Chimichurri & Quinoa';

    public const BAKED_SALMON_RICE_LEGACY_NAME = 'Baked Salmon with Fermented Chimichurri & Steamed Basmati Rice';

    /** @var list<string> */
    public const BAKED_SALMON_PREVIOUS_NAMES = [
        self::BAKED_SALMON_QUINOA_LEGACY_NAME,
        self::BAKED_SALMON_RICE_LEGACY_NAME,
    ];

    public const CARROT_DESSERT_LEGACY_NAME = 'Carrot Oatmeal Cake';

    /** @var list<string> */
    public const CARROT_DESSERT_PREVIOUS_NAMES = [
        'Carrot Oatmeal Cake',
        'Carrot Walnut Spice Cake',
    ];

    public const CARROT_DESSERT_NAME = 'Carrot Walnut Raisin Spice Cake';

    public const CARROT_DESSERT_SERVINGS_COUNT = 16;

    public const BUTTERNUT_SQUASH_SOUP_NAME = 'Butternut Squash Soup';

    public const BATCH_SOUP_SERVINGS_COUNT = 10;

    /** @deprecated Use {@see BATCH_SOUP_SERVINGS_COUNT} */
    public const BUTTERNUT_SQUASH_SOUP_SERVINGS_COUNT = self::BATCH_SOUP_SERVINGS_COUNT;

    /** One US tablespoon psyllium husks per batch-soup serving (15 ml at library density 1.0 g/ml). */
    public const BATCH_SOUP_PSYLLIUM_TABLESPOON_GRAMS = 15.0;

    public const ROSEMARY_GARLIC_CHICKEN_PLATE_LEGACY_NAME = 'Grilled Rosemary Garlic Chicken Salad w Rocca & Red Pepper Dressing';

    public const ROSEMARY_GARLIC_CHICKEN_PLATE_NAME = 'Rosemary Garlic Chicken w Mushroom, Spinach & Roasted Sweet Potato';

    public const VEGAN_BUTTERNUT_PEANUT_STEW_NAME = 'Vegan Butternut Squash, Lentil & Peanut Stew w Brown Rice';

    public const VEGAN_BUTTERNUT_PEANUT_STEW_LEGACY_NAME = 'Vegan Butternut Squash, Lentil & Nut Stew w Brown Rice';

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

                if (MealLibraryEditGuard::shouldSkipMealRefinement($meal)) {
                    continue;
                }

                if ($mealName === self::CARROT_DESSERT_NAME && in_array($meal->name, self::CARROT_DESSERT_PREVIOUS_NAMES, true)) {
                    $meal->update([
                        'name' => self::CARROT_DESSERT_NAME,
                        'short_description' => 'Moist gluten-free carrot cake batch ('.self::CARROT_DESSERT_SERVINGS_COUNT.' slices) with house-milled almond flour, dates, pumpkin puree, walnuts, warm spices, grass-fed butter, and vanilla bean.',
                    ]);
                }

                if ($mealName === self::BAKED_SALMON_NAME && in_array($meal->name, self::BAKED_SALMON_PREVIOUS_NAMES, true)) {
                    $meal->update([
                        'name' => self::BAKED_SALMON_NAME,
                        'short_description' => 'Premium baked salmon with fermented chimichurri over roasted pumpkin, vegetables, and broccoli.',
                    ]);
                }

                if ($mealName === self::ROSEMARY_GARLIC_CHICKEN_PLATE_NAME && $meal->name === self::ROSEMARY_GARLIC_CHICKEN_PLATE_LEGACY_NAME) {
                    $meal->update([
                        'name' => self::ROSEMARY_GARLIC_CHICKEN_PLATE_NAME,
                        'short_description' => 'Grilled rosemary garlic chicken with sautéed mushrooms and spinach over roasted sweet potato wedges.',
                    ]);
                }

                if ($mealName === self::VEGAN_BUTTERNUT_PEANUT_STEW_NAME && $meal->name === self::VEGAN_BUTTERNUT_PEANUT_STEW_LEGACY_NAME) {
                    $meal->update([
                        'name' => self::VEGAN_BUTTERNUT_PEANUT_STEW_NAME,
                        'short_description' => 'A rich plant-based stew with red lentils, peanut butter, and crushed peanuts over brown rice.',
                    ]);
                }

                $this->syncMealIngredients(
                    $meal,
                    $definition['ingredients'],
                    $definition['diet_tags'] ?? null,
                    $definition['short_description'] ?? null,
                    ($definition['is_bulk'] ?? false) ? true : null,
                    isset($definition['servings_count']) ? (float) $definition['servings_count'] : null,
                );
                $updated[] = $meal->fresh()->name;
            }

            return $updated;
        });
    }

    private function resolveMealForRefinement(string $mealName): ?Meal
    {
        $query = Meal::queryForMealLibrary();

        if ($mealName === self::CARROT_DESSERT_NAME) {
            return $query->whereIn('name', [self::CARROT_DESSERT_NAME, ...self::CARROT_DESSERT_PREVIOUS_NAMES])->first();
        }

        if ($mealName === self::BAKED_SALMON_NAME) {
            return $query->whereIn('name', [self::BAKED_SALMON_NAME, ...self::BAKED_SALMON_PREVIOUS_NAMES])->first();
        }

        if ($mealName === self::ROSEMARY_GARLIC_CHICKEN_PLATE_NAME) {
            return $query->whereIn('name', [self::ROSEMARY_GARLIC_CHICKEN_PLATE_NAME, self::ROSEMARY_GARLIC_CHICKEN_PLATE_LEGACY_NAME])->first();
        }

        if ($mealName === self::VEGAN_BUTTERNUT_PEANUT_STEW_NAME) {
            return $query->whereIn('name', [self::VEGAN_BUTTERNUT_PEANUT_STEW_NAME, self::VEGAN_BUTTERNUT_PEANUT_STEW_LEGACY_NAME])->first();
        }

        return $query->where('name', $mealName)->first();
    }

    /**
     * @param  array<string, float>  $ingredientGrams
     * @param  list<string>|null  $dietTags
     */
    private function syncMealIngredients(
        Meal $meal,
        array $ingredientGrams,
        ?array $dietTags = null,
        ?string $shortDescription = null,
        ?bool $isBulk = null,
        ?float $servingsCount = null,
    ): void {
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
        $batchNutrition = RecipeNutritionCalculator::fromMeal($fresh);

        if ($isBulk === true && $servingsCount !== null && $servingsCount > 0) {
            $nutritionResolution = MealLibraryBulkNutrition::resolvePersistedNutrition(
                $batchNutrition,
                true,
                $servingsCount,
                null,
                true,
            );

            $update = array_merge(
                $nutritionResolution['attributes'],
                [
                    'nutrition_aggregates_synced' => $nutritionResolution['nutrition_aggregates_synced'],
                    'sickle_cell_program_highlight' => $nutritionResolution['sickle_cell_program_highlight'],
                    'is_bulk' => true,
                    'servings_count' => $servingsCount,
                ],
            );
        } else {
            $update = array_merge(
                Meal::nutritionSummaryToPersistedAttributes($batchNutrition),
                ['nutrition_aggregates_synced' => true],
            );
        }

        if ($dietTags !== null) {
            $update['diet_tags'] = $dietTags;
        }

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
     * @return array<string, array{ingredients: array<string, float>, diet_tags?: list<string>}>
     */
    private function recipeDefinitions(): array
    {
        $wholeFoodTags = WholeFoodDietPolicy::REQUIRED_MEAL_DIET_TAGS;

        $definitions = [
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
                    'Olive Oil (Extra Virgin)' => 5,
                    'Black Pepper' => 1,
                ],
                'diet_tags' => array_merge($wholeFoodTags, ['Vegetarian']),
            ],
            'Tamarind Honey & Sesame Chicken w Garlicky Green Beans' => [
                'ingredients' => [
                    'Chicken Breast' => StandardMeatPortion::GRAMS,
                    'Tamarind Paste' => 10,
                    'Honey (Raw)' => 5,
                    'Ginger (Raw)' => 5,
                    'Sesame Oil' => 10,
                    'Rice Vinegar' => 10,
                    'Garlic (Raw)' => 5,
                    'Sea Salt' => 1,
                    'Spring Onion' => 5,
                    'Garlicky Green Beans (Base)' => 100,
                    'Broccoli' => 60,
                    'Bok Choy' => 80,
                    'Cucumber Pickle (Base)' => 25,
                    'Sesame Seeds' => 5,
                ],
                'diet_tags' => $wholeFoodTags,
            ],
            self::ROSEMARY_GARLIC_CHICKEN_PLATE_NAME => [
                'ingredients' => [
                    'Rosemary Garlic Chicken (Base)' => StandardMeatPortion::GRAMS,
                    'Sweet Potato' => 85,
                    'Spinach (Fresh)' => 55,
                    'Mushrooms' => 45,
                    'Rosemary (Fresh)' => 2,
                    'Garlic (Raw)' => 4,
                    'Olive Oil (Extra Virgin)' => 5,
                    'Black Pepper' => 0.5,
                ],
                'diet_tags' => $wholeFoodTags,
            ],
            self::BAKED_SALMON_NAME => [
                'ingredients' => [
                    'Salmon' => StandardMeatPortion::GRAMS,
                    'Pumpkin' => 90,
                    'Bell Pepper (Red)' => 55,
                    'Zucchini' => 55,
                    'Carrots' => 45,
                    'Olive Oil (Extra Virgin)' => 5,
                    'Broccoli' => 60,
                    'Fermented Chimichurri (Base)' => 25,
                    'Pumpkin Seeds' => 10,
                ],
                'diet_tags' => $wholeFoodTags,
                'short_description' => 'Premium baked salmon with fermented chimichurri over roasted pumpkin, vegetables, and broccoli.',
            ],
            self::VEGAN_BUTTERNUT_PEANUT_STEW_NAME => [
                'ingredients' => [
                    'Cooked Brown Basmati Rice (Base)' => 113,
                    'Red Onion' => 30,
                    'Olive Oil' => 5,
                    'Garlic (Raw)' => 2,
                    'Tomato (Raw)' => 80,
                    'Bell Pepper (Red)' => 30,
                    'Lentils (Red)' => 40,
                    'Butternut Squash' => 60,
                    'Water (Filtered)' => 144,
                    'Peanut Butter' => 10,
                    'Vegetable Stock' => 48,
                    'Chili Flakes' => 0.5,
                    'Mushrooms' => 30,
                    'Zucchini' => 30,
                    'Spinach (Fresh)' => 16,
                    'Cabbage (Purple)' => 16,
                    'Fresh Coriander' => 4,
                    'Peanuts (Crushed)' => 15,
                    'Lime Juice' => 3,
                    'Sea Salt' => 0.5,
                    'Black Pepper' => 0.5,
                    'Cherry Tomatoes' => 10,
                ],
                'diet_tags' => array_merge($wholeFoodTags, ['Vegan']),
                'short_description' => 'A rich plant-based stew with red lentils, peanut butter, and crushed peanuts over brown rice.',
            ],
            'Marinated Pineapple, Peppers, Red Onion & Cilantro Side Salad' => [
                'ingredients' => [
                    'Pineapple' => 40,
                    'Bell Pepper (Red)' => 25,
                    'Cabbage (Purple)' => 45,
                    'Cucumber' => 35,
                    'Red Onion' => 12,
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
                    'Carrots' => 40,
                    'Fresh Basil' => 5,
                    'Fresh Mint' => 5,
                    'Olive Oil' => 5,
                    'Lemon Juice' => 8,
                ],
                'diet_tags' => array_merge($wholeFoodTags, ['Vegan']),
            ],
            self::CARROT_DESSERT_NAME => [
                'ingredients' => $this->carrotDessertBatchIngredients(),
                'is_bulk' => true,
                'servings_count' => self::CARROT_DESSERT_SERVINGS_COUNT,
                'diet_tags' => ['Vegetarian', 'Gluten-free'],
                'short_description' => 'Moist gluten-free carrot cake batch ('.self::CARROT_DESSERT_SERVINGS_COUNT.' slices) with house-milled almond flour, dates, pumpkin puree, walnuts, warm spices, grass-fed butter, and vanilla bean.',
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
            'Vegan Mushroom Soup' => $this->bulkSoupDefinition(
                [
                    'Mushrooms' => 200,
                    'White Onion' => 30,
                    'Homemade Coconut Milk' => 25,
                    'Water (Filtered)' => 140,
                    'Vegetable Stock' => 40,
                    'Garlic' => 3,
                    'Olive Oil' => 5,
                    'Turmeric Powder' => 2,
                    'Thyme (Fresh)' => 3,
                ],
                array_merge($wholeFoodTags, ['Vegan']),
            ),
            'Tomato Basil Soup' => $this->bulkSoupDefinition(
                [
                    'Tomato (Raw)' => 250,
                    'Fresh Basil' => 12,
                    'Garlic' => 4,
                    'Olive Oil' => 5,
                    'Water (Filtered)' => 150,
                    'Vegetable Broth (Base)' => 50,
                    'White Onion' => 35,
                    'Smoked Paprika' => 1,
                ],
                array_merge($wholeFoodTags, ['Vegan']),
            ),
            'Red Lentil Turmeric Soup' => $this->bulkSoupDefinition(
                [
                    'Lentils (Red)' => 80,
                    'Carrots' => 80,
                    'Spinach (Fresh)' => 40,
                    'Turmeric Powder' => 2,
                    'Ginger (Raw)' => 8,
                    'Garlic' => 4,
                    'Cumin Seeds' => 2,
                    'Water (Filtered)' => 150,
                    'Vegetable Broth (Base)' => 50,
                    'Olive Oil' => 5,
                    'Lemon Juice' => 8,
                    'White Onion' => 30,
                ],
                array_merge($wholeFoodTags, ['Vegan']),
            ),
            'Cauliflower Ginger Soup' => $this->bulkSoupDefinition(
                [
                    'Cauliflower Florets' => 220,
                    'Ginger (Raw)' => 12,
                    'Homemade Coconut Milk' => 40,
                    'Water (Filtered)' => 110,
                    'Vegetable Stock' => 40,
                    'White Onion' => 30,
                    'Garlic' => 4,
                    'Olive Oil' => 5,
                    'Turmeric Powder' => 2,
                    'Black Pepper' => 1,
                ],
                array_merge($wholeFoodTags, ['Vegan']),
            ),
            'Carrot Cumin Soup' => $this->bulkSoupDefinition(
                $this->carrotCuminSoupPerServingIngredients(),
                array_merge($wholeFoodTags, ['Vegan']),
                'Hearty carrot and French lentil soup with cumin, fresh parsley, and psyllium husks for fiber.',
            ),
            'Lentil Carrot Soup' => $this->bulkSoupDefinition(
                $this->carrotCuminSoupPerServingIngredients(),
                array_merge($wholeFoodTags, ['Vegan']),
                'Earthy carrot and French lentil soup with cumin and coriander.',
            ),
            'Sweet Potato Fennel Soup' => $this->bulkSoupDefinition(
                [
                    'Sweet Potato' => 120,
                    'Fennel Bulb' => 80,
                    'Homemade Coconut Milk' => 35,
                    'Water (Filtered)' => 130,
                    'Vegetable Broth (Base)' => 50,
                    'White Onion' => 30,
                    'Ginger (Raw)' => 10,
                    'Garlic' => 3,
                    'Olive Oil' => 5,
                    'Turmeric Powder' => 2,
                ],
                array_merge($wholeFoodTags, ['Vegan']),
            ),
            self::BUTTERNUT_SQUASH_SOUP_NAME => $this->bulkSoupDefinition(
                $this->butternutSquashSoupPerServingIngredients(),
                array_merge($wholeFoodTags, ['Vegan']),
                'A silky velvet roasted pumpkin soup blended with light coconut cream, spices, and psyllium husks for fiber.',
            ),
            'Miso Mushroom Soup' => $this->bulkSoupDefinition(
                [
                    'Mushrooms' => 120,
                    'Water (Filtered)' => 200,
                    'Miso Paste' => 10,
                    'Spring Onion' => 10,
                    'Ginger (Raw)' => 5,
                ],
                array_merge($wholeFoodTags, ['Vegan']),
                'Silky miso broth with mushrooms — fermented mineral anchor with psyllium husks for fiber.',
            ),
            'Miso Carrot Ginger Soup' => $this->bulkSoupDefinition(
                [
                    'Carrots' => 100,
                    'Water (Filtered)' => 200,
                    'Miso Paste' => 10,
                    'Ginger (Raw)' => 8,
                    'Spring Onion' => 8,
                ],
                array_merge($wholeFoodTags, ['Vegan']),
                'Golden carrot miso soup with ginger warmth and psyllium husks for fiber.',
            ),
            BalancedMealLibraryConfigurator::BONE_BROTH_MEAL_NAME => $this->bulkSoupDefinition(
                [
                    'Bone Broth (Base)' => BalancedMealLibraryConfigurator::BONE_BROTH_SERVING_GRAMS,
                ],
                $wholeFoodTags,
                '500 ml cup of defatted house bone broth — long-simmered, gelatin-rich, with psyllium husks for fiber.',
            ),
        ];

        return MealLibraryRefinerOverrides::mergeRecipeDefinitionMap($definitions);
    }

    /**
     * @return array<string, float>
     */
    private function carrotDessertPerServingIngredients(): array
    {
        return [
            'Medjool Dates' => 45,
            'Almond Flour (Base)' => 30,
            'Carrots' => 38,
            'Water (Filtered)' => 22,
            'Cinnamon' => 1,
            'Walnuts' => 8,
            'Grass Fed Butter' => 14,
            'Pumpkin Puree' => 15,
            'Ground Ginger' => 0.25,
            'Nutmeg' => 0.1,
            'Eggs (Large)' => 19,
            'Baking Soda' => 0.8,
            'Vanilla Pods' => 0.5,
            'Baking Powder' => 0.9,
            'Sea Salt' => 0.4,
        ];
    }

    /**
     * Full pan batch originally portioned as 8 thick slices; now cut into 16 servings.
     *
     * @return array<string, float>
     */
    private function carrotDessertBatchIngredients(): array
    {
        return $this->scalePerServingToBatch(
            $this->carrotDessertPerServingIngredients(),
            8.0,
        );
    }

    /**
     * @return array<string, float>
     */
    private function carrotCuminSoupPerServingIngredients(): array
    {
        return [
            'Carrots' => 150,
            'French Lentils' => 70,
            'Cumin Seeds' => 3,
            'Coriander Seeds' => 2,
            'Water (Filtered)' => 130,
            'Vegetable Broth (Base)' => 50,
            'White Onion' => 35,
            'Garlic' => 4,
            'Olive Oil' => 5,
            'Fresh Parsley' => 5,
            'Lemon Juice' => 8,
        ];
    }

    /**
     * @param  array<string, float>  $perServingIngredients
     * @param  list<string>  $dietTags
     * @return array{ingredients: array<string, float>, is_bulk: true, servings_count: float, diet_tags: list<string>, short_description?: string}
     */
    private function bulkSoupDefinition(
        array $perServingIngredients,
        array $dietTags,
        ?string $shortDescription = null,
    ): array {
        $definition = [
            'ingredients' => $this->scalePerServingToBatch(
                $this->batchSoupPerServingIngredients($perServingIngredients),
                self::BATCH_SOUP_SERVINGS_COUNT,
            ),
            'is_bulk' => true,
            'servings_count' => self::BATCH_SOUP_SERVINGS_COUNT,
            'diet_tags' => $dietTags,
        ];

        if ($shortDescription !== null) {
            $definition['short_description'] = $shortDescription;
        }

        return $definition;
    }

    /**
     * @param  array<string, float>  $base
     * @return array<string, float>
     */
    private function batchSoupPerServingIngredients(array $base): array
    {
        return array_merge($base, [
            'Psyllium Husks' => self::BATCH_SOUP_PSYLLIUM_TABLESPOON_GRAMS,
        ]);
    }

    /**
     * @return array<string, float>
     */
    private function butternutSquashSoupPerServingIngredients(): array
    {
        return [
            'Black Pepper' => 0.2,
            'Butternut Squash' => 100,
            'Garlic' => 1,
            'Homemade Coconut Milk' => 10,
            'Nutmeg' => 0.1,
            'Olive Oil' => 2,
            'Pumpkin Seeds' => 3,
            'Water (Filtered)' => 15,
            'White Onion' => 8,
        ];
    }

    /**
     * @param  array<string, float>  $perServing
     * @return array<string, float>
     */
    private function scalePerServingToBatch(array $perServing, float $servingsCount): array
    {
        $batch = [];

        foreach ($perServing as $ingredientName => $grams) {
            $batch[$ingredientName] = round($grams * $servingsCount, 4);
        }

        return $batch;
    }
}
