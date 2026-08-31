<?php

namespace App\Support;

use App\Enums\MealScalingRole as MealScalingRoleEnum;
use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;

/**
 * Shared chicken kitchen pack-out targets for the Meal Tiers Library.
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

    public static function designedCaloriesForTier(int $calorieTier): ?float
    {
        $row = config('meal_tiers_library.families.chicken.'.$calorieTier);

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

        $role = MealScalingRole::roleForIngredient($ingredient);

        return $role === MealScalingRoleEnum::Sauce && str_contains($name, '(base)');
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
     * Cooked bases / liver store cooked curve grams; raw breast / thigh store
     * the raw weight that yields that cooked target.
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

        return KitchenPortionRounding::snapFiveGramSteps($cooked);
    }
}
