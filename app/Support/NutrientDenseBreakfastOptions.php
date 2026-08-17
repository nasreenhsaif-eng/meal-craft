<?php

namespace App\Support;

use App\Enums\DietProtocol;
use App\Enums\MealPlanSlotType;
use App\Models\CustomerProfile;
use App\Models\Meal;
use App\Services\NutrientDenseWeeklyRotationSchedule;

/**
 * TBD Weekly Protocol breakfast deck: day’s egg breakfast (default) + day’s Greek yogurt chia.
 */
final class NutrientDenseBreakfastOptions
{
    /** Day 1 egg breakfast name (Mediterranean Omelet). */
    public const OMELETTE_NAME = 'Mediterranean Omelet';

    /** Plan slot index stamped on the rotating egg option. */
    public const OMELETTE_SLOT_INDEX = 1;

    /** Alias for the egg breakfast slot index. */
    public const EGG_SLOT_INDEX = self::OMELETTE_SLOT_INDEX;

    /** Plan slot index stamped on the chia Greek yoghurt option. */
    public const CHIA_SLOT_INDEX = 2;

    public static function appliesTo(CustomerProfile $profile): bool
    {
        return DietProtocol::tryFromStored($profile->diet_protocol) === DietProtocol::NutrientDense;
    }

    public static function eggBreakfastMealNameForDay(int $dayNumber, CustomerProfile $profile): string
    {
        return SavoryEggBreakfastMeals::scheduledBreakfastNameForDay($dayNumber, $profile);
    }

    /**
     * @deprecated Use eggBreakfastMealNameForDay() — eggs rotate by weekday.
     */
    public static function omeletteMealNameForProfile(CustomerProfile $profile): string
    {
        return self::eggBreakfastMealNameForDay(1, $profile);
    }

    public static function chiaMealNameForDay(int $dayNumber, CustomerProfile $profile): string
    {
        $scheduled = NutrientDenseWeeklyRotationSchedule::mealNameForDay(
            $dayNumber,
            MealPlanSlotType::Dessert,
            3,
        );

        return ChiaDessertMeals::resolveMealNameForProfile($scheduled, $profile);
    }

    public static function resolveEggBreakfastMeal(int $dayNumber, CustomerProfile $profile): ?Meal
    {
        return SavoryEggBreakfastMeals::findRotationMealByName(
            self::eggBreakfastMealNameForDay($dayNumber, $profile),
        );
    }

    /**
     * @deprecated Use resolveEggBreakfastMeal() — eggs rotate by weekday.
     */
    public static function resolveOmeletteMeal(CustomerProfile $profile): ?Meal
    {
        return self::resolveEggBreakfastMeal(1, $profile);
    }

    public static function resolveChiaMeal(int $dayNumber, CustomerProfile $profile): ?Meal
    {
        $name = self::chiaMealNameForDay($dayNumber, $profile);

        $meal = Meal::queryForMealLibrary()
            ->where('name', $name)
            ->with('ingredients')
            ->first();

        return $meal instanceof Meal ? ChiaDessertMeals::resolveMealForProfile($meal, $profile) : null;
    }

    /**
     * @param  list<int>  $recommendedMealIds  When non-empty, overrides which option is recommended.
     * @return list<array{meal: Meal, plan_slot_index: int, is_recommended: bool}>
     */
    public static function optionMealsForDay(
        int $dayNumber,
        CustomerProfile $profile,
        array $recommendedMealIds = [],
    ): array {
        $options = [];

        $egg = self::resolveEggBreakfastMeal($dayNumber, $profile);

        if ($egg instanceof Meal) {
            $options[] = [
                'meal' => $egg,
                'plan_slot_index' => self::EGG_SLOT_INDEX,
                'is_recommended' => $recommendedMealIds === []
                    ? true
                    : in_array((int) $egg->id, $recommendedMealIds, true),
            ];
        }

        $chia = self::resolveChiaMeal($dayNumber, $profile);

        if ($chia instanceof Meal) {
            $options[] = [
                'meal' => $chia,
                'plan_slot_index' => self::CHIA_SLOT_INDEX,
                'is_recommended' => $recommendedMealIds === []
                    ? false
                    : in_array((int) $chia->id, $recommendedMealIds, true),
            ];
        }

        if ($recommendedMealIds !== [] && $options !== []) {
            $anyRecommended = false;

            foreach ($options as $option) {
                if ($option['is_recommended']) {
                    $anyRecommended = true;
                    break;
                }
            }

            if (! $anyRecommended) {
                $options[0]['is_recommended'] = true;
            }
        }

        return $options;
    }
}
