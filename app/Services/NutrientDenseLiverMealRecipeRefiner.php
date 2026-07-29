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
    public const SAUTEED_CHICKEN_LIVER_NAME = 'Sautéed Chicken Liver w Garlicky Cabbage & Peppers';

    public const SAUTEED_CHICKEN_LIVER_LEGACY_NAME = 'Sautéed Chicken Liver w Garlicky Cabbage, Bok Choy & Peppers';

    public const SPICED_BEEF_LIVER_MEATBALLS_NAME = 'Spiced Beef & Liver Meatballs w Roasted Tomato Couscous';

    public const BEEF_LIVER_STUFFED_ZUCCHINI_NAME = 'Beef & Liver Stuffed Zucchini w Marinara & Basil';

    public const PERI_PERI_CHICKEN_LIVER_NAME = 'Peri Peri Chicken Liver w Zucchini Bread';

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

                $meal = $this->ensureMealExists($mealName, $definition);

                if (MealLibraryEditGuard::shouldSkipMealRefinement($meal)) {
                    continue;
                }

                $this->syncMeal(
                    $meal,
                    $definition['ingredients'],
                    $definition['diet_tags'] ?? WholeFoodDietPolicy::REQUIRED_MEAL_DIET_TAGS,
                    $definition['short_description'] ?? null,
                    $definition['instructions'] ?? null,
                    $definition['food_filter_tags'] ?? null,
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
        $existing = Meal::queryForMealLibrary()
            ->whereIn('name', [$mealName, ...$this->legacyNamesFor($mealName)])
            ->first();

        if ($existing instanceof Meal) {
            if ($existing->name !== $mealName) {
                $existing->update(['name' => $mealName]);
            }

            return $existing->fresh();
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
     * @param  list<string>|null  $foodFilterTags
     */
    private function syncMeal(
        Meal $meal,
        array $ingredientGrams,
        array $dietTags,
        ?string $shortDescription = null,
        ?string $instructions = null,
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

        $attrs = [
            'diet_tags' => $dietTags,
            'short_description' => $shortDescription ?? $meal->short_description,
            'nutrition_aggregates_synced' => true,
            ...Meal::nutritionSummaryToPersistedAttributes(RecipeNutritionCalculator::fromMeal($meal->fresh(['ingredients']))),
        ];

        if ($instructions !== null && trim($instructions) !== '') {
            $attrs['instructions'] = trim($instructions);
        }

        if ($foodFilterTags !== null) {
            $attrs['food_filter_tags'] = $foodFilterTags;
        }

        $meal->update($attrs);
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
                    'Beetroot' => 90.0,
                    'Black Pepper' => 0.5,
                    'Cabbage (Purple)' => 70.0,
                    'Carrots' => 55.0,
                    'Chard' => 100.0,
                    'Fermented Chimichurri (Base)' => 18.0,
                    'Garlic (Raw)' => 4.0,
                    'Lemon Juice' => 8.0,
                    'Olive Oil (Extra Virgin)' => 5.0,
                    'Cooked Quinoa (Base)' => 75.0,
                    'Sesame Seeds' => 10.0,
                    'White Onion' => 35.0,
                ],
                'diet_tags' => $tags,
                'short_description' => 'Quick-seared beef liver with fluffy quinoa, roasted beetroot, wilted chard, and fermented chimichurri.',
            ],
            self::SAUTEED_CHICKEN_LIVER_NAME => [
                'ingredients' => [
                    'Bell Pepper (Red)' => 50.0,
                    'Black Pepper' => 0.5,
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
                    'Sea Salt' => 0.5,
                ],
                'diet_tags' => $tags,
                'short_description' => 'Pan-sautéed chicken liver with garlicky cabbage and peppers.',
            ],
            self::SPICED_BEEF_LIVER_MEATBALLS_NAME => [
                'ingredients' => [
                    'Beef Ground Lean' => StandardMeatPortion::beefGramsForLiverBlendMeal(37.5),
                    'Beef Liver' => 37.5,
                    'Cherry Tomatoes' => 80.0,
                    'Cooked Couscous (Base)' => 90.0,
                    'Fresh Basil' => 5.0,
                    'Garlic (Raw)' => 4.0,
                    'Lemon-Tahini Dressing (Base)' => 15.0,
                    'Marinara Sauce (Base)' => 90.0,
                    'Olive Oil (Extra Virgin)' => 4.0,
                    'Ras El Hanout (Base)' => 2.0,
                    'Sesame Seeds' => 10.0,
                    'White Onion' => 28.0,
                ],
                'diet_tags' => $tags,
                'short_description' => 'Spiced beef and liver meatballs simmered in marinara over fluffy couscous with roasted cherry tomatoes, lemon-tahini drizzle, and sesame seeds.',
            ],
            self::PERI_PERI_CHICKEN_LIVER_NAME => [
                'ingredients' => [
                    'Bay Leaves' => 0.3,
                    'Bell Pepper (Red)' => 25.0,
                    'Black Pepper' => 0.5,
                    'Cashew Cream (Base)' => 50.0,
                    'Chicken Liver' => StandardMeatPortion::GRAMS,
                    'cumin powder' => 1.0,
                    'Fire Roasted Tomatoes (Base)' => 30.0,
                    'Fresh Parsley' => 4.0,
                    'Garlic (Raw)' => 3.0,
                    'Grass Fed Butter' => 5.0,
                    'Lemon Juice' => 20.0,
                    'Olive Oil (Extra Virgin)' => 5.0,
                    'Oregano' => 1.0,
                    'Red Chili' => 2.0,
                    'Sea Salt' => 1.0,
                    'Smoked Paprika' => 1.0,
                    'White Onion' => 50.0,
                    'Worcestershire' => 2.5,
                    'Zucchini Almond Bread (Base)' => 45.0,
                ],
                'diet_tags' => array_values(array_unique(array_merge($tags, ['Gluten-free']))),
                'food_filter_tags' => ['fish', 'nightshades', 'nuts'],
                'short_description' => 'Creamy peri peri chicken livers with smoked paprika, chili, and lemon, served with warm zucchini almond bread.',
                'instructions' => "Prepare Zucchini Almond Bread (Base) per base recipe instructions; keep warm.\n"
                    ."Pat Chicken Liver dry and season with Sea Salt and Black Pepper.\n"
                    ."Warm Grass Fed Butter and Olive Oil (Extra Virgin) in a wide pan. Soften White Onion, then add Garlic (Raw), Bell Pepper (Red), and Red Chili until fragrant.\n"
                    ."Sear the livers until browned outside but still pink in the center; remove to a plate.\n"
                    ."Stir in Fire Roasted Tomatoes (Base), cumin powder, Smoked Paprika, Oregano, Bay Leaves, and Worcestershire. Deglaze with Lemon Juice (optional splash of brandy).\n"
                    ."Return livers to the pan, stir in Cashew Cream (Base), and simmer gently until the sauce thickens and livers are just cooked through. Discard bay leaves.\n"
                    .'Finish with Fresh Parsley and serve with warm Zucchini Almond Bread (Base).',
            ],
            self::BEEF_LIVER_STUFFED_ZUCCHINI_NAME => [
                'ingredients' => [
                    'Beef Ground Lean' => StandardMeatPortion::beefGramsForLiverBlendMeal(20.0),
                    'Beef Liver' => 20.0,
                    'Fresh Basil' => 8.0,
                    'Garlic (Raw)' => 4.0,
                    'Marinara Sauce (Base)' => 80.0,
                    'Olive Oil (Extra Virgin)' => 4.0,
                    'Oregano' => 1.0,
                    'White Onion' => 28.0,
                    'Zucchini' => 200.0,
                ],
                'diet_tags' => $tags,
                'short_description' => 'Tender zucchini boats stuffed with seasoned beef and minced liver, baked in marinara with fresh basil.',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function legacyNamesFor(string $mealName): array
    {
        if ($mealName === self::SAUTEED_CHICKEN_LIVER_NAME) {
            return [self::SAUTEED_CHICKEN_LIVER_LEGACY_NAME];
        }

        return [];
    }
}
