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
    public const SARDINE_MAIN_NAME = 'Sardine Pate w Zucchini Bread';

    public const SARDINE_MAIN_LEGACY_NAME = 'Sardine & Roasted Pepper Salad';

    public const SARDINE_MAIN_IMAGE = 'images/meals/sardine_pate_zucchini_bread.png';

    public const MACKEREL_PLATE_NAME = 'Grilled Mackerel w Roasted Vegetables';

    public const MACKEREL_PLATE_IMAGE = 'images/meals/grilled_mackerel_roasted_vegetables.png';

    public const MACKEREL_QUINOA_NAME = 'Grilled Mackerel w Lemon Herb Quinoa';

    public const TAHINI_PURSLANE_PEPPER_SALAD_NAME = 'Tahini Purslane Pepper Salad';

    public const TAHINI_PURSLANE_PEPPER_SALAD_IMAGE = 'images/meals/tahini_purslane_pepper_salad.png';

    public const SAUERKRAUT_ROCCA_SALAD_NAME = 'Sauerkraut & Rocca Salad';

    public const SAUERKRAUT_ROCCA_SALAD_IMAGE = 'images/meals/sauerkraut_rocca_salad.png';

    public const KEFIR_TURKISH_EGGS_NAME = 'Kefir Turkish Eggs w Zucchini Bread';

    public const KEFIR_TURKISH_EGGS_LEGACY_NAME = 'Kefir Herb Egg Bowl';

    public const KEFIR_TURKISH_EGGS_IMAGE = 'images/meals/kefir_turkish_eggs_zucchini_bread.png';

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
        $existing = $this->resolveMealForRefinement($mealName);

        if ($existing instanceof Meal) {
            if ($mealName === self::SARDINE_MAIN_NAME && $existing->name === self::SARDINE_MAIN_LEGACY_NAME) {
                $existing->update(['name' => self::SARDINE_MAIN_NAME]);
            }

            if ($mealName === self::KEFIR_TURKISH_EGGS_NAME && $existing->name === self::KEFIR_TURKISH_EGGS_LEGACY_NAME) {
                $existing->update(['name' => self::KEFIR_TURKISH_EGGS_NAME]);
            }

            return $existing->fresh() ?? $existing;
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

    private function resolveMealForRefinement(string $mealName): ?Meal
    {
        $query = Meal::queryForMealLibrary();

        if ($mealName === self::SARDINE_MAIN_NAME) {
            return $query->whereIn('name', [self::SARDINE_MAIN_NAME, self::SARDINE_MAIN_LEGACY_NAME])->first();
        }

        if ($mealName === self::KEFIR_TURKISH_EGGS_NAME) {
            return $query->whereIn('name', [self::KEFIR_TURKISH_EGGS_NAME, self::KEFIR_TURKISH_EGGS_LEGACY_NAME])->first();
        }

        return $query->where('name', $mealName)->first();
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
            self::SAUERKRAUT_ROCCA_SALAD_NAME => [
                'category' => RecipeCategory::SideSalad,
                'meal_type' => MealType::Salad,
                'ingredients' => [
                    'Sauerkraut (Base)' => 40,
                    'Rocca' => 45,
                    'Avocado' => 35,
                    'Cherry Tomatoes' => 50,
                    'Almond whole' => 10,
                    'Cilantro Lime Dressing (Base)' => 15,
                ],
                'diet_tags' => $veganTags,
                'highlight' => 'Homemade sauerkraut with rocca, avocado, cherry tomatoes, and peeled chopped almonds with cilantro-lime dressing.',
                'image_path' => self::SAUERKRAUT_ROCCA_SALAD_IMAGE,
                'instructions' => "1. Prepare Sauerkraut (Base) per base recipe instructions.\n2. Toss sauerkraut, rocca, halved cherry tomatoes, diced avocado, and peeled chopped almonds in a bowl.\n3. Serve with Cilantro Lime Dressing (Base) on the side.",
            ],
            self::KEFIR_TURKISH_EGGS_NAME => [
                'category' => RecipeCategory::Breakfast,
                'meal_type' => MealType::Breakfast,
                'ingredients' => [
                    'Egg' => 110,
                    'Greek Yogurt' => 240,
                    'Kefir' => 30,
                    'Grass Fed Butter' => 10,
                    'Zucchini Almond Bread (Base)' => 100,
                    'Dill (Fresh)' => 5,
                    'Fresh Mint' => 4,
                    'Pumpkin Seeds' => 15,
                    'Garlic (Raw)' => 1.5,
                    'Chili Flakes' => 2,
                    'Smoked Paprika' => 1,
                    'Sea Salt' => 1,
                    'Black Pepper' => 1,
                ],
                'diet_tags' => $breakfastTags,
                'highlight' => 'Soft-boiled eggs on kefir-spiked Greek yogurt with spiced butter, dill, mint, and pumpkin seeds — served with toasted zucchini almond bread.',
                'image_path' => self::KEFIR_TURKISH_EGGS_IMAGE,
                'instructions' => "1. Place eggs in a pot and add enough cold water to cover them (do not fill the pot to the top). Cover the pot, bring to a boil, and cook for exactly 12 minutes.\n2. Transfer the eggs to an ice water bath until chilled; peel when ready — this keeps the yolks creamy and makes peeling easy.\n3. Prepare Zucchini Almond Bread (Base) per base recipe instructions. Toast 2 slices.\n4. Warm the butter in a small pan with minced garlic, smoked paprika, and chili flakes until fragrant.\n5. Whisk Greek yogurt, kefir, chopped dill, torn mint leaves, sea salt, and black pepper until smooth. Spread on a plate and drizzle with the warm spiced butter.\n6. Halve the soft-boiled eggs and nestle them on the yogurt. Scatter pumpkin seeds over the top and serve with toasted zucchini almond bread.",
            ],
            self::SARDINE_MAIN_NAME => [
                'ingredients' => [
                    'Sardines (Canned)' => 100,
                    'Lemon Juice' => 10,
                    'Dijon Mustard' => 8,
                    'Radish' => 25,
                    'Capers' => 10,
                    'Spring Onion' => 15,
                    'Garlic (Raw)' => 3,
                    'Zucchini Almond Bread (Base)' => 100,
                    'Purslane' => 40,
                    'Avocado' => 35,
                    'Roasted Red Bell Peppers (Base)' => 50,
                ],
                'diet_tags' => $fishTags,
                'highlight' => 'Sardine pâté with lemon, mustard, radish, and capers on toasted zucchini almond bread — topped with purslane, avocado, and roasted peppers.',
                'image_path' => self::SARDINE_MAIN_IMAGE,
                'instructions' => "1. Drain the sardines. In a food processor, blend with lemon juice, Dijon mustard, grated radish, capers, chopped spring onion, and garlic until smooth.\n2. Prepare Zucchini Almond Bread (Base) per base recipe instructions. Toast 2 slices.\n3. Spread the sardine pâté on the toasted bread.\n4. Top with purslane, sliced avocado, and Roasted Red Bell Peppers (Base). Serve immediately.",
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
