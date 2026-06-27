<?php

namespace App\Support;

use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\BalancedWeeklyRotationSchedule;
use App\Services\Nutrition\UserPlanCalculator;

/**
 * Balanced rotation savory (egg-based) breakfasts scale egg count by plan tier.
 */
final class SavoryEggBreakfastMeals
{
    /**
     * @return list<string>
     */
    public static function mealNames(): array
    {
        return BalancedWeeklyRotationSchedule::EGG_BREAKFASTS;
    }

    public static function isSavoryEggBreakfast(Meal|string $meal): bool
    {
        $name = $meal instanceof Meal ? (string) $meal->name : $meal;

        return in_array($name, self::mealNames(), true);
    }

    public static function eggCountForPlanTier(float $planTier): int
    {
        $snapped = (int) UserPlanCalculator::snapToPlanTier($planTier);

        /** @var array<int, int> $counts */
        $counts = config('customer_nutrition.savory_egg_breakfast_tier_counts', [
            1000 => 2,
            1200 => 2,
            1500 => 4,
            1800 => 4,
            2000 => 5,
        ]);

        if (isset($counts[$snapped])) {
            return max(1, (int) $counts[$snapped]);
        }

        $tierFloor = 2;

        foreach (UserPlanCalculator::planTiers() as $tier) {
            if ($tier <= $snapped) {
                $tierFloor = max($tierFloor, (int) ($counts[$tier] ?? 2));
            }
        }

        return $tierFloor;
    }

    public static function eggGramsForPlanTier(float $planTier): float
    {
        return round(
            self::eggCountForPlanTier($planTier) * EggIngredientPresentation::LARGE_EGG_GRAMS,
            2,
        );
    }

    /**
     * Whole eggs in the library recipe (typically 100g = 2 large eggs).
     */
    public static function baselineEggGramsInMeal(Meal $meal): float
    {
        foreach ($meal->ingredients as $ingredient) {
            if (! EggIngredientPresentation::isEggIngredient($ingredient)) {
                continue;
            }

            $grams = (float) ($ingredient->pivot->amount_grams ?? 0);

            if ($grams > 0) {
                return $grams;
            }
        }

        return 0.0;
    }

    /**
     * Scale non-egg sides with egg count so portions stay realistic (not calorie-squeezed).
     */
    public static function sidePortionMultiplierForMeal(Meal $meal, float $planTier): float
    {
        $baselineEggGrams = self::baselineEggGramsInMeal($meal);

        if ($baselineEggGrams <= 0) {
            return 1.0;
        }

        return round(self::eggGramsForPlanTier($planTier) / $baselineEggGrams, 4);
    }

    public static function minimumSideGramsForIngredient(Ingredient $ingredient): ?float
    {
        /** @var array<string, float> $minimums */
        $minimums = config('customer_nutrition.savory_egg_breakfast_minimum_side_grams', [
            'Avocado' => 50.0,
        ]);

        $minimum = $minimums[$ingredient->name] ?? null;

        if ($minimum === null || $minimum <= 0) {
            return null;
        }

        return (float) $minimum;
    }

    public static function adaptedSideGrams(Ingredient $ingredient, float $baselineGrams, float $sideMultiplier): float
    {
        if ($baselineGrams <= 0) {
            return 0.0;
        }

        $grams = round($baselineGrams * $sideMultiplier, 4);
        $minimum = self::minimumSideGramsForIngredient($ingredient);

        if ($minimum !== null) {
            $grams = max($grams, $minimum);
        }

        return $grams;
    }
}
