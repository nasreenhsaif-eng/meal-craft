<?php

namespace App\Services;

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Support\MealLibraryEditGuard;
use App\Support\NutrientDenseFermentedPortionCaps;
use App\Support\WholeFoodDietPolicy;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates nutrient-dense fermented-anchor meals: miso soups, kimchi/sauerkraut salads, kefir breakfast, fish mains.
 */
final class NutrientDenseFermentedRecipeRefiner
{
    public const SARDINE_MAIN_NAME = 'Sardine & Roasted Pepper Salad';

    public const MACKEREL_PLATE_NAME = 'Grilled Mackerel w Roasted Vegetables';

    public const MACKEREL_QUINOA_NAME = 'Grilled Mackerel w Lemon Herb Quinoa';

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
    public function refine(): array
    {
        return DB::transaction(function (): array {
            $updated = [];

            foreach ($this->recipeDefinitions() as $mealName => $definition) {
                $meal = $this->ensureMealExists($mealName, $definition);

                if (MealLibraryEditGuard::shouldSkipMealRefinement($meal)) {
                    continue;
                }

                $this->syncMeal(
                    $meal,
                    $definition['ingredients'],
                    $definition['diet_tags'] ?? WholeFoodDietPolicy::REQUIRED_MEAL_DIET_TAGS,
                    $definition['highlight'] ?? null,
                );
                $updated[] = $mealName;
            }

            return $updated;
        });
    }

    /**
     * @param  array{ingredients: array<string, float>, diet_tags?: list<string>, highlight?: string, meal_type?: MealType, category?: RecipeCategory}  $definition
     */
    private function ensureMealExists(string $mealName, array $definition): Meal
    {
        $existing = Meal::queryForMealLibrary()->where('name', $mealName)->first();

        if ($existing instanceof Meal) {
            return $existing;
        }

        return Meal::query()->create([
            'name' => $mealName,
            'category' => $definition['category'] ?? RecipeCategory::Meal,
            'meal_type' => $definition['meal_type'] ?? MealType::Main,
            'meal_plan_tags' => ['NutrientDense'],
            'meal_plan_tag' => 'NutrientDense',
            'diet_tags' => $definition['diet_tags'] ?? WholeFoodDietPolicy::REQUIRED_MEAL_DIET_TAGS,
            'short_description' => $definition['highlight'] ?? null,
            'highlight' => $definition['highlight'] ?? null,
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
    ): void {
        $sync = [];

        foreach ($ingredientGrams as $ingredientName => $grams) {
            if ($grams <= 0) {
                continue;
            }

            $cap = NutrientDenseFermentedPortionCaps::capGramsForIngredient($ingredientName);

            if ($cap !== null) {
                $grams = min($grams, $cap);
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
                'meal_plan_tags' => ['NutrientDense'],
                'meal_plan_tag' => 'NutrientDense',
            ],
            $highlight !== null && trim($highlight) !== '' ? [
                'short_description' => trim($highlight),
                'highlight' => trim($highlight),
            ] : [],
        ));

        MealRecipeAsIngredientSyncService::syncFromPersistedMeal($fresh->fresh(['ingredients']), false);
    }

    /**
     * @return array<string, array{ingredients: array<string, float>, diet_tags?: list<string>, highlight?: string}>
     */
    private function recipeDefinitions(): array
    {
        $veganTags = array_merge(WholeFoodDietPolicy::REQUIRED_MEAL_DIET_TAGS, ['Vegan']);
        $fishTags = WholeFoodDietPolicy::REQUIRED_MEAL_DIET_TAGS;
        $breakfastTags = array_merge(WholeFoodDietPolicy::REQUIRED_MEAL_DIET_TAGS, ['Vegetarian']);

        return [
            'Kimchi Purslane Side Salad' => [
                'category' => RecipeCategory::SideSalad,
                'meal_type' => MealType::Salad,
                'ingredients' => [
                    'Purslane' => 60,
                    'Bok Choy' => 40,
                    'Kimchi (Base)' => 30,
                    'Tahini Miso Garlic Ginger Rice Vinegar Dressing (Base)' => 20,
                ],
                'diet_tags' => $veganTags,
                'highlight' => 'Homemade fermented kimchi with purslane and bok choy, tahini-miso dressing.',
            ],
            'Tahini Purslane Pepper Salad' => [
                'category' => RecipeCategory::SideSalad,
                'meal_type' => MealType::Salad,
                'ingredients' => [
                    'Purslane' => 50,
                    'Bell Pepper (Red)' => 50,
                    'Rocca' => 30,
                    'Tahini' => 18,
                    'Lemon Juice' => 8,
                ],
                'diet_tags' => $veganTags,
                'highlight' => 'Calcium-rich tahini dressing over purslane and pepper.',
            ],
            'Sauerkraut & Rocca Salad' => [
                'category' => RecipeCategory::SideSalad,
                'meal_type' => MealType::Salad,
                'ingredients' => [
                    'Sauerkraut' => 40,
                    'Rocca' => 50,
                    'Bell Pepper (Red)' => 35,
                    'Olive Oil' => 8,
                    'Lemon Juice' => 8,
                ],
                'diet_tags' => $veganTags,
                'highlight' => 'Fermented cabbage with peppery rocca and lemon.',
            ],
            'Kefir Herb Egg Bowl' => [
                'category' => RecipeCategory::Breakfast,
                'meal_type' => MealType::Breakfast,
                'ingredients' => [
                    'Egg' => 110,
                    'Kefir' => 100,
                    'Spinach (Fresh)' => 40,
                    'Rocca' => 20,
                    'Olive Oil' => 8,
                    'Black Pepper' => 1,
                ],
                'diet_tags' => $breakfastTags,
                'highlight' => 'Whole eggs with kefir, greens, and herbs — daily fermented breakfast anchor.',
            ],
            self::SARDINE_MAIN_NAME => [
                'ingredients' => [
                    'Sardines (Canned)' => 100,
                    'Bell Pepper (Red)' => 60,
                    'Rocca' => 40,
                    'Cherry Tomatoes' => 40,
                    'Olive Oil' => 10,
                    'Lemon Juice' => 8,
                ],
                'diet_tags' => $fishTags,
                'highlight' => 'Omega-3 sardines with roasted pepper salad — vitamin D and B12.',
            ],
            self::MACKEREL_PLATE_NAME => [
                'ingredients' => [
                    'Mackerel' => 140,
                    'Zucchini' => 80,
                    'Bell Pepper (Red)' => 60,
                    'Olive Oil' => 10,
                    'Lemon Juice' => 8,
                    'Fermented Chimichurri (Base)' => 20,
                ],
                'diet_tags' => $fishTags,
                'highlight' => 'Grilled mackerel with roasted vegetables and fermented chimichurri.',
            ],
            self::MACKEREL_QUINOA_NAME => [
                'ingredients' => [
                    'Mackerel' => 140,
                    'Cooked Quinoa (Base)' => 80,
                    'Parsley' => 10,
                    'Lemon Juice' => 10,
                    'Olive Oil' => 10,
                ],
                'diet_tags' => $fishTags,
                'highlight' => 'Mackerel over lemon herb quinoa — dense in D and B12.',
            ],
        ];
    }
}
