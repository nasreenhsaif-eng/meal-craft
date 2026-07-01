<?php

namespace App\Services;

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Support\MealLibraryEditGuard;
use App\Support\StandardMeatPortion;
use App\Support\WholeFoodDietPolicy;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Ensures rotation mains missing from meals.csv exist in the meal library before canonical refinement.
 */
final class NutrientDenseLiverMealRecipeRefiner
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
                    $definition['short_description'] ?? null,
                );

                $updated[] = $mealName;
            }

            return $updated;
        });
    }

    /**
     * @param  array{ingredients: array<string, float>, diet_tags?: list<string>, short_description?: string, meal_type?: MealType, category?: RecipeCategory}  $definition
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
            'meal_plan_tags' => ['Balanced', 'NutrientDense'],
            'meal_plan_tag' => 'NutrientDense',
            'diet_tags' => $definition['diet_tags'] ?? WholeFoodDietPolicy::REQUIRED_MEAL_DIET_TAGS,
            'short_description' => $definition['short_description'] ?? null,
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
        ?string $shortDescription = null,
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

        $meal->update([
            'diet_tags' => $dietTags,
            'short_description' => $shortDescription ?? $meal->short_description,
            'nutrition_aggregates_synced' => true,
            ...Meal::nutritionSummaryToPersistedAttributes(RecipeNutritionCalculator::fromMeal($meal->fresh(['ingredients']))),
        ]);
    }

    /**
     * @return array<string, array{ingredients: array<string, float>, diet_tags?: list<string>, short_description?: string, meal_type?: MealType, category?: RecipeCategory}>
     */
    private function recipeDefinitions(): array
    {
        $tags = WholeFoodDietPolicy::REQUIRED_MEAL_DIET_TAGS;
        $veganTags = array_merge($tags, ['Vegan']);

        return [
            BalancedCanonicalMealRecipeRefiner::ROSEMARY_GARLIC_CHICKEN_PLATE_NAME => [
                'ingredients' => [
                    'Rosemary Garlic Chicken (Base)' => StandardMeatPortion::GRAMS,
                    'Sweet Potato' => 85.0,
                    'Spinach (Fresh)' => 55.0,
                    'Mushrooms' => 45.0,
                    'Rosemary (Fresh)' => 2.0,
                    'Garlic (Raw)' => 4.0,
                    'Olive Oil (Extra Virgin)' => 5.0,
                    'Black Pepper' => 0.5,
                ],
                'diet_tags' => $tags,
                'short_description' => 'Grilled rosemary garlic chicken with sautéed mushrooms and spinach over roasted sweet potato wedges.',
            ],
            'Seared Beef Liver w Roasted Beetroot, Chard & Chimichurri' => [
                'ingredients' => [
                    'Beef Liver' => StandardMeatPortion::GRAMS,
                    'Beetroot' => 70.0,
                    'Black Pepper' => 0.5,
                    'Cabbage (Purple)' => 55.0,
                    'Carrots' => 45.0,
                    'Chard' => 80.0,
                    'Fermented Chimichurri (Base)' => 18.0,
                    'Garlic (Raw)' => 4.0,
                    'Lemon Juice' => 8.0,
                    'Olive Oil (Extra Virgin)' => 5.0,
                    'Steamed Basmati Rice (Base)' => 40.0,
                    'White Onion' => 35.0,
                ],
                'diet_tags' => $tags,
                'short_description' => 'Quick-seared beef liver with roasted beetroot, wilted chard, and fermented chimichurri.',
            ],
            'Sautéed Chicken Liver w Garlicky Cabbage, Bok Choy & Peppers' => [
                'ingredients' => [
                    'Bell Pepper (Red)' => 50.0,
                    'Black Pepper' => 0.5,
                    'Bok Choy' => 75.0,
                    'Cabbage (Purple)' => 85.0,
                    'Cherry Tomatoes' => 35.0,
                    'Chicken Liver' => StandardMeatPortion::GRAMS,
                    'Garlic (Raw)' => 5.0,
                    'Nutmeg' => 0.2,
                    'Olive Oil (Extra Virgin)' => 6.0,
                    'Oregano' => 1.0,
                    'Pomegranate Molasses' => 10.0,
                    'Quinoa Flatbread (Base)' => 35.0,
                    'Red Onion' => 35.0,
                    'Rocca' => 40.0,
                    'Sea Salt' => 0.5,
                ],
                'diet_tags' => $tags,
                'short_description' => 'Pan-sautéed chicken liver with garlicky cabbage, bok choy, and peppers.',
            ],
            'Carrot Cumin Soup' => [
                'ingredients' => [
                    'Carrots' => 150.0,
                    'French Lentils' => 70.0,
                    'Cumin Seeds' => 3.0,
                    'Coriander Seeds' => 2.0,
                    'Water (Filtered)' => 130.0,
                    'Vegetable Broth (Base)' => 50.0,
                    'White Onion' => 35.0,
                    'Garlic' => 4.0,
                    'Olive Oil' => 5.0,
                    'Fresh Parsley' => 5.0,
                    'Lemon Juice' => 8.0,
                ],
                'diet_tags' => $veganTags,
                'meal_type' => MealType::Soup,
                'category' => RecipeCategory::Soup,
                'short_description' => 'Hearty carrot and French lentil soup with cumin and fresh parsley.',
            ],
        ];
    }
}
