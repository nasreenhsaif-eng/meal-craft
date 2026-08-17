<?php

namespace App\Support;

use App\Enums\MealPlanSlotType;
use App\Models\CustomerProfile;
use App\Models\Meal;
use App\Services\NutrientDenseWeeklyRotationSchedule;

/**
 * Resolves nutrient-dense dessert deck meals (Greek yogurt chia, baked goods, bars).
 */
final class NutrientDenseDessertMeals
{
    /**
     * @return list<string>
     */
    public static function allDessertNames(): array
    {
        return array_values(array_unique([
            ...NutrientDenseWeeklyRotationSchedule::NUTRIENT_DENSE_DESSERTS,
            ...NutrientDenseWeeklyRotationSchedule::BAKED_DESSERTS,
            ...ChiaDessertMeals::mealNames(),
        ]));
    }

    public static function isNutrientDenseDessertName(string $mealName): bool
    {
        return in_array($mealName, self::allDessertNames(), true);
    }

    public static function resolveMealForProfile(Meal $scheduledMeal, CustomerProfile $profile): Meal
    {
        if (ChiaDessertMeals::isChiaDessert($scheduledMeal)) {
            return ChiaDessertMeals::resolveMealForProfile($scheduledMeal, $profile);
        }

        return $scheduledMeal;
    }

    public static function scheduledDessertNameForDay(int $dayNumber): string
    {
        return NutrientDenseWeeklyRotationSchedule::mealNameForDay(
            max(1, min(7, $dayNumber)),
            MealPlanSlotType::Dessert,
            1,
        );
    }
}
