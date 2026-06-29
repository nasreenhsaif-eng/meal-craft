<?php

namespace App\Support;

use App\Models\Meal;
use App\Services\BalancedChiaDessertRecipeRefiner;

/**
 * Coconut-chia dessert deck meals — fixed kitchen portion (~300+ kcal); tier rebalance via dessert_calories.
 */
final class ChiaDessertMeals
{
    /**
     * @return list<string>
     */
    public static function mealNames(): array
    {
        return BalancedChiaDessertRecipeRefiner::refinedMealNames();
    }

    public static function isChiaDessert(Meal|string $meal): bool
    {
        $name = $meal instanceof Meal ? (string) $meal->name : $meal;

        return in_array($name, self::mealNames(), true);
    }
}
