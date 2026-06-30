<?php

namespace App\Support;

use App\Enums\MealScalingRole as MealScalingRoleEnum;
use App\Models\Ingredient;
use App\Models\Meal;

/**
 * Classifies meal ingredients for macro-first scaling passes.
 */
final class MealScalingRole
{
    public static function roleForIngredient(Ingredient $ingredient, ?Meal $meal = null): MealScalingRoleEnum
    {
        $name = strtolower($ingredient->name);
        $mealName = $meal?->name;

        if (str_ends_with($name, '(base)') && self::nameIndicatesSauce($name)) {
            return MealScalingRoleEnum::Sauce;
        }

        if (
            $meal !== null
            && SavoryEggBreakfastMeals::isSavoryEggBreakfast($meal)
            && EggIngredientPresentation::isEggIngredient($ingredient)
        ) {
            return MealScalingRoleEnum::Protein;
        }

        if ($mealName !== null && StandardMeatPortion::isPrimaryMeatIngredient($ingredient->name, $mealName)) {
            return MealScalingRoleEnum::Protein;
        }

        $group = MealIngredientDisplayOrder::groupRank($ingredient);

        return match ($group) {
            MealIngredientDisplayOrder::GROUP_PROTEIN => MealScalingRoleEnum::Protein,
            MealIngredientDisplayOrder::GROUP_CARBS => MealScalingRoleEnum::Carb,
            MealIngredientDisplayOrder::GROUP_HERBS_SPICES => MealScalingRoleEnum::HerbSpice,
            MealIngredientDisplayOrder::GROUP_VEGETABLES => MealScalingRoleEnum::Vegetable,
            MealIngredientDisplayOrder::GROUP_FATS => MealScalingRoleEnum::Fat,
            MealIngredientDisplayOrder::GROUP_SAUCES => MealScalingRoleEnum::Sauce,
            default => MealScalingRoleEnum::Other,
        };
    }

    private static function nameIndicatesSauce(string $name): bool
    {
        foreach ([
            'sauce',
            'dressing',
            'marinade',
            'broth',
            'stock',
            'paste',
            'curry',
            'pesto',
            'hummus',
        ] as $needle) {
            if (str_contains($name, $needle)) {
                return true;
            }
        }

        return false;
    }
}
