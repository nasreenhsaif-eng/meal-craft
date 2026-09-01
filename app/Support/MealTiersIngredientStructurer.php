<?php

namespace App\Support;

use App\Enums\MealScalingRole as MealScalingRoleEnum;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\RecipeNutritionCalculator;

/**
 * Builds per-tab ingredient grams from a classic recipe using family kitchen rules.
 */
final class MealTiersIngredientStructurer
{
    /**
     * @return array<int, array<int, float>> calorie_tier => [ingredient_id => grams]
     */
    public static function gramsByTier(Meal $meal): array
    {
        $tabs = MealTiersCalorieTabs::forMeal($meal);

        if ($tabs === []) {
            return [];
        }

        $meal->loadMissing('ingredients');
        $baseline = [];

        foreach ($meal->ingredients as $ingredient) {
            $grams = (float) ($ingredient->pivot->amount_grams ?? $ingredient->pivot->amount ?? 0);
            $baseline[$ingredient->id] = max(0.0, $grams);
        }

        $byTier = [];

        foreach ($tabs as $tier) {
            $byTier[$tier] = self::gramsForTier($meal, $baseline, $tier);
        }

        return $byTier;
    }

    /**
     * @param  array<int, float>  $baseline
     * @return array<int, float>
     */
    public static function gramsForTier(Meal $meal, array $baseline, int $calorieTier): array
    {
        if (MealTiersAuthoredPlates::scalesFrom500($meal)) {
            $authoredBaseline = MealTiersAuthoredPlates::gramsByIngredientIdForTier(
                $meal,
                MealTiersAuthoredPlates::ReferenceCalorieTier,
            );

            if ($authoredBaseline !== null) {
                return ChickenKitchenContainerScaler::gramsForTier($meal, $authoredBaseline, $calorieTier);
            }

            return MealTiersAuthoredPlates::scaleBaselineFrom500($baseline, $calorieTier);
        }

        $family = MealTiersProteinFamily::forMeal($meal);

        if (MealTiersProteinFamily::usesKitchenPackout($family)) {
            return ChickenKitchenContainerScaler::gramsForTier($meal, $baseline, $calorieTier);
        }

        $buckets = MealTiersProteinFamily::bucketsForMeal($meal, $calorieTier);
        $targetProteinGrams = MealTiersProteinFamily::proteinGramsForTier($calorieTier);

        $grams = $baseline;

        if ($buckets === null || $targetProteinGrams === null) {
            return self::applyCookingOilTierCap($meal, $grams, $calorieTier);
        }

        $proteinIds = [];
        $groupedIds = [
            'carbs_veg' => [],
            'sauce_fat' => [],
            'seasoning' => [],
        ];

        foreach ($meal->ingredients as $ingredient) {
            $role = MealScalingRole::roleForIngredient($ingredient, $meal);
            $id = $ingredient->id;

            if ($role === MealScalingRoleEnum::Protein && StandardMeatPortion::isPrimaryMeatIngredient($ingredient->name, $meal->name)) {
                $proteinIds[] = $id;

                continue;
            }

            $bucket = MealTiersProteinFamily::roleBucket($role);

            if (isset($groupedIds[$bucket])) {
                $groupedIds[$bucket][] = $id;
            }
        }

        $currentProteinGrams = 0.0;
        foreach ($proteinIds as $id) {
            $currentProteinGrams += $baseline[$id] ?? 0.0;
        }

        if ($currentProteinGrams > 0.0 && $proteinIds !== []) {
            $proteinMultiplier = $targetProteinGrams / $currentProteinGrams;
            foreach ($proteinIds as $id) {
                $grams[$id] = round(($baseline[$id] ?? 0.0) * $proteinMultiplier, 2);
            }
        }

        foreach (['carbs_veg', 'sauce_fat', 'seasoning'] as $bucket) {
            $targetCalories = (float) ($buckets[$bucket] ?? 0);
            $ids = $groupedIds[$bucket];
            $currentCalories = self::caloriesForIngredientIds($meal, $baseline, $ids);

            if ($ids === [] || $currentCalories <= 0.0 || $targetCalories <= 0.0) {
                continue;
            }

            $multiplier = $targetCalories / $currentCalories;
            foreach ($ids as $id) {
                $grams[$id] = round(($baseline[$id] ?? 0.0) * $multiplier, 2);
            }
        }

        return self::applyCookingOilTierCap($meal, $grams, $calorieTier);
    }

    /**
     * @param  array<int, float>  $grams
     * @return array<int, float>
     */
    private static function applyCookingOilTierCap(Meal $meal, array $grams, int $calorieTier): array
    {
        $cap = MealTiersAuthoredPlates::cookingOilGramsCapForTier($calorieTier);

        if ($cap === null) {
            return $grams;
        }

        foreach ($meal->ingredients as $ingredient) {
            if (! KitchenPortionRounding::isOilIngredient($ingredient)) {
                continue;
            }

            if (! isset($grams[$ingredient->id]) || (float) $grams[$ingredient->id] <= 0) {
                continue;
            }

            $grams[$ingredient->id] = $cap;
        }

        return $grams;
    }

    /**
     * @param  array<int, float>  $gramsById
     * @param  list<int>  $ingredientIds
     */
    private static function caloriesForIngredientIds(Meal $meal, array $gramsById, array $ingredientIds): float
    {
        $total = 0.0;

        foreach ($meal->ingredients as $ingredient) {
            if (! in_array($ingredient->id, $ingredientIds, true)) {
                continue;
            }

            $grams = $gramsById[$ingredient->id] ?? 0.0;
            $total += self::caloriesForIngredientGrams($ingredient, $grams);
        }

        return $total;
    }

    private static function caloriesForIngredientGrams(Ingredient $ingredient, float $grams): float
    {
        if ($grams <= 0.0) {
            return 0.0;
        }

        $perGram = RecipeNutritionCalculator::unroundedNutrientsPerGram($ingredient);

        return (float) ($perGram['calories'] ?? 0) * $grams;
    }
}
