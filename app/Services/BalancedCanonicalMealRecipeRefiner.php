<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Meal;
use App\Support\BoneBrothBaseRecipe;
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

    public const VEGAN_MUSHROOM_SOUP_NAME = 'Vegan Mushroom Soup';

    /** One-liter kitchen batch poured into two 500 ml cups. */
    public const VEGAN_MUSHROOM_SOUP_BATCH_SERVINGS_COUNT = 2;

    /** Optional thickener for the full 1 L mushroom soup batch (~1.5 tsp). */
    public const VEGAN_MUSHROOM_SOUP_PSYLLIUM_BATCH_GRAMS = 5.0;

    public const MISO_MUSHROOM_SOUP_NAME = 'Miso Mushroom Soup';

    /** Two-liter kitchen batch poured into four 500 ml cups. */
    public const MISO_MUSHROOM_SOUP_BATCH_SERVINGS_COUNT = 4;

    /** Optional fiber boost for the full 2 L miso mushroom batch (~2 tsp). */
    public const MISO_MUSHROOM_SOUP_PSYLLIUM_BATCH_GRAMS = 8.0;

    public const RED_LENTIL_TURMERIC_SOUP_NAME = 'Red Lentil Turmeric Soup';

    /** Five-liter kitchen batch poured into ten 500 ml cups. */
    public const RED_LENTIL_TURMERIC_SOUP_BATCH_SERVINGS_COUNT = 10;

    public const CAULIFLOWER_GINGER_SOUP_NAME = 'Cauliflower Ginger Soup';

    /** Five-liter kitchen batch poured into ten 500 ml cups. */
    public const CAULIFLOWER_GINGER_SOUP_BATCH_SERVINGS_COUNT = 10;

    public const LENTIL_CARROT_SOUP_NAME = 'Lentil Carrot Soup';

    /** Five-liter kitchen batch poured into ten 500 ml cups. */
    public const LENTIL_CARROT_SOUP_BATCH_SERVINGS_COUNT = 10;

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
                    'Roasted Mixed Vegetables (Base)' => 100,
                    'Broccoli' => 60,
                    'Fermented Chimichurri (Base)' => 25,
                    'Pumpkin Seeds' => 10,
                ],
                'diet_tags' => $wholeFoodTags,
                'short_description' => 'Premium baked salmon with fermented chimichurri over house roasted mixed vegetables and broccoli.',
            ],
            self::VEGAN_BUTTERNUT_PEANUT_STEW_NAME => [
                'ingredients' => [
                    'Cooked Brown Basmati Rice (Base)' => 80,
                    'Red Onion' => 30,
                    'Olive Oil' => 3,
                    'Garlic (Raw)' => 2,
                    'Tomato (Raw)' => 80,
                    'Bell Pepper (Red)' => 30,
                    'Lentils (Red)' => 30,
                    'Butternut Squash' => 60,
                    'Water (Filtered)' => 130,
                    'Peanut Butter' => 8,
                    'Vegetable Stock' => 50,
                    'Chili Flakes' => 0.5,
                    'Mushrooms' => 30,
                    'Zucchini' => 30,
                    'Spinach (Fresh)' => 16,
                    'Cabbage (Purple)' => 16,
                    'Fresh Coriander' => 4,
                    'Peanuts (Crushed)' => 8,
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
                    'Cabbage (Purple)' => 60,
                    'Cucumber' => 55,
                    'Pineapple' => 50,
                    'Bell Pepper (Red)' => 35,
                    'Red Onion' => 15,
                    'Fresh Coriander' => 6,
                    'Red Thai Chillies' => 2,
                    'Zesty Lime Chili Salad Dressing (Base)' => 20,
                ],
                'diet_tags' => array_merge($wholeFoodTags, ['Vegan']),
                'short_description' => 'Tropical side salad of pineapple, peppers, red onion, cucumber, and cilantro with zesty lime-chili dressing — packed for a 500 ml bowl with dressing on the side.',
            ],
            'Classic Garden Salad' => [
                'ingredients' => [
                    'Romaine Lettuce' => 90,
                    'Tomato (Raw)' => 50,
                    'Cucumber' => 60,
                    'Carrots' => 35,
                    'Classic Lemon Garlic Dressing (Base)' => 20,
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
            self::VEGAN_MUSHROOM_SOUP_NAME => [
                'ingredients' => [
                    'Mushrooms' => 600.0,
                    'White Onion' => 75.0,
                    'Garlic' => 8.0,
                    'Olive Oil' => 10.0,
                    'Bone Broth (Base)' => 250.0,
                    'Water (Filtered)' => 350.0,
                    'Thyme (Fresh)' => 3.0,
                    'Psyllium Husks' => self::VEGAN_MUSHROOM_SOUP_PSYLLIUM_BATCH_GRAMS,
                    'Sea Salt' => 3.0,
                    'Black Pepper' => 1.0,
                ],
                'is_bulk' => true,
                'servings_count' => self::VEGAN_MUSHROOM_SOUP_BATCH_SERVINGS_COUNT,
                'diet_tags' => $wholeFoodTags,
                'short_description' => 'Deeply browned mushroom soup prepared as a 1 L batch with bone broth, then portioned into 500 ml cups (2 servings).',
            ],
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
            self::RED_LENTIL_TURMERIC_SOUP_NAME => [
                'ingredients' => [
                    'Lentils (Red)' => 250.0,
                    'Carrots' => 600.0,
                    'Spinach (Fresh)' => 300.0,
                    'White Onion' => 250.0,
                    'Olive Oil' => 15.0,
                    'Vegetable Broth (Base)' => 500.0,
                    'Water (Filtered)' => 3800.0,
                    'Lemon Juice' => 60.0,
                    'Garlic' => 30.0,
                    'Ginger (Raw)' => 25.0,
                    'Turmeric Powder' => 15.0,
                    'cumin powder' => 8.0,
                    'Black Pepper' => 2.0,
                    'Sea Salt' => 18.0,
                ],
                'is_bulk' => true,
                'servings_count' => self::RED_LENTIL_TURMERIC_SOUP_BATCH_SERVINGS_COUNT,
                'diet_tags' => array_merge($wholeFoodTags, ['Vegan']),
                'short_description' => 'Warming red lentil turmeric soup prepared as a 5 L batch with carrots and spinach, then portioned into 500 ml cups (10 servings).',
            ],
            self::CAULIFLOWER_GINGER_SOUP_NAME => [
                'ingredients' => [
                    'Cauliflower Florets' => 2200.0,
                    'White Onion' => 350.0,
                    'Garlic' => 35.0,
                    'Ginger (Raw)' => 35.0,
                    'Olive Oil' => 25.0,
                    'Homemade Coconut Milk' => 450.0,
                    'Water (Filtered)' => 3200.0,
                    'Vegetable Broth (Base)' => 20.0,
                    'Turmeric Powder' => 12.0,
                    'Black Pepper' => 3.0,
                    'Sea Salt' => 20.0,
                    'Lemon Juice' => 25.0,
                ],
                'is_bulk' => true,
                'servings_count' => self::CAULIFLOWER_GINGER_SOUP_BATCH_SERVINGS_COUNT,
                'diet_tags' => array_merge($wholeFoodTags, ['Vegan']),
                'short_description' => 'Silky cauliflower-ginger soup prepared as a 5 L batch with coconut milk and turmeric, then portioned into 500 ml cups (10 servings).',
            ],
            'Carrot Cumin Soup' => $this->bulkSoupDefinition(
                $this->carrotCuminSoupPerServingIngredients(),
                array_merge($wholeFoodTags, ['Vegan']),
                'Hearty carrot and French lentil soup with cumin, fresh parsley, and psyllium husks for fiber.',
            ),
            self::LENTIL_CARROT_SOUP_NAME => [
                'ingredients' => [
                    'French Lentils' => 220.0,
                    'Carrots' => 1200.0,
                    'White Onion' => 350.0,
                    'Fresh Parsley' => 40.0,
                    'Olive Oil' => 15.0,
                    'Vegetable Broth (Base)' => 500.0,
                    'Water (Filtered)' => 3600.0,
                    'Lemon Juice' => 60.0,
                    'Garlic' => 30.0,
                    'Cumin Seeds' => 10.0,
                    'Coriander Seeds' => 8.0,
                    'Sea Salt' => 20.0,
                    'Black Pepper' => 2.0,
                ],
                'is_bulk' => true,
                'servings_count' => self::LENTIL_CARROT_SOUP_BATCH_SERVINGS_COUNT,
                'diet_tags' => array_merge($wholeFoodTags, ['Vegan']),
                'short_description' => 'Earthy French lentil and carrot soup prepared as a 5 L batch with cumin and coriander, then portioned into 500 ml cups (10 servings).',
            ],
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
            self::MISO_MUSHROOM_SOUP_NAME => [
                'ingredients' => [
                    'Mushrooms' => 700.0,
                    'Water (Filtered)' => 2000.0,
                    'Miso Paste' => 100.0,
                    'Ginger (Raw)' => 15.0,
                    'Spring Onion' => 70.0,
                    'Psyllium Husks' => self::MISO_MUSHROOM_SOUP_PSYLLIUM_BATCH_GRAMS,
                ],
                'is_bulk' => true,
                'servings_count' => self::MISO_MUSHROOM_SOUP_BATCH_SERVINGS_COUNT,
                'diet_tags' => array_merge($wholeFoodTags, ['Vegan']),
                'short_description' => 'Gentle miso-mushroom broth prepared as a 2 L batch, then portioned into 500 ml cups (4 servings).',
            ],
            'Miso Carrot Ginger Soup' => $this->bulkSoupDefinition(
                [
                    'Carrots' => 112.5,
                    'Garlic (Raw)' => 3,
                    'Ginger (Raw)' => 3,
                    'Seaweed (Nori)' => 1,
                    'White Onion' => 37.5,
                    'Spring Onion' => 8,
                    'Miso Paste' => 11,
                    'Shichimi Togarashi (Base)' => 2,
                    'Sea Salt' => 0.5,
                    'Black Pepper' => 0.3,
                    'Olive Oil (Extra Virgin)' => 7,
                    'Sesame Oil' => 1,
                    'Vegetable Broth (Base)' => 200,
                ],
                array_merge($wholeFoodTags, ['Vegan']),
                'Golden carrot-ginger miso soup with roasted nori, scallions, sesame oil, and shichimi togarashi.',
            ),
            BalancedMealLibraryConfigurator::BONE_BROTH_MEAL_NAME => [
                'ingredients' => [
                    BoneBrothBaseRecipe::NAME => BalancedMealLibraryConfigurator::BONE_BROTH_SERVING_GRAMS
                        * BalancedMealLibraryConfigurator::BONE_BROTH_BATCH_SERVINGS_COUNT,
                ],
                'is_bulk' => true,
                'servings_count' => BalancedMealLibraryConfigurator::BONE_BROTH_BATCH_SERVINGS_COUNT,
                'diet_tags' => $wholeFoodTags,
                'short_description' => '500 ml cup of fully defatted house bone broth — long-simmered, gelatin-rich collagen broth from cracked beef leg bones.',
            ],
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
