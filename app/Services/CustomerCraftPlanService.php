<?php

namespace App\Services;

use App\Enums\CustomerCraftMealSlot;
use App\Models\CustomerCraftPlan;
use App\Models\CustomerCraftPlanDay;
use App\Models\CustomerCraftPlanDayMeal;
use App\Models\CustomerProfile;
use App\Models\Meal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CustomerCraftPlanService
{
    /**
     * @param  array{
     *     craft_key: string,
     *     week_duration: int,
     *     selected_days: list<int>,
     *     days: list<array{
     *         day_of_week: int,
     *         include_soup?: bool,
     *         selections: array{
     *             breakfasts?: list<int|string>,
     *             meals?: list<int|string>,
     *             sideSalads?: list<int|string>,
     *             desserts?: list<int|string>,
     *             soup?: list<int|string>
     *         }
     *     }>
     * }  $payload
     */
    public static function storeSubmission(CustomerProfile $profile, array $payload): CustomerCraftPlan
    {
        return DB::transaction(function () use ($profile, $payload): CustomerCraftPlan {
            $plan = CustomerCraftPlan::query()->create([
                'customer_profile_id' => $profile->id,
                'craft_key' => $payload['craft_key'],
                'week_duration' => (int) $payload['week_duration'],
                'selected_weekdays' => array_values($payload['selected_days']),
                'submitted_at' => now(),
            ]);

            foreach ($payload['days'] as $dayPayload) {
                self::storeDay($plan, $dayPayload);
            }

            return $plan->load(['days.meals.meal']);
        });
    }

    /**
     * @param  array{
     *     day_of_week: int,
     *     include_soup?: bool,
     *     selections: array{
     *         breakfasts?: list<int|string>,
     *         meals?: list<int|string>,
     *         sideSalads?: list<int|string>,
     *         desserts?: list<int|string>,
     *         soup?: list<int|string>
     *     }
     * }  $dayPayload
     */
    private static function storeDay(CustomerCraftPlan $plan, array $dayPayload): CustomerCraftPlanDay
    {
        $day = CustomerCraftPlanDay::query()->create([
            'customer_craft_plan_id' => $plan->id,
            'day_of_week' => (int) $dayPayload['day_of_week'],
            'include_soup' => (bool) ($dayPayload['include_soup'] ?? false),
        ]);

        $selections = $dayPayload['selections'];

        self::attachMeals($day, CustomerCraftMealSlot::Breakfast, $selections['breakfasts'] ?? [], 1);
        self::attachMeals($day, CustomerCraftMealSlot::SideSalad, $selections['sideSalads'] ?? [], 1);
        self::attachMeals($day, CustomerCraftMealSlot::Dessert, $selections['desserts'] ?? [], 1);

        $mainIds = $selections['meals'] ?? [];
        foreach (array_values($mainIds) as $index => $mealId) {
            self::attachMeals($day, CustomerCraftMealSlot::Main, [$mealId], $index + 1);
        }

        if ($day->include_soup) {
            self::attachMeals($day, CustomerCraftMealSlot::Soup, $selections['soup'] ?? [], 1);
        }

        return $day;
    }

    /**
     * @param  list<int|string>  $mealIds
     */
    private static function attachMeals(
        CustomerCraftPlanDay $day,
        CustomerCraftMealSlot $slot,
        array $mealIds,
        int $startingPosition,
    ): void {
        $position = $startingPosition;

        foreach ($mealIds as $mealId) {
            $id = (int) $mealId;
            if ($id <= 0) {
                continue;
            }

            if (! Meal::query()->whereKey($id)->exists()) {
                throw new InvalidArgumentException("Meal [{$id}] does not exist.");
            }

            CustomerCraftPlanDayMeal::query()->create([
                'customer_craft_plan_day_id' => $day->id,
                'meal_id' => $id,
                'slot' => $slot,
                'position' => $position,
            ]);

            $position++;
        }
    }

    public static function weekdayFromDate(Carbon $date): int
    {
        return $date->dayOfWeek === 0 ? 1 : $date->dayOfWeek + 1;
    }
}
