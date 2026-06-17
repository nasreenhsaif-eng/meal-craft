<?php

namespace App\Services\Nutrition;

use App\Enums\MealPlanSchemaType;
use App\Enums\MealPlanSlotType;
use App\Models\CustomerProfile;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\MealPlanDayMeal;

/**
 * Admin weekly meal-plan assignments that customers see per weekday (Sun=1 … Sat=7).
 */
final class ProductionWeeklyMenuSchedule
{
    public static function resolveProductionMealPlan(): ?MealPlan
    {
        $configuredId = config('customer_nutrition.production_meal_plan_id');

        if (is_numeric($configuredId) && (int) $configuredId > 0) {
            $plan = MealPlan::query()
                ->where('schema_type', MealPlanSchemaType::WeeklyStructured)
                ->find((int) $configuredId);

            if ($plan !== null) {
                return $plan;
            }
        }

        return MealPlan::query()
            ->where('schema_type', MealPlanSchemaType::WeeklyStructured)
            ->latest('id')
            ->first();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function scheduledSoupsByWeekday(CustomerProfile $profile, ?MealPlan $plan = null): array
    {
        $plan ??= self::resolveProductionMealPlan();

        if ($plan === null) {
            return [];
        }

        $rows = MealPlanDayMeal::query()
            ->where('meal_plan_id', $plan->id)
            ->where('is_option_b', false)
            ->where('slot_type', MealPlanSlotType::Soup->value)
            ->where('slot_index', 1)
            ->with(['meal.ingredients'])
            ->get()
            ->keyBy('day_number');

        $out = [];

        foreach (range(1, 7) as $dayNumber) {
            $row = $rows->get($dayNumber);

            if (! $row instanceof MealPlanDayMeal || ! $row->meal instanceof Meal) {
                continue;
            }

            $adapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $row->meal);

            if ($adapted !== null) {
                $out[$dayNumber] = $adapted;
            }
        }

        return $out;
    }
}
