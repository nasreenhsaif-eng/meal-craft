<?php

namespace App\Services;

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Support\MealLibraryEditGuard;
use App\Support\NutrientDenseFermentedPortionCaps;
use App\Support\StandardMeatPortion;
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

    public const MACKEREL_PLATE_IMAGE = 'images/meals/grilled_mackerel_roasted_vegetables.png';

    public const MACKEREL_QUINOA_NAME = 'Grilled Mackerel w Lemon Herb Quinoa';

    public const TAHINI_PURSLANE_PEPPER_SALAD_NAME = 'Tahini Purslane Pepper Salad';

    public const TAHINI_PURSLANE_PEPPER_SALAD_IMAGE = 'images/meals/tahini_purslane_pepper_salad.png';

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
                    $definition['image_path'] ?? null,
                    $definition['instructions'] ?? null,
                );
                $updated[] = $mealName;
            }

            return $updated;
        });
    }

    /**
     * @param  array{ingredients: array<string, float>, diet_tags?: list<string>, highlight?: string, meal_type?: MealType, category?: RecipeCategory, image_path?: string}  $definition
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
            'image_path' => $definition['image_path'] ?? null,
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
        ?string $imagePath = null,
        ?string $instructions = null,
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
            $imagePath !== null && trim($imagePath) !== '' ? [
                'image_path' => trim($imagePath),
            ] : [],
            $instructions !== null && trim($instructions) !== '' ? [
                'instructions' => trim($instructions),
                'description' => trim($instructions),
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
                    'Sesame Seeds' => 8,
                    'Tahini Miso Garlic Ginger Rice Vinegar Dressing (Base)' => 20,
                ],
                'diet_tags' => $veganTags,
                'highlight' => 'Homemade fermented kimchi with purslane and bok choy, sesame seeds, and tahini-miso dressing.',
            ],
            self::TAHINI_PURSLANE_PEPPER_SALAD_NAME => [
                'category' => RecipeCategory::SideSalad,
                'meal_type' => MealType::Salad,
                'ingredients' => [
                    'Bell Pepper (Red)' => 50,
                    'Lemon-Tahini Dressing (Base)' => 22,
                    'Purslane' => 55,
                    'Pumpkin Seeds' => 8,
                    'Roasted Cherry Tomato (Base)' => 45,
                    'Sesame Seeds' => 8,
                ],
                'diet_tags' => $veganTags,
                'highlight' => 'Purslane and red pepper with roasted cherry tomatoes, sesame and pumpkin seeds, and lemon-tahini dressing.',
                'image_path' => self::TAHINI_PURSLANE_PEPPER_SALAD_IMAGE,
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
                    'Black Pepper' => 0.5,
                    'Buckwheat Spaghetti (Cooked)' => 120,
                    'Fresh Coriander' => 8,
                    'Fresh Parsley' => 8,
                    'Garlic (Raw)' => 4,
                    'Lemon Juice' => 12,
                    'Loomi (Black Lime)' => 2,
                    'Mackerel' => StandardMeatPortion::GRAMS,
                    'Mustard Seeds' => 2,
                    'Olive Oil (Extra Virgin)' => 10,
                    'Roasted Mixed Vegetables (Base)' => 100,
                    'Sea Salt' => 0.5,
                ],
                'diet_tags' => $fishTags,
                'highlight' => 'Grilled mackerel with lemon-garlic marinade, roasted mixed vegetables, and buckwheat spaghetti.',
                'image_path' => self::MACKEREL_PLATE_IMAGE,
                'instructions' => "1. Prepare Roasted Mixed Vegetables (Base) and Buckwheat Spaghetti (Cooked) per base recipe instructions; keep warm.\n2. Lightly crush the loomi (black lime) and mustard seeds. Mince the garlic and chop the fresh coriander and parsley.\n3. Whisk olive oil, lemon juice, garlic, sea salt, black pepper, crushed loomi, and mustard seeds into a bright marinade.\n4. Score the mackerel fillets and coat with the marinade. Rest 10 minutes.\n5. Grill or pan-sear skin-side down over medium-high heat until the skin is crisp and the flesh is cooked through, about 4–5 minutes per side.\n6. Serve the fish over buckwheat spaghetti with roasted mixed vegetables. Finish with fresh coriander and parsley.",
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
