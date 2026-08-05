<?php

namespace App\Support;

use App\Enums\DietProtocol;
use App\Enums\MealPlanSlotType;
use App\Models\CustomerProfile;
use App\Models\Meal;
use App\Services\NutrientDenseWeeklyRotationSchedule;

/**
 * TBD Weekly Protocol breakfast deck: Mediterranean Omelet (default) + day’s Greek yogurt chia.
 */
final class NutrientDenseBreakfastOptions
{
    public const OMELETTE_NAME = 'Mediterranean Omelet';

    /** Plan slot index stamped on the omelette option. */
    public const OMELETTE_SLOT_INDEX = 1;

    /** Plan slot index stamped on the chia Greek yoghurt option. */
    public const CHIA_SLOT_INDEX = 2;

    public static function appliesTo(CustomerProfile $profile): bool
    {
        return DietProtocol::tryFromStored($profile->diet_protocol) === DietProtocol::NutrientDense;
    }

    public static function omeletteMealNameForProfile(CustomerProfile $profile): string
    {
        return SavoryEggBreakfastMeals::resolveMealNameForProfile(self::OMELETTE_NAME, $profile);
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

    public static function resolveOmeletteMeal(CustomerProfile $profile): ?Meal
    {
        return SavoryEggBreakfastMeals::findRotationMealByName(self::omeletteMealNameForProfile($profile));
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

        $omelette = self::resolveOmeletteMeal($profile);

        if ($omelette instanceof Meal) {
            $options[] = [
                'meal' => $omelette,
                'plan_slot_index' => self::OMELETTE_SLOT_INDEX,
                'is_recommended' => $recommendedMealIds === []
                    ? true
                    : in_array((int) $omelette->id, $recommendedMealIds, true),
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
