<?php

namespace App\Support;

use App\Enums\DietProtocol;
use App\Models\CustomerProfile;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\BalancedWeeklyRotationSchedule;
use App\Services\NutrientDenseWeeklyRotationSchedule;
use App\Services\Nutrition\UserPlanCalculator;

/**
 * Balanced rotation savory (egg-based) breakfasts scale egg count by plan tier.
 * Dairy-forward breakfasts swap to dairy-free when the customer filters dairy.
 */
final class SavoryEggBreakfastMeals
{
    /**
     * @return list<string>
     */
    public static function dairyFreeMealNames(): array
    {
        return BalancedWeeklyRotationSchedule::EGG_BREAKFASTS;
    }

    /**
     * @return list<string>
     */
    public static function dairyForwardMealNames(): array
    {
        return BalancedWeeklyRotationSchedule::DAIRY_FORWARD_EGG_BREAKFASTS;
    }

    /**
     * @return list<string>
     */
    public static function mealNames(): array
    {
        return array_values(array_unique(array_merge(
            self::dairyFreeMealNames(),
            self::dairyForwardMealNames(),
        )));
    }

    public static function isDairyForwardBreakfast(Meal|string $meal): bool
    {
        $name = $meal instanceof Meal ? (string) $meal->name : $meal;

        return in_array($name, self::dairyForwardMealNames(), true);
    }

    public static function isDairyFreeBreakfast(Meal|string $meal): bool
    {
        $name = $meal instanceof Meal ? (string) $meal->name : $meal;

        return in_array($name, self::dairyFreeMealNames(), true);
    }

    public static function isSavoryEggBreakfast(Meal|string $meal): bool
    {
        return self::isDairyForwardBreakfast($meal) || self::isDairyFreeBreakfast($meal);
    }

    public static function profileAvoidsDairy(CustomerProfile $profile): bool
    {
        return ChiaDessertMeals::profileAvoidsDairy($profile);
    }

    public static function dairyFreeVariantMealName(string $dairyForwardMealName): ?string
    {
        $index = array_search($dairyForwardMealName, self::dairyForwardMealNames(), true);

        if ($index === false) {
            return null;
        }

        return self::dairyFreeMealNames()[$index] ?? null;
    }

    public static function dairyForwardVariantMealName(string $dairyFreeMealName): ?string
    {
        $index = array_search($dairyFreeMealName, self::dairyFreeMealNames(), true);

        if ($index === false) {
            return null;
        }

        return self::dairyForwardMealNames()[$index] ?? null;
    }

    public static function resolveMealNameForProfile(string $mealName, CustomerProfile $profile): string
    {
        if (! self::isSavoryEggBreakfast($mealName)) {
            return $mealName;
        }

        if (self::profileAvoidsDairy($profile)) {
            if (self::isDairyForwardBreakfast($mealName)) {
                return self::dairyFreeVariantMealName($mealName) ?? $mealName;
            }

            return $mealName;
        }

        if (self::isDairyFreeBreakfast($mealName)) {
            return self::dairyForwardVariantMealName($mealName) ?? $mealName;
        }

        return $mealName;
    }

    public static function resolveMealForProfile(Meal $meal, CustomerProfile $profile): Meal
    {
        $resolvedName = self::resolveMealNameForProfile((string) $meal->name, $profile);

        if ($resolvedName === $meal->name) {
            return $meal;
        }

        $resolved = Meal::queryForMealLibrary()
            ->where('name', $resolvedName)
            ->with('ingredients')
            ->first();

        return $resolved instanceof Meal ? $resolved : $meal;
    }

    public static function scheduledBreakfastNameForDay(int $dayNumber, CustomerProfile $profile): string
    {
        $index = max(0, min(6, $dayNumber - 1));

        if (DietProtocol::tryFromStored($profile->diet_protocol) === DietProtocol::NutrientDense) {
            return NutrientDenseWeeklyRotationSchedule::EGG_BREAKFASTS[$index];
        }

        if (self::profileAvoidsDairy($profile)) {
            return self::dairyFreeMealNames()[$index];
        }

        return self::dairyForwardMealNames()[$index];
    }

    public static function eggCountForPlanTier(float $planTier): int
    {
        $snapped = (int) UserPlanCalculator::snapToPlanTier($planTier);

        /** @var array<int, int> $counts */
        $counts = config('customer_nutrition.savory_egg_breakfast_tier_counts', [
            1000 => 2,
            1200 => 2,
            1500 => 3,
            1800 => 4,
            2000 => 4,
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

    public static function minimumSideGramsForIngredient(Ingredient $ingredient, ?string $mealName = null): ?float
    {
        if ($mealName !== null && self::isDairyForwardBreakfast($mealName)) {
            return null;
        }

        /** @var array<string, float> $minimums */
        $minimums = config('customer_nutrition.savory_egg_breakfast_minimum_side_grams', [
            'Avocado' => 25.0,
        ]);

        $minimum = $minimums[$ingredient->name] ?? null;

        if ($minimum === null || $minimum <= 0) {
            return null;
        }

        return (float) $minimum;
    }

    /**
     * Minimum side grams at the customer's plan tier — scales with the breakfast calorie target.
     */
    public static function minimumSideGramsForPlanTier(
        Ingredient $ingredient,
        float $planTier,
        ?string $mealName = null,
    ): ?float {
        $baseMinimum = self::minimumSideGramsForIngredient($ingredient, $mealName);

        if ($baseMinimum === null) {
            return null;
        }

        $referenceBreakfast = UserPlanCalculator::tierSlotCalories(1000.0)['breakfast'];
        $tierBreakfast = UserPlanCalculator::tierSlotCalories($planTier)['breakfast'];

        if ($referenceBreakfast <= 0 || $tierBreakfast <= 0) {
            return $baseMinimum;
        }

        return round($baseMinimum * ($tierBreakfast / $referenceBreakfast), 2);
    }

    public static function adaptedSideGrams(
        Ingredient $ingredient,
        float $baselineGrams,
        float $sideMultiplier,
        float $planTier = 1000.0,
        ?string $mealName = null,
    ): float {
        if ($baselineGrams <= 0) {
            return 0.0;
        }

        $grams = round($baselineGrams * $sideMultiplier, 4);
        $minimum = self::minimumSideGramsForPlanTier($ingredient, $planTier, $mealName);

        if ($minimum !== null) {
            $grams = max($grams, $minimum);
        }

        return $grams;
    }
}
