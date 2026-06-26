<?php

namespace App\Services\Nutrition;

use App\Enums\MealPlanSlotType;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\MealPlanDayMeal;

/**
 * Resolves actual fixed-portion calories for plan budgeting (side salad, dessert, soup).
 */
final class AdaptedMenuFixedPortionResolver
{
    /**
     * @param  array{
     *     include_soup?: bool,
     *     soup_calories?: float,
     *     side_salad_calories?: float,
     *     dessert_calories?: float,
     *     day_of_week?: int,
     * }  $options
     * @return array{
     *     side_salad_calories?: float,
     *     dessert_calories?: float,
     *     soup_calories?: float,
     * }
     */
    public static function mergeIntoBuildOptions(array $options, ?MealPlan $productionPlan = null): array
    {
        $merged = $options;

        if (! isset($merged['side_salad_calories']) || ! isset($merged['dessert_calories'])) {
            $dayOfWeek = isset($options['day_of_week']) ? (int) $options['day_of_week'] : 0;

            if ($dayOfWeek >= 1 && $dayOfWeek <= 7) {
                $fromSchedule = self::fromProductionSchedule($dayOfWeek, $productionPlan);

                if (! isset($merged['side_salad_calories']) && isset($fromSchedule['side_salad_calories'])) {
                    $merged['side_salad_calories'] = $fromSchedule['side_salad_calories'];
                }

                if (! isset($merged['dessert_calories']) && isset($fromSchedule['dessert_calories'])) {
                    $merged['dessert_calories'] = $fromSchedule['dessert_calories'];
                }

                if (
                    ! isset($merged['soup_calories'])
                    && ($options['include_soup'] ?? false)
                    && isset($fromSchedule['soup_calories'])
                ) {
                    $merged['soup_calories'] = $fromSchedule['soup_calories'];
                }
            }
        }

        return $merged;
    }

    /**
     * @return array{side_salad_calories?: float, dessert_calories?: float, soup_calories?: float}
     */
    public static function fromProductionSchedule(int $dayOfWeek, ?MealPlan $plan = null): array
    {
        $plan ??= ProductionWeeklyMenuSchedule::resolveProductionMealPlan();

        if ($plan === null) {
            return [];
        }

        $rows = MealPlanDayMeal::query()
            ->where('meal_plan_id', $plan->id)
            ->where('day_number', $dayOfWeek)
            ->where('is_option_b', false)
            ->with('meal')
            ->orderBy('slot_type')
            ->orderBy('slot_index')
            ->get();

        $out = [];

        foreach ($rows as $row) {
            if (! $row->meal instanceof Meal) {
                continue;
            }

            $slotType = $row->slot_type instanceof MealPlanSlotType
                ? $row->slot_type
                : MealPlanSlotType::tryFrom((string) $row->slot_type);

            $calories = (float) ($row->meal->nutritionForDisplay()['calories'] ?? 0);

            if ($calories <= 0) {
                continue;
            }

            if ($slotType === MealPlanSlotType::Salad && ! isset($out['side_salad_calories'])) {
                $out['side_salad_calories'] = round($calories, 2);
            }

            if ($slotType === MealPlanSlotType::Dessert && ! isset($out['dessert_calories'])) {
                $out['dessert_calories'] = round($calories, 2);
            }

            if ($slotType === MealPlanSlotType::Soup && ! isset($out['soup_calories'])) {
                $out['soup_calories'] = round($calories, 2);
            }
        }

        return $out;
    }
}
