<?php

namespace App\Support;

use App\Enums\MealScalingRole as MealScalingRoleEnum;
use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;

/**
 * Shared kitchen pack-out targets for chicken, fish, and beef Meal Tiers mains.
 */
final class ChickenKitchenPlateTargets
{
    public static function containerMl(): float
    {
        return (float) config('meal_tiers_library.chicken_kitchen.container_ml', 500.0);
    }

    public static function platePrepOilGrams(): float
    {
        return (float) config('meal_tiers_library.chicken_kitchen.plate_prep_oil_grams', 5.0);
    }

    public static function saladDressingGrams(): float
    {
        return (float) config('meal_tiers_library.chicken_kitchen.salad_dressing_grams', 20.0);
    }

    public static function nutSeedCapGrams(): float
    {
        return (float) config('meal_tiers_library.chicken_kitchen.nut_seed_cap_grams', 15.0);
    }

    public static function denseVegCapGrams(): float
    {
        return (float) config('meal_tiers_library.chicken_kitchen.dense_veg_cap_grams', 280.0);
    }

    public static function leafyCapGrams(): float
    {
        return (float) config('meal_tiers_library.chicken_kitchen.leafy_cap_grams', 50.0);
    }

    public static function noodleVegCapGrams(): float
    {
        return (float) config('meal_tiers_library.chicken_kitchen.noodle_veg_cap_grams', 250.0);
    }

    public static function cherryTomatoCapGrams(): float
    {
        return (float) config('meal_tiers_library.chicken_kitchen.cherry_tomato_cap_grams', 80.0);
    }

    public static function isCherryTomatoIngredient(Ingredient $ingredient): bool
    {
        $name = strtolower(trim($ingredient->name));

        return $name !== '' && str_contains($name, 'cherry tomato');
    }

    public static function calorieTolerance(): float
    {
        return (float) config('meal_tiers_library.chicken_kitchen.calorie_tolerance', 25.0);
    }

    public static function cookedProteinGramsForTier(int $calorieTier): ?float
    {
        $grams = config('meal_tiers_library.chicken_kitchen.cooked_protein_grams', []);

        if (! is_array($grams) || ! array_key_exists($calorieTier, $grams)) {
            return null;
        }

        return (float) $grams[$calorieTier];
    }

    public static function designedCaloriesForTier(int $calorieTier, ?Meal $meal = null): ?float
    {
        $family = MealTiersProteinFamily::Chicken;

        if ($meal !== null) {
            $family = MealTiersProteinFamily::fromMealName((string) $meal->name)
                ?? ($meal->exists ? MealTiersProteinFamily::forMeal($meal) : null)
                ?? MealTiersProteinFamily::Chicken;
        }

        $row = config('meal_tiers_library.families.'.$family.'.'.$calorieTier);

        if (! is_array($row) || ! isset($row['designed_calories'])) {
            return null;
        }

        return (float) $row['designed_calories'];
    }

    public static function mlPerGram(string $band): float
    {
        $map = config('meal_tiers_library.chicken_kitchen.ml_per_gram', []);

        if (! is_array($map)) {
            return 2.0;
        }

        return (float) ($map[$band] ?? $map['default_extra'] ?? 2.0);
    }

    public static function isSaladMeal(Meal $meal): bool
    {
        $name = strtolower(trim((string) $meal->name));

        if (str_contains($name, 'salad')) {
            return true;
        }

        if (in_array($meal->category, [RecipeCategory::SideSalad, RecipeCategory::MainSalad], true)) {
            return true;
        }

        if ($meal->meal_type === MealType::Salad) {
            return true;
        }

        return false;
    }

    public static function isDressingIngredient(Ingredient $ingredient): bool
    {
        $name = strtolower(trim($ingredient->name));

        if ($name === '') {
            return false;
        }

        if (str_contains($name, 'dressing')) {
            return true;
        }

        return false;
    }

    public static function isSideBreadIngredient(Ingredient $ingredient): bool
    {
        $name = strtolower(trim($ingredient->name));

        return $name !== '' && str_contains($name, 'bread');
    }

    /**
     * House (Base) components already cooked — meal grams are plated kitchen weight, not raw/dry prep.
     */
    public static function isPlatedBaseIngredient(Ingredient $ingredient, Meal $meal): bool
    {
        if (! IngredientCookingYield::isFinishedBaseComponent($ingredient)) {
            return false;
        }

        if (StandardMeatPortion::isPrimaryMeatIngredient($ingredient->name, $meal->name)) {
            return false;
        }

        if (self::isDressingIngredient($ingredient) || self::isSideBreadIngredient($ingredient)) {
            return false;
        }

        return true;
    }

    public static function isPlatedRiceBaseIngredient(Ingredient $ingredient): bool
    {
        if (! IngredientCookingYield::isFinishedBaseComponent($ingredient)) {
            return false;
        }

        $name = strtolower(trim($ingredient->name));

        return $name !== '' && str_contains($name, 'rice') && str_contains($name, '(base)');
    }

    /**
     * Kitchen minimum cooked plated rice (Base) grams, scaled with the protein tier curve from 400 kcal.
     */
    public static function platedRiceBaseGramsForTier(int $calorieTier, ?Meal $meal = null): ?float
    {
        $family = $meal !== null ? MealTiersProteinFamily::forMeal($meal) : null;

        if ($family === MealTiersProteinFamily::Beef) {
            $minimums = config('meal_tiers_library.chicken_kitchen.plated_rice_base_grams_beef', []);

            if (is_array($minimums) && array_key_exists($calorieTier, $minimums)) {
                return KitchenPortionRounding::snapFiveGramSteps((float) $minimums[$calorieTier]);
            }
        }

        $anchorTier = 400;
        $anchorGrams = (float) config('meal_tiers_library.chicken_kitchen.plated_rice_base_grams_at_400', 100.0);
        $anchorProtein = self::cookedProteinGramsForTier($anchorTier);
        $tierProtein = self::cookedProteinGramsForTier($calorieTier);

        if ($anchorProtein === null || $tierProtein === null || $anchorProtein <= 0.0 || $anchorGrams <= 0.0) {
            return null;
        }

        return KitchenPortionRounding::snapFiveGramSteps($anchorGrams * $tierProtein / $anchorProtein);
    }

    public static function densityBandForIngredient(Ingredient $ingredient, Meal $meal): string
    {
        if (KitchenPortionRounding::isNutOrSeedIngredient($ingredient)) {
            return 'nut_seed';
        }

        if (KitchenPortionRounding::isOilIngredient($ingredient)) {
            return 'oil';
        }

        $role = MealScalingRole::roleForIngredient($ingredient, $meal);

        if ($role === MealScalingRoleEnum::Protein) {
            return 'protein';
        }

        if ($role === MealScalingRoleEnum::Carb) {
            return 'grain';
        }

        $name = strtolower(trim($ingredient->name));

        foreach ([
            'spinach', 'rocca', 'arugula', 'lettuce', 'kale', 'cabbage', 'bok choy',
            'herb salad', 'mixed greens', 'rocket', 'purslane', 'watercress',
        ] as $needle) {
            if (str_contains($name, $needle)) {
                return 'leafy';
            }
        }

        foreach (['zucchini', 'koosa', 'courgette'] as $needle) {
            if (str_contains($name, $needle)) {
                return 'noodle_veg';
            }
        }

        foreach ([
            'potato', 'sweet potato', 'pumpkin', 'beet', 'carrot', 'squash',
            'eggplant', 'cauliflower', 'broccoli',
        ] as $needle) {
            if (str_contains($name, $needle)) {
                return 'dense_veg';
            }
        }

        if ($role === MealScalingRoleEnum::Vegetable) {
            return 'dense_veg';
        }

        return 'default_extra';
    }

    public static function estimatedMl(Ingredient $ingredient, Meal $meal, float $grams): float
    {
        if ($grams <= 0.0) {
            return 0.0;
        }

        return $grams * self::mlPerGram(self::densityBandForIngredient($ingredient, $meal));
    }

    /**
     * Stored protein grams for a primary meat ingredient at a calorie tab.
     * Cooked bases / liver store cooked curve grams; raw breast / thigh / raw
     * fish and beef store the raw weight that yields that cooked target.
     */
    public static function storedProteinGramsForIngredient(Ingredient $ingredient, int $calorieTier): ?float
    {
        $cooked = self::cookedProteinGramsForTier($calorieTier);

        if ($cooked === null) {
            return null;
        }

        $name = strtolower(trim($ingredient->name));

        if ($name === strtolower(ChickenBreastYield::RAW_INGREDIENT_NAME) || $name === 'chicken thigh') {
            return KitchenPortionRounding::snapFiveGramSteps(
                ChickenBreastYield::rawGramsFromCooked($cooked),
            );
        }

        // Finished bases are already cooked plated grams.
        if (str_contains($name, '(base)') || IngredientCookingYield::isFinishedBaseComponent($ingredient)) {
            return KitchenPortionRounding::snapFiveGramSteps($cooked);
        }

        $profile = IngredientCookingYield::profileFor($ingredient);
        $yield = (float) $profile['dry_to_cooked_yield'];

        if (
            $profile['macros_state'] === IngredientCookingYield::STATE_RAW_OR_DRY
            && $yield > 0.0
            && $yield < 1.0
        ) {
            return KitchenPortionRounding::snapFiveGramSteps($cooked / $yield);
        }

        return KitchenPortionRounding::snapFiveGramSteps($cooked);
    }
}
