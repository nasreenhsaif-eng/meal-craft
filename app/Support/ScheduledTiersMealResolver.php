<?php

namespace App\Support;

use App\Enums\MealPlanLibraryCategory;
use App\Enums\MealPlanSlotType;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\MealPlanDayMeal;
use App\Services\BalancedWeeklyRotationSchedule;
use App\Services\NutrientDenseWeeklyRotationSchedule;
use InvalidArgumentException;

/**
 * Weekly protocols serve Meal Tiers Library copies when a same-name row exists.
 */
final class ScheduledTiersMealResolver
{
    public static function forMeal(Meal $meal): Meal
    {
        if ($meal->isTiersLibrary() && ! MealTiersLibraryExclusions::isExcluded($meal)) {
            return $meal;
        }

        $lookupName = MealTiersLibraryExclusions::isExcluded($meal)
            ? (MealTiersLibraryExclusions::weeklyProtocolReplacement($meal) ?? (string) $meal->name)
            : (string) $meal->name;

        return self::tiersCopyByName($lookupName) ?? $meal;
    }

    public static function tiersCopyByName(string $mealName): ?Meal
    {
        $names = self::lookupNames($mealName);

        $tiers = Meal::queryScheduledTiersMeals()
            ->whereIn('name', $names)
            ->with(['ingredients', 'calorieTiers.ingredients'])
            ->first();

        if (! $tiers instanceof Meal || MealTiersLibraryExclusions::isExcluded($tiers)) {
            return null;
        }

        return $tiers;
    }

    /**
     * @return list<string>
     */
    private static function lookupNames(string $mealName): array
    {
        return array_values(array_unique([
            $mealName,
            SavoryEggBreakfastMeals::canonicalMealName($mealName),
        ]));
    }

    /**
     * Point stored weekly-plan slots (and saved customer defaults) at tiers copies.
     */
    public static function remapPlan(MealPlan $plan): int
    {
        $plan->loadMissing('dayMeals.meal');

        /** @var array<int, int> $idMap */
        $idMap = [];
        $changed = 0;

        foreach ($plan->dayMeals as $dayMeal) {
            if (! $dayMeal instanceof MealPlanDayMeal || ! $dayMeal->meal instanceof Meal) {
                continue;
            }

            $preferred = self::preferredMealForSlot($plan, $dayMeal);

            if ((int) $preferred->id === (int) $dayMeal->meal_id) {
                continue;
            }

            $idMap[(int) $dayMeal->meal_id] = (int) $preferred->id;
            $dayMeal->meal_id = $preferred->id;
            $dayMeal->save();
            $changed++;
        }

        if ($idMap === []) {
            return $changed;
        }

        $defaults = $plan->default_day_selections;

        if (is_array($defaults) && $defaults !== []) {
            $plan->default_day_selections = self::remapSelectionIds($defaults, $idMap);
            $plan->save();
        }

        return $changed;
    }

    private static function preferredMealForSlot(MealPlan $plan, MealPlanDayMeal $dayMeal): Meal
    {
        $preferred = self::forMeal($dayMeal->meal);

        if ($preferred->isTiersLibrary() && ! MealTiersLibraryExclusions::isExcluded($preferred)) {
            return $preferred;
        }

        $rotationName = self::rotationMealName($plan, $dayMeal);

        if ($rotationName === null || MealTiersLibraryExclusions::isExcluded($rotationName)) {
            return $preferred;
        }

        return self::tiersCopyByName($rotationName)
            ?? self::classicLibraryMealByName($rotationName)
            ?? $preferred;
    }

    private static function rotationMealName(MealPlan $plan, MealPlanDayMeal $dayMeal): ?string
    {
        $slotType = $dayMeal->slot_type;

        if (! $slotType instanceof MealPlanSlotType) {
            return null;
        }

        try {
            $useNutrientDense = $plan->plan_category === MealPlanLibraryCategory::NutrientDense
                || ($plan->plan_category !== MealPlanLibraryCategory::Balanced && $plan->usesNutrientDenseProtocol());

            return $useNutrientDense
                ? NutrientDenseWeeklyRotationSchedule::mealNameForDay(
                    (int) $dayMeal->day_number,
                    $slotType,
                    (int) $dayMeal->slot_index,
                )
                : BalancedWeeklyRotationSchedule::mealNameForDay(
                    (int) $dayMeal->day_number,
                    $slotType,
                    (int) $dayMeal->slot_index,
                );
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private static function classicLibraryMealByName(string $mealName): ?Meal
    {
        if (MealTiersLibraryExclusions::isExcluded($mealName)) {
            return null;
        }

        $classic = Meal::queryForMealLibrary()
            ->where('name', $mealName)
            ->with(['ingredients', 'calorieTiers.ingredients'])
            ->first();

        return $classic instanceof Meal ? $classic : null;
    }

    /**
     * @param  array<int|string, mixed>  $defaults
     * @param  array<int, int>  $idMap
     * @return array<int|string, mixed>
     */
    private static function remapSelectionIds(array $defaults, array $idMap): array
    {
        foreach ($defaults as $dayNumber => $categories) {
            if (! is_array($categories)) {
                continue;
            }

            foreach ($categories as $categoryKey => $mealIds) {
                if (! is_array($mealIds)) {
                    continue;
                }

                $defaults[$dayNumber][$categoryKey] = array_values(array_map(
                    static fn (mixed $id): int => $idMap[(int) $id] ?? (int) $id,
                    $mealIds,
                ));
            }
        }

        return $defaults;
    }
}
