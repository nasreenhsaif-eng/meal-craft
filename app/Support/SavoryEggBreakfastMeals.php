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
 * Nutrient-dense breakfasts use the scheduled rotation meal as-is (no dairy swap).
 */
final class SavoryEggBreakfastMeals
{
    /** @var array<string, string> Legacy library names still referenced by older production plans. */
    private const LEGACY_MEAL_NAME_ALIASES = [
        'Halloumi & Spinach Scramble' => 'Gouda & Spinach Scramble',
    ];

    public static function canonicalMealName(string $mealName): string
    {
        return self::LEGACY_MEAL_NAME_ALIASES[$mealName] ?? $mealName;
    }

    /**
     * @return list<string>
     */
    public static function legacyMealNamesForCanonical(string $canonicalName): array
    {
        $legacy = [];

        foreach (self::LEGACY_MEAL_NAME_ALIASES as $oldName => $newName) {
            if ($newName === $canonicalName) {
                $legacy[] = $oldName;
            }
        }

        return $legacy;
    }

    public static function findRotationMealByName(string $mealName): ?Meal
    {
        $canonicalName = self::canonicalMealName($mealName);

        $meal = Meal::queryForMealLibrary()
            ->where('name', $canonicalName)
            ->with('ingredients')
            ->first();

        if ($meal instanceof Meal) {
            return $meal;
        }

        foreach (self::legacyMealNamesForCanonical($canonicalName) as $legacyName) {
            $legacyMeal = Meal::queryForMealLibrary()
                ->where('name', $legacyName)
                ->with('ingredients')
                ->first();

            if ($legacyMeal instanceof Meal) {
                return $legacyMeal;
            }
        }

        return null;
    }

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
        $name = self::canonicalMealName($name);

        return in_array($name, self::dairyForwardMealNames(), true);
    }

    public static function isDairyFreeBreakfast(Meal|string $meal): bool
    {
        $name = $meal instanceof Meal ? (string) $meal->name : $meal;
        $name = self::canonicalMealName($name);

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
        $mealName = self::canonicalMealName($mealName);

        if (! self::isSavoryEggBreakfast($mealName)) {
            return $mealName;
        }

        if (DietProtocol::tryFromStored($profile->diet_protocol) === DietProtocol::NutrientDense) {
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
        $canonicalMeal = self::findRotationMealByName((string) $meal->name) ?? $meal;

        $resolvedName = self::resolveMealNameForProfile((string) $canonicalMeal->name, $profile);

        if ($resolvedName === $canonicalMeal->name) {
            return $canonicalMeal;
        }

        $resolved = self::findRotationMealByName($resolvedName);

        return $resolved instanceof Meal ? $resolved : $canonicalMeal;
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
     * Prefer whole-egg rows; fall back to combined egg-family grams when whites are present.
     */
    public static function baselineEggGramsInMeal(Meal $meal): float
    {
        $whole = 0.0;
        $family = 0.0;

        foreach ($meal->ingredients as $ingredient) {
            $grams = (float) ($ingredient->pivot->amount_grams ?? 0);

            if ($grams <= 0) {
                continue;
            }

            if (EggIngredientPresentation::isWholeEggIngredient($ingredient)) {
                $whole += $grams;
                $family += $grams;

                continue;
            }

            if (EggIngredientPresentation::isEggWhiteIngredient($ingredient)) {
                $family += $grams;
            }
        }

        if ($whole > 0) {
            return $whole;
        }

        return $family;
    }

    /**
     * Total whole-egg + egg-white grams used for side scaling when whites rebalance fat.
     */
    public static function baselineEggFamilyGramsInMeal(Meal $meal): float
    {
        $total = 0.0;

        foreach ($meal->ingredients as $ingredient) {
            if (! EggIngredientPresentation::isEggFamilyIngredient($ingredient)) {
                continue;
            }

            $grams = (float) ($ingredient->pivot->amount_grams ?? 0);

            if ($grams > 0) {
                $total += $grams;
            }
        }

        return $total;
    }

    /**
     * Scale non-egg sides with egg count so portions stay realistic (not calorie-squeezed).
     */
    public static function sidePortionMultiplierForMeal(Meal $meal, float $planTier): float
    {
        $baselineEggGrams = self::baselineEggFamilyGramsInMeal($meal);

        if ($baselineEggGrams <= 0) {
            $baselineEggGrams = self::baselineEggGramsInMeal($meal);
        }

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
