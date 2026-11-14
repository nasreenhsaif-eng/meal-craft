<?php

namespace App\Support;

use App\Services\BalancedChiaDessertRecipeRefiner;
use App\Services\BalancedEggBreakfastRecipeRefiner;
use App\Services\BalancedRotationMealRecipeRefiner;
use App\Services\BalancedTandooriMealRecipeRefiner;
use App\Services\BalancedWeeklyRotationSchedule;
use App\Services\SaladDressingMealRefiner;

/**
 * Meal names whose library UI edits should be mirrored into version-controlled refiner source.
 */
final class MealRefinerCatalog
{
    /**
     * @return list<string>
     */
    public static function managedMealNames(): array
    {
        static $names = null;

        if (is_array($names)) {
            return $names;
        }

        $names = array_values(array_unique(array_merge(
            BalancedWeeklyRotationSchedule::allScheduledMealNames(),
            BalancedEggBreakfastRecipeRefiner::refinedMealNames(),
            SaladDressingMealRefiner::refinedMealNames(),
            BalancedChiaDessertRecipeRefiner::refinedMealNames(),
            BalancedRotationMealRecipeRefiner::refinedMealNames(),
            BalancedTandooriMealRecipeRefiner::refinedMealNames(),
        )));

        sort($names, SORT_NATURAL | SORT_FLAG_CASE);

        return $names;
    }

    public static function isManagedMealName(string $mealName): bool
    {
        return in_array(trim($mealName), self::managedMealNames(), true);
    }
}
