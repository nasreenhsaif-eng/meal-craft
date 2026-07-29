<?php

namespace App\Support;

use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\Nutrition\UserPlanCalculator;

/**
 * Kitchen-cookability floors and accent caps so macro math cannot invent absurd recipes.
 */
final class CulinaryPortionConstraints
{
    /**
     * @param  array<int, float>  $gramsByIngredientId
     * @return array<int, float>
     */
    public static function apply(Meal $meal, array $gramsByIngredientId, float $planTier = 0.0): array
    {
        $meal->loadMissing('ingredients');
        $adjusted = $gramsByIngredientId;

        foreach ($meal->ingredients as $ingredient) {
            if (! isset($adjusted[$ingredient->id])) {
                continue;
            }

            $grams = (float) $adjusted[$ingredient->id];

            if ($grams <= 0) {
                continue;
            }

            $minimum = self::minimumGrams($meal, $ingredient, $planTier);
            if ($minimum !== null) {
                $grams = max($grams, $minimum);
            }

            $maximum = self::maximumGrams($ingredient);
            if ($maximum !== null) {
                $grams = min($grams, $maximum);
            }

            $adjusted[$ingredient->id] = round($grams, 4);
        }

        return $adjusted;
    }

    /**
     * @param  array<int, float>  $gramsByIngredientId
     * @return list<string>
     */
    public static function violationMessages(Meal $meal, array $gramsByIngredientId, float $planTier = 0.0): array
    {
        $meal->loadMissing('ingredients');
        $messages = [];

        foreach ($meal->ingredients as $ingredient) {
            $grams = (float) ($gramsByIngredientId[$ingredient->id] ?? 0);

            if ($grams <= 0) {
                continue;
            }

            $minimum = self::minimumGrams($meal, $ingredient, $planTier);
            if ($minimum !== null && $grams + 0.05 < $minimum) {
                $messages[] = sprintf(
                    '%s is below the culinary minimum (%.1fg < %.1fg) for %s.',
                    $ingredient->name,
                    $grams,
                    $minimum,
                    $meal->name,
                );
            }

            $maximum = self::maximumGrams($ingredient);
            if ($maximum !== null && $grams - 0.05 > $maximum) {
                $messages[] = sprintf(
                    '%s exceeds the culinary accent maximum (%.1fg > %.1fg).',
                    $ingredient->name,
                    $grams,
                    $maximum,
                );
            }
        }

        return $messages;
    }

    public static function minimumGrams(Meal $meal, Ingredient $ingredient, float $planTier = 0.0): ?float
    {
        $name = $ingredient->name;

        /** @var array<string, array<string, float>> $perMeal */
        $perMeal = config('customer_nutrition.culinary_portion_constraints.per_meal_minimum_grams', []);

        if (isset($perMeal[$meal->name][$name]) && (float) $perMeal[$meal->name][$name] > 0) {
            return self::scaleMinimumForPlanTier((float) $perMeal[$meal->name][$name], $planTier);
        }

        if (self::isTitleStructuralIngredient($meal, $name)) {
            $titleFloor = (float) config(
                'customer_nutrition.culinary_portion_constraints.title_structural_minimum_grams',
                100.0,
            );

            if ($titleFloor > 0) {
                return self::scaleMinimumForPlanTier($titleFloor, $planTier);
            }
        }

        /** @var array<string, float> $defaults */
        $defaults = config('customer_nutrition.culinary_portion_constraints.default_minimum_grams', []);

        if (isset($defaults[$name]) && (float) $defaults[$name] > 0) {
            return self::scaleMinimumForPlanTier((float) $defaults[$name], $planTier);
        }

        return SavoryEggBreakfastMeals::minimumSideGramsForPlanTier(
            $ingredient,
            max(1000.0, $planTier),
            $meal->name,
        );
    }

    public static function maximumGrams(Ingredient $ingredient): ?float
    {
        $name = $ingredient->name;

        /** @var array<string, float> $explicit */
        $explicit = config('customer_nutrition.culinary_portion_constraints.herb_spice_maximum_grams', []);

        if (isset($explicit[$name]) && (float) $explicit[$name] > 0) {
            return (float) $explicit[$name];
        }

        if (KitchenPortionRounding::isWoodyFreshHerb($ingredient)) {
            return (float) config(
                'customer_nutrition.culinary_portion_constraints.default_woody_fresh_herb_maximum_grams',
                1.0,
            );
        }

        if (KitchenPortionRounding::isSoftFreshHerb($ingredient)) {
            return (float) config(
                'customer_nutrition.culinary_portion_constraints.default_soft_fresh_herb_maximum_grams',
                8.0,
            );
        }

        if (KitchenPortionRounding::isFineMeasureSpice($ingredient)) {
            return (float) config(
                'customer_nutrition.culinary_portion_constraints.default_dry_spice_maximum_grams',
                2.0,
            );
        }

        return null;
    }

    public static function isStructuralIngredient(Meal $meal, Ingredient $ingredient): bool
    {
        return self::minimumGrams($meal, $ingredient) !== null
            || self::isTitleStructuralIngredient($meal, $ingredient->name);
    }

    public static function isTitleStructuralIngredient(Meal $meal, string $ingredientName): bool
    {
        $mealName = strtolower($meal->name);
        $needle = strtolower(trim(explode('(', $ingredientName)[0]));

        if ($needle === '' || strlen($needle) < 4) {
            return false;
        }

        // Never treat eggs, fats, or seasonings as title "bases" (e.g. Egg in "Egg Hash").
        foreach (['egg', 'oil', 'butter', 'ghee', 'salt', 'pepper', 'yogurt', 'cheese', 'milk'] as $excluded) {
            if ($needle === $excluded || str_starts_with($needle, $excluded.' ')) {
                return false;
            }
        }

        if (KitchenPortionRounding::isWoodyFreshHerb(new Ingredient(['name' => $ingredientName]))
            || KitchenPortionRounding::isSoftFreshHerb(new Ingredient(['name' => $ingredientName]))
            || KitchenPortionRounding::isFineMeasureSpice(new Ingredient(['name' => $ingredientName]))) {
            return false;
        }

        /** @var array<string, float> $defaults */
        $defaults = config('customer_nutrition.culinary_portion_constraints.default_minimum_grams', []);
        /** @var list<string> $plateVeg */
        $plateVeg = config('customer_nutrition.main_meal_plate_vegetable_ingredients', []);

        $allowed = array_unique(array_merge(array_keys($defaults), $plateVeg));

        if (! in_array($ingredientName, $allowed, true) && ! str_contains($needle, ' ')) {
            return false;
        }

        // "Sweet Potato Egg Hash" → sweet potato is the structural base.
        return str_contains($mealName, $needle);
    }

    private static function scaleMinimumForPlanTier(float $baseMinimum, float $planTier): float
    {
        if ($planTier <= 0) {
            return $baseMinimum;
        }

        $referenceBreakfast = (float) (UserPlanCalculator::tierSlotCalories(1000.0)['breakfast'] ?? 0);
        $tierBreakfast = (float) (UserPlanCalculator::tierSlotCalories($planTier)['breakfast'] ?? 0);

        if ($referenceBreakfast <= 0 || $tierBreakfast <= 0) {
            return $baseMinimum;
        }

        // Structural floors scale gently with breakfast energy (never below the cookable base).
        $ratio = max(1.0, $tierBreakfast / $referenceBreakfast);

        return round($baseMinimum * $ratio, 2);
    }
}
