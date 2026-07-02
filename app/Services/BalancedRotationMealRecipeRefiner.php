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
 * Rewrites Balanced weekly rotation meals not owned by other refiners — fixing missing
 * signature ingredients, banned pantry shortcuts, and whole-food dessert recipes.
 */
final class BalancedRotationMealRecipeRefiner
{
    public const ROASTED_POMEGRANATE_CHICKEN_NAME = 'Grilled Sumac Chicken Skewers w Zereshk & Turmeric Rice & Roasted Mixed Vegetables';

    public const CHOCOLATE_ORANGE_BROWNIE_NAME = 'Chocolate Orange Brownie';

    public const CHOCOLATE_ORANGE_BROWNIE_SERVINGS_COUNT = 16;

    /** One US cup psyllium husks in the full batch (240 g at library density 1.0 g/ml). */
    public const CHOCOLATE_ORANGE_BROWNIE_PSYLLIUM_BATCH_GRAMS = 240.0;

    public const SALTED_TAHINI_CARAMEL_CHOCOLATE_BAR_NAME = 'Salted Tahini Caramel Chocolate Bar';

    public const SALTED_CARAMEL_CHOCOLATE_BAR_SERVINGS_COUNT = 16;

    public const APPLE_PIE_BALLS_PER_SERVING_COUNT = 3;

    public const BANANA_BLUEBERRY_BALLS_PER_SERVING_COUNT = 3;

    public const CHOCOLATE_PB_BANANA_MUFFIN_BATCH_SERVINGS_COUNT = 6;

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
                $meal = $this->resolveMealForRefinement($mealName);

                if ($meal === null) {
                    continue;
                }

                if (MealLibraryEditGuard::shouldSkipMealRefinement($meal)) {
                    continue;
                }

                $this->syncMeal(
                    $meal,
                    $definition['ingredients'],
                    $definition['diet_tags'] ?? WholeFoodDietPolicy::REQUIRED_MEAL_DIET_TAGS,
                    $definition['short_description'] ?? null,
                    $definition['is_bulk'] ?? null,
                    $definition['servings_count'] ?? null,
                    $mealName,
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
    private function syncMeal(
        Meal $meal,
        array $ingredientGrams,
        array $dietTags,
        ?string $shortDescription = null,
        ?bool $isBulk = null,
        ?float $servingsCount = null,
        ?string $canonicalMealName = null,
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
                    'diet_tags' => $dietTags,
                ],
            );
        } else {
            $update = array_merge(
                Meal::nutritionSummaryToPersistedAttributes($batchNutrition),
                [
                    'nutrition_aggregates_synced' => true,
                    'diet_tags' => $dietTags,
                ],
            );
        }

        if ($shortDescription !== null) {
            $update['short_description'] = $shortDescription;
        }

        if ($canonicalMealName !== null && $meal->name !== $canonicalMealName) {
            $update['name'] = $canonicalMealName;
        }

        $meal->update($update);

        MealRecipeAsIngredientSyncService::syncFromPersistedMeal($fresh->fresh(['ingredients']), false);

        $violations = WholeFoodDietPolicy::violationsForMeal($meal->fresh(['ingredients']));

        if ($violations !== []) {
            throw new InvalidArgumentException(implode('; ', $violations));
        }
    }

    private function resolveMealForRefinement(string $mealName): ?Meal
    {
        if ($mealName === self::CHOCOLATE_ORANGE_BROWNIE_NAME) {
            return Meal::queryForMealLibrary()
                ->whereIn('name', [self::CHOCOLATE_ORANGE_BROWNIE_NAME, self::CHOCOLATE_ORANGE_BROWNIE_NAME.' (N)'])
                ->first();
        }

        if ($mealName === self::SALTED_TAHINI_CARAMEL_CHOCOLATE_BAR_NAME) {
            return Meal::queryForMealLibrary()
                ->whereIn('name', [
                    self::SALTED_TAHINI_CARAMEL_CHOCOLATE_BAR_NAME,
                    'Salted Caramel Chocolate Bar',
                ])
                ->first();
        }

        if ($mealName === self::ROASTED_POMEGRANATE_CHICKEN_NAME) {
            return Meal::queryForMealLibrary()
                ->whereIn('name', [
                    self::ROASTED_POMEGRANATE_CHICKEN_NAME,
                    'Roasted Chicken in Pomegranate & Sumac Sauce w Turmeric Rice',
                ])
                ->first();
        }

        return Meal::queryForMealLibrary()->where('name', $mealName)->first();
    }

    /**
     * Batch yields {@see CHOCOLATE_ORANGE_BROWNIE_SERVINGS_COUNT} squares.
     * Volumes: 1½ cups dates, ⅓ cup water, zest of 2 oranges, 2 tbsp OJ; ¾ cup butter & cocoa, 3 eggs;
     * ½ cup almond flour, ¼ cup tapioca, 1 cup psyllium husks, ¼ tsp baking powder & salt.
     *
     * @return array<string, float>
     */
    private function chocolateOrangeBrowniePerServingIngredients(): array
    {
        return [
            'Medjool Dates' => 16.6875,
            'Water (Filtered)' => 4.9375,
            'Orange Zest' => 0.75,
            'Orange Juice' => 1.9375,
            'Grass Fed Butter' => 10.625,
            'Cocoa Powder' => 4.6875,
            'Eggs (Large)' => 9.375,
            'Almond Flour (Base)' => 6,
            'Tapioca Starch' => 1.875,
            'Psyllium Husks' => self::CHOCOLATE_ORANGE_BROWNIE_PSYLLIUM_BATCH_GRAMS / self::CHOCOLATE_ORANGE_BROWNIE_SERVINGS_COUNT,
            'Baking Powder' => 0.0781,
            'Sea Salt' => 0.0938,
        ];
    }

    /**
     * @return array<string, float>
     */
    private function chocolateOrangeBrownieBatchIngredients(): array
    {
        return $this->scaleIngredientGrams(
            $this->chocolateOrangeBrowniePerServingIngredients(),
            self::CHOCOLATE_ORANGE_BROWNIE_SERVINGS_COUNT,
        );
    }

    /**
     * Per-serving grams for a 16-square 8x8 batch (see {@see saltedTahiniCaramelChocolateBarBatchIngredients()}).
     *
     * @return array<string, float>
     */
    private function saltedTahiniCaramelChocolateBarPerServingIngredients(): array
    {
        return [
            'Almond Flour (Base)' => 9,
            'Coconut Oil' => 6.8,
            'Date Syrup' => 9,
            'Tahini' => 7.5,
            'Cocoa Powder' => 3.8,
            'Sea Salt' => 0.6,
            'Vanilla Pods' => 0.1,
        ];
    }

    /**
     * @return array<string, float>
     */
    private function saltedTahiniCaramelChocolateBarBatchIngredients(): array
    {
        return $this->scaleIngredientGrams(
            $this->saltedTahiniCaramelChocolateBarPerServingIngredients(),
            self::SALTED_CARAMEL_CHOCOLATE_BAR_SERVINGS_COUNT,
        );
    }

    /**
     * @param  array<string, float>  $ingredientGrams
     * @return array<string, float>
     */
    private function scaleIngredientGrams(array $ingredientGrams, float $factor): array
    {
        $scaled = [];

        foreach ($ingredientGrams as $ingredientName => $grams) {
            $scaled[$ingredientName] = round((float) $grams * $factor, 4);
        }

        return $scaled;
    }

    /**
     * @return array<string, array{
     *     ingredients: array<string, float>,
     *     diet_tags?: list<string>,
     *     short_description?: string,
     *     is_bulk?: bool,
     *     servings_count?: float
     * }>
     */
    private function recipeDefinitions(): array
    {
        $tags = WholeFoodDietPolicy::REQUIRED_MEAL_DIET_TAGS;
        $spicyTags = array_merge($tags, ['Spicy']);
        $veganTags = array_merge($tags, ['Vegan']);
        $vegetarianTags = array_merge($tags, ['Vegetarian']);

        $definitions = [
            'Spicy Harissa Grilled Chicken w Roasted Sweet Potato & Zucchini' => [
                'ingredients' => [
                    'Chicken Breast' => StandardMeatPortion::GRAMS,
                    'Harissa Paste (Base)' => 6.3,
                    'Sweet Potato' => 90,
                    'Zucchini' => 80,
                    'Garlic (Raw)' => 3,
                    'Fresh Mint' => 5,
                    'Olive Oil (Extra Virgin)' => 4,
                    'Lemon Juice' => 10,
                    'Black Pepper' => 0.5,
                ],
                'diet_tags' => $spicyTags,
            ],
            self::ROASTED_POMEGRANATE_CHICKEN_NAME => [
                'ingredients' => [
                    'Chicken Breast' => StandardMeatPortion::GRAMS,
                    'Pomegranate Sumac Sauce (Base)' => 40,
                    'Red Onion' => 30,
                    'Turmeric Rice (Base)' => 70,
                    'Barberries' => 5,
                    'Roasted Mixed Vegetables (Base)' => 85,
                    'Parsley' => 5,
                    'Sumac' => 2,
                    'Garlic (Raw)' => 3,
                    'Turmeric Powder' => 1,
                    'Thyme (Fresh)' => 2,
                ],
                'diet_tags' => $tags,
                'short_description' => 'Grilled sumac-marinated chicken skewers roasted over red onion with zereshk turmeric rice and house roasted mixed vegetables.',
            ],
            'Pepper Chicken in Creamy Cajun Sauce w Roasted Potato' => [
                'ingredients' => [
                    'Chicken Breast' => StandardMeatPortion::GRAMS,
                    'Potato' => 90,
                    'Bell Pepper (Red)' => 40,
                    'Cherry Tomatoes' => 30,
                    'Red Onion' => 15,
                    'Garlic (Raw)' => 4,
                    'Homemade Coconut Milk' => 25,
                    'Cajun Spice (Base)' => 3,
                    'Smoked Paprika' => 1,
                    'Fresh Parsley' => 3,
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
                    'Quinoa Flatbread (Base)' => 45,
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
                    'Dates (Khelas)' => 16,
                    'Almond Flour' => 7,
                    'Apple Green' => 10,
                    'Almond Butter' => 5,
                    'Walnuts' => 4,
                    'Cinnamon' => 1,
                ],
                'diet_tags' => $vegetarianTags,
                'short_description' => 'No-bake apple-cinnamon balls with khelas dates, almond flour, walnuts, and almond butter — '.self::APPLE_PIE_BALLS_PER_SERVING_COUNT.' small bites per serving (~150 kcal).',
            ],
            'Banana Blueberry Balls' => [
                'ingredients' => [
                    'Almond Flour' => 16,
                    'Flaxseeds' => 2,
                    'Cinnamon' => 0.1,
                    'Maple Syrup' => 12,
                    'Almond Butter' => 7,
                    'Banana' => 11,
                    'Blueberries' => 9,
                ],
                'diet_tags' => $vegetarianTags,
                'short_description' => 'No-bake banana-blueberry energy balls with almond flour, flaxseed, maple syrup, and almond butter — '.self::BANANA_BLUEBERRY_BALLS_PER_SERVING_COUNT.' bites per serving (~193 kcal).',
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
                    'Almond Flour' => 80,
                    'Banana' => 120,
                    'Cocoa Powder' => 20,
                    'Maple Syrup' => 30,
                    'Peanut Butter' => 15,
                    'Egg Whites' => 50,
                    'Baking Soda' => 2.3,
                    'Sea Salt' => 0.5,
                ],
                'is_bulk' => true,
                'servings_count' => self::CHOCOLATE_PB_BANANA_MUFFIN_BATCH_SERVINGS_COUNT,
                'diet_tags' => $vegetarianTags,
                'short_description' => 'Grain-free cocoa-banana muffin batch ('.self::CHOCOLATE_PB_BANANA_MUFFIN_BATCH_SERVINGS_COUNT.' muffins) with almond flour, peanut butter, maple syrup, and egg whites — one muffin per serving (~135 kcal).',
            ],
            self::CHOCOLATE_ORANGE_BROWNIE_NAME => [
                'ingredients' => $this->chocolateOrangeBrownieBatchIngredients(),
                'is_bulk' => true,
                'servings_count' => self::CHOCOLATE_ORANGE_BROWNIE_SERVINGS_COUNT,
                'diet_tags' => $vegetarianTags,
                'short_description' => 'Rich grain-free cocoa-orange brownie batch ('.self::CHOCOLATE_ORANGE_BROWNIE_SERVINGS_COUNT.' small squares) with date-orange sweetener, Dutch cocoa, grass-fed butter, blanched almond flour, tapioca, and psyllium husks for fiber.',
            ],
            self::SALTED_TAHINI_CARAMEL_CHOCOLATE_BAR_NAME => [
                'ingredients' => $this->saltedTahiniCaramelChocolateBarBatchIngredients(),
                'is_bulk' => true,
                'servings_count' => self::SALTED_CARAMEL_CHOCOLATE_BAR_SERVINGS_COUNT,
                'diet_tags' => $vegetarianTags,
                'short_description' => 'Three-layer 8x8 no-bake bar (16 squares): almond shortbread, salted tahini-date caramel, and dark cocoa topping.',
            ],
        ];

        return MealLibraryRefinerOverrides::mergeRecipeDefinitionMap($definitions);
    }
}
