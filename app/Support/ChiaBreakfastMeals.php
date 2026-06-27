<?php

namespace App\Support;

use App\Models\Meal;
use App\Services\BalancedChiaBreakfastRecipeRefiner;

/**
 * Coconut-chia breakfast deck meals served at a fixed kitchen portion for every plan tier.
 */
final class ChiaBreakfastMeals
{
    public static function fixedCalories(): float
    {
        return (float) config('customer_nutrition.chia_breakfast_calories', 200.0);
    }

    /**
     * @return list<string>
     */
    public static function mealNames(): array
    {
        return BalancedChiaBreakfastRecipeRefiner::refinedMealNames();
    }

    public static function isChiaBreakfast(Meal|string $meal): bool
    {
        $name = $meal instanceof Meal ? (string) $meal->name : $meal;

        return in_array($name, self::mealNames(), true);
    }
}
