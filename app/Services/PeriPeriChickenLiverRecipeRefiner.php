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
 * Canonical Peri Peri Chicken Liver w Zucchini Bread — includes 15 g pumpkin seeds.
 */
final class PeriPeriChickenLiverRecipeRefiner
{
    public const MEAL_NAME = 'Peri Peri Chicken Liver w Zucchini Bread';

    /**
     * @return list<string>
     */
    public function refine(): array
    {
        return DB::transaction(function (): array {
            $meal = $this->resolveOrCreateMeal();

            if (MealLibraryEditGuard::shouldSkipMealRefinement($meal)) {
                return [];
            }

            $this->syncMeal($meal, $this->ingredientGrams());

            return [self::MEAL_NAME];
        });
    }

    /**
     * @return array<string, float>
     */
    public function ingredientGrams(): array
    {
        return [
            'Chicken Liver' => StandardMeatPortion::GRAMS,
            'Zucchini Almond Bread (Base)' => 75.0,
            'Harissa Paste (Base)' => 20.0,
            'Olive Oil (Extra Virgin)' => 5.0,
            'Bell Pepper (Red)' => 40.0,
            'Red Onion' => 35.0,
            'Tomato (Raw)' => 45.0,
            'Garlic (Raw)' => 4.0,
            'Lemon Juice' => 10.0,
            'Fresh Coriander' => 5.0,
            'Smoked Paprika' => 1.0,
            'Sea Salt' => 1.0,
            'Black Pepper' => 0.5,
            'Pumpkin Seeds' => 15.0,
        ];
    }

    private function resolveOrCreateMeal(): Meal
    {
        /** @var Meal|null $meal */
        $meal = Meal::queryForMealLibrary()->where('name', self::MEAL_NAME)->first();

        if ($meal !== null) {
            return $meal;
        }

        return Meal::query()->create([
            'name' => self::MEAL_NAME,
            'category' => RecipeCategory::Meal,
            'meal_type' => MealType::Main,
            'image_path' => 'images/meals/peri_peri_chicken_liver_w_zucchini_bread.png',
            'short_description' => $this->highlight(),
            'instructions' => $this->instructionsText(),
            'diet_tags' => WholeFoodDietPolicy::REQUIRED_MEAL_DIET_TAGS,
        ]);
    }

    /**
     * @param  array<string, float>  $ingredientGrams
     */
    private function syncMeal(Meal $meal, array $ingredientGrams): void
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
                'diet_tags' => WholeFoodDietPolicy::REQUIRED_MEAL_DIET_TAGS,
                'short_description' => $this->highlight(),
                'highlight' => $this->highlight(),
                'instructions' => $this->instructionsText(),
                'image_path' => 'images/meals/peri_peri_chicken_liver_w_zucchini_bread.png',
            ],
        ));

        MealRecipeAsIngredientSyncService::syncFromPersistedMeal($fresh->fresh(['ingredients']), false);
    }

    private function highlight(): string
    {
        return 'Peri peri–spiced chicken liver with peppers and onion, served with warm zucchini almond bread, tomato, coriander, and a pumpkin-seed crunch.';
    }

    private function instructionsText(): string
    {
        return implode("\n", [
            '1. Prepare Zucchini Almond Bread (Base) per base recipe instructions; slice and keep warm.',
            '2. Toss chicken liver with Harissa Paste (Base), smoked paprika, garlic, lemon juice, sea salt, and black pepper.',
            '3. Sauté red onion and bell pepper in olive oil until softened. Add the seasoned liver and sear until just cooked through.',
            '4. Plate with warm zucchini almond bread and sliced tomato. Scatter fresh coriander and pumpkin seeds over the top. Serve immediately.',
        ]);
    }
}
