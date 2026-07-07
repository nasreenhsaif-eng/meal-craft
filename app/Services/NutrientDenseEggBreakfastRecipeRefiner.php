<?php

namespace App\Services;

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Support\MealLibraryEditGuard;
use App\Support\MealLibraryRefinerOverrides;
use App\Support\WholeFoodDietPolicy;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Whole-food egg breakfast recipes for the NutrientDense weekly rotation (slot 2).
 */
final class NutrientDenseEggBreakfastRecipeRefiner
{
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
    public function refine(?array $onlyMealNames = null): array
    {
        return DB::transaction(function () use ($onlyMealNames): array {
            $updated = [];
            $only = $onlyMealNames !== null ? array_flip($onlyMealNames) : null;

            foreach ($this->recipeDefinitions() as $mealName => $definition) {
                if ($only !== null && ! isset($only[$mealName])) {
                    continue;
                }

                $meal = $this->ensureMealExists($mealName, $definition);

                if (MealLibraryEditGuard::shouldSkipMealRefinement($meal)) {
                    continue;
                }

                $this->syncMeal(
                    $meal,
                    $definition['ingredients'],
                    $definition['diet_tags'] ?? WholeFoodDietPolicy::REQUIRED_MEAL_DIET_TAGS,
                    $definition['highlight'] ?? null,
                    $definition['food_filter_tags'] ?? null,
                );
                $updated[] = $mealName;
            }

            return $updated;
        });
    }

    /**
     * @param  list<string>  $mealNames
     * @return list<string>
     */
    public function ensureMealsExist(array $mealNames): array
    {
        if ($mealNames === []) {
            return [];
        }

        $only = array_flip($mealNames);
        $created = [];

        foreach ($this->recipeDefinitions() as $mealName => $definition) {
            if (! isset($only[$mealName])) {
                continue;
            }

            if (Meal::queryForMealLibrary()->where('name', $mealName)->exists()) {
                continue;
            }

            $this->ensureMealExists($mealName, $definition);
            $created[] = $mealName;
        }

        return $created;
    }

    /**
     * @param  array{ingredients: array<string, float>, diet_tags?: list<string>, highlight?: string, food_filter_tags?: list<string>}  $definition
     */
    private function ensureMealExists(string $mealName, array $definition): Meal
    {
        $existing = Meal::queryForMealLibrary()->where('name', $mealName)->first();

        if ($existing instanceof Meal) {
            return $existing;
        }

        $dietTags = $definition['diet_tags'] ?? WholeFoodDietPolicy::REQUIRED_MEAL_DIET_TAGS;
        $highlight = isset($definition['highlight']) ? trim((string) $definition['highlight']) : null;

        return Meal::query()->create([
            'name' => $mealName,
            'category' => RecipeCategory::Breakfast,
            'meal_type' => MealType::Breakfast,
            'meal_plan_tags' => ['NutrientDense'],
            'meal_plan_tag' => 'NutrientDense',
            'diet_tags' => $dietTags === [] ? null : $dietTags,
            'food_filter_tags' => $definition['food_filter_tags'] ?? null,
            'short_description' => $highlight !== '' ? $highlight : null,
            'highlight' => $highlight !== '' ? $highlight : null,
            'library_sort_order' => Meal::nextLibrarySortOrder(),
            'nutrition_aggregates_synced' => false,
        ]);
    }

    /**
     * @param  array<string, float>  $ingredientGrams
     * @param  list<string>  $dietTags
     */
    private function syncMeal(
        Meal $meal,
        array $ingredientGrams,
        array $dietTags,
        ?string $highlight = null,
        ?array $foodFilterTags = null,
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
            $foodFilterTags !== null ? ['food_filter_tags' => $foodFilterTags] : [],
            $highlight !== null && trim($highlight) !== '' ? [
                'short_description' => trim($highlight),
                'highlight' => trim($highlight),
            ] : [],
        ));

        MealRecipeAsIngredientSyncService::syncFromPersistedMeal($fresh->fresh(['ingredients']), false);
    }

    /**
     * @return array<string, array{ingredients: array<string, float>, diet_tags?: list<string>, highlight?: string, food_filter_tags?: list<string>}>
     */
    private function recipeDefinitions(): array
    {
        $tags = array_merge(WholeFoodDietPolicy::REQUIRED_MEAL_DIET_TAGS, ['Vegetarian']);
        $dairyTags = ['dairy', 'eggs'];

        $definitions = [
            'Gouda & Spinach Scramble' => [
                'ingredients' => [
                    'Egg' => 100,
                    'Gouda' => 40,
                    'Spinach (Fresh)' => 50,
                    'Grass Fed Butter' => 10,
                    'Black Pepper' => 1,
                ],
                'highlight' => 'Melted gouda folded through softly scrambled eggs with wilted spinach.',
                'diet_tags' => $tags,
                'food_filter_tags' => $dairyTags,
            ],
            'Greek Yogurt & Parmesan Frittata' => [
                'ingredients' => [
                    'Egg' => 100,
                    'Greek Yogurt' => 40,
                    'Parmesan' => 15,
                    'Spinach (Fresh)' => 30,
                    'Bell Pepper (Red)' => 30,
                    'Olive Oil' => 5,
                    'Sea Salt' => 1,
                    'Black Pepper' => 1,
                ],
                'highlight' => 'Fluffy oven-baked frittata with Greek yogurt for extra protein and parmesan finish.',
                'diet_tags' => $tags,
                'food_filter_tags' => $dairyTags,
            ],
            'Feta & Herb Open Omelet' => [
                'ingredients' => [
                    'Egg' => 110,
                    'Feta' => 35,
                    'Spinach (Fresh)' => 30,
                    'Bell Pepper (Red)' => 25,
                    'Dill (Fresh)' => 5,
                    'Olive Oil' => 10,
                    'Black Pepper' => 1,
                ],
                'highlight' => 'Open omelet with crumbled feta, peppers, spinach, and fresh dill.',
                'diet_tags' => $tags,
                'food_filter_tags' => $dairyTags,
            ],
            'Brie & Mushroom Skillet Eggs' => [
                'ingredients' => [
                    'Egg' => 100,
                    'Brie' => 30,
                    'Mushrooms' => 60,
                    'White Onion' => 20,
                    'Olive Oil' => 10,
                    'Thyme (Fresh)' => 2,
                    'Black Pepper' => 1,
                ],
                'highlight' => 'Sautéed mushrooms and brie with skillet eggs and thyme.',
                'diet_tags' => $tags,
                'food_filter_tags' => $dairyTags,
            ],
            'Parmesan Shakshuka' => [
                'ingredients' => [
                    'Egg' => 110,
                    'Tomato (Raw)' => 150,
                    'Bell Pepper (Red)' => 40,
                    'White Onion' => 30,
                    'Garlic (Raw)' => 4,
                    'Olive Oil' => 5,
                    'Parmesan' => 20,
                    'Smoked Paprika' => 1,
                ],
                'highlight' => 'Poached eggs in a tomato-pepper skillet finished with grated parmesan.',
                'diet_tags' => $tags,
                'food_filter_tags' => $dairyTags,
            ],
            'Halloumi Egg Stack' => [
                'ingredients' => [
                    'Egg' => 100,
                    'Halloumi' => 35,
                    'Greek Yogurt' => 30,
                    'Spinach (Fresh)' => 30,
                    'Cherry Tomatoes' => 30,
                    'Olive Oil' => 5,
                    'Black Pepper' => 1,
                ],
                'highlight' => 'Poached eggs on grilled halloumi with lemony Greek yogurt and wilted spinach.',
                'diet_tags' => $tags,
                'food_filter_tags' => $dairyTags,
            ],
            'Feta & Dill Egg Muffins' => [
                'ingredients' => [
                    'Egg' => 110,
                    'Feta' => 30,
                    'Spinach (Fresh)' => 25,
                    'Dill (Fresh)' => 5,
                    'Spring Onion' => 15,
                    'Olive Oil' => 5,
                    'Sea Salt' => 1,
                    'Black Pepper' => 1,
                ],
                'highlight' => 'Baked feta and dill egg muffins — three protein-forward bites per serving.',
                'diet_tags' => $tags,
                'food_filter_tags' => $dairyTags,
            ],
            'Deconstructed Shakshuka Skillet' => [
                'ingredients' => [
                    'Egg' => 110,
                    'Tomato (Raw)' => 150,
                    'Bell Pepper (Red)' => 40,
                    'White Onion' => 30,
                    'Garlic (Raw)' => 4,
                    'Olive Oil' => 5,
                    'Fresh Coriander' => 4,
                    'Smoked Paprika' => 1,
                ],
                'diet_tags' => $tags,
                'food_filter_tags' => ['eggs'],
            ],
            'Hummus Egg Stack' => [
                'ingredients' => [
                    'Creamy Cumin Hummus (Base)' => 100,
                    'Egg' => 100,
                    'Spinach (Fresh)' => 45,
                    'Cherry Tomatoes' => 45,
                    'Cucumber' => 40,
                    'Olive Oil' => 5,
                    'Black Pepper' => 1,
                ],
                'highlight' => 'Soft-boiled eggs stacked over sautéed spinach and cherry tomatoes on a bed of creamy house cumin hummus.',
                'diet_tags' => $tags,
                'food_filter_tags' => ['eggs'],
            ],
            'Kuku Sabzi Egg Muffins' => [
                'ingredients' => [
                    'Egg' => 110,
                    'Spinach (Fresh)' => 30,
                    'Fresh Coriander' => 8,
                    'Dill (Fresh)' => 4,
                    'Spring Onion' => 15,
                    'Walnuts' => 5,
                    'Barberries' => 5,
                    'Olive Oil' => 5,
                    'Sea Salt' => 1,
                    'Black Pepper' => 1,
                ],
                'highlight' => 'Traditional Persian-style baked egg muffins packed with minced fresh herbs, walnuts, barberries (zereshk), and seasoning.',
                'diet_tags' => $tags,
                'food_filter_tags' => ['eggs', 'nuts'],
            ],
            'Sweet Potato Egg Hash' => [
                'ingredients' => [
                    'Egg' => 100,
                    'Sweet Potato' => 90,
                    'Bell Pepper (Red)' => 30,
                    'White Onion' => 25,
                    'Spinach (Fresh)' => 45,
                    'Olive Oil' => 10,
                    'Rosemary (Fresh)' => 2,
                    'Thyme (Fresh)' => 2,
                    'Sea Salt' => 1,
                    'Black Pepper' => 1,
                    'Fresh Coriander' => 3,
                    'Flaxseeds' => 5,
                ],
                'highlight' => 'Rosemary-thyme roasted sweet potato hash with sautéed onion, pepper, and spinach, finished with softly scrambled eggs and flaxseeds.',
                'diet_tags' => $tags,
                'food_filter_tags' => ['eggs'],
            ],
            'Butternut Squash Frittata' => [
                'ingredients' => [
                    'Butternut Squash' => 200,
                    'Eggs (Large)' => 200,
                    'Gruyere Cheese' => 35,
                    'Chickpea Flour' => 15,
                    'Paprika' => 1,
                    'Sea Salt' => 1,
                    'Dill (Fresh)' => 8,
                    'Red Onion' => 35,
                    'Greek Yogurt' => 40,
                    'Olive Oil' => 10,
                    'Marinara Sauce (Base)' => 80,
                ],
                'highlight' => 'Roasted butternut squash frittata with gruyère, chickpea flour, dill, and Greek yogurt — topped with fried eggs and warm marinara on the side.',
                'diet_tags' => $tags,
                'food_filter_tags' => array_merge($dairyTags, ['nightshades', 'beans']),
            ],
            'Butternut Squash & Eggs' => [
                'ingredients' => [
                    'Butternut Squash' => 200,
                    'Eggs (Large)' => 200,
                    'Chickpea Flour' => 15,
                    'Paprika' => 1,
                    'Sea Salt' => 1,
                    'Dill (Fresh)' => 8,
                    'Red Onion' => 35,
                    'Olive Oil' => 10,
                    'Marinara Sauce (Base)' => 80,
                ],
                'highlight' => 'Roasted butternut squash baked with eggs, chickpea flour, and dill — topped with fried eggs and warm marinara on the side.',
                'diet_tags' => array_merge(WholeFoodDietPolicy::REQUIRED_MEAL_DIET_TAGS, ['Vegetarian', 'Gluten-Free']),
                'food_filter_tags' => ['eggs', 'nightshades', 'beans'],
            ],
            'Smashed Beans & Eggs' => [
                'ingredients' => [
                    'Smashed White Beans (Base)' => 80,
                    'Egg' => 100,
                    'Tomato (Raw)' => 50,
                    'Fresh Coriander' => 4,
                    'Olive Oil' => 5,
                ],
                'diet_tags' => $tags,
                'food_filter_tags' => ['eggs', 'beans'],
            ],
        ];

        return MealLibraryRefinerOverrides::mergeRecipeDefinitionMap($definitions);
    }
}
