<?php

namespace App\Services\Nutrition;

use App\Enums\MealPlanSchemaType;
use App\Enums\MealPlanSlotType;
use App\Models\CustomerProfile;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\MealPlanDayMeal;
use Illuminate\Support\Collection;

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
     * Full Craft day menu from the production weekly plan (unique meals per weekday).
     *
     * @param  array{craft_key?: string, include_soup?: bool}  $adaptOptions
     * @return array<int, array{
     *     breakfasts: list<array<string, mixed>>,
     *     meals: list<array<string, mixed>>,
     *     sideSalads: list<array<string, mixed>>,
     *     desserts: list<array<string, mixed>>,
     *     soup: list<array<string, mixed>>
     * }>
     */
    public static function scheduledFullCraftByWeekday(
        CustomerProfile $profile,
        ?MealPlan $plan = null,
        array $adaptOptions = [],
    ): array {
        $plan ??= self::resolveProductionMealPlan();

        if ($plan === null) {
            return [];
        }

        $rows = MealPlanDayMeal::query()
            ->where('meal_plan_id', $plan->id)
            ->where('is_option_b', false)
            ->with(['meal.ingredients'])
            ->orderBy('day_number')
            ->orderBy('slot_type')
            ->orderBy('slot_index')
            ->get()
            ->groupBy('day_number');

        $out = [];

        foreach (range(1, 7) as $dayNumber) {
            /** @var Collection<int, MealPlanDayMeal> $dayRows */
            $dayRows = $rows->get($dayNumber, collect());

            if ($dayRows->isEmpty()) {
                continue;
            }

            $dayMenu = [
                'breakfasts' => [],
                'meals' => [],
                'sideSalads' => [],
                'desserts' => [],
                'soup' => [],
            ];

            foreach ($dayRows as $row) {
                if (! $row instanceof MealPlanDayMeal || ! $row->meal instanceof Meal) {
                    continue;
                }

                $adapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $row->meal, $adaptOptions);

                if ($adapted === null) {
                    continue;
                }

                $slotType = $row->slot_type instanceof MealPlanSlotType
                    ? $row->slot_type
                    : MealPlanSlotType::tryFrom((string) $row->slot_type);
                $slotIndex = (int) $row->slot_index;

                if ($slotType === MealPlanSlotType::Breakfast && $slotIndex === 1) {
                    $dayMenu['breakfasts'][] = $adapted;
                } elseif ($slotType === MealPlanSlotType::Main && in_array($slotIndex, [1, 2], true)) {
                    $dayMenu['meals'][] = $adapted;
                } elseif ($slotType === MealPlanSlotType::Salad && $slotIndex === 1) {
                    $dayMenu['sideSalads'][] = $adapted;
                } elseif ($slotType === MealPlanSlotType::Dessert && $slotIndex === 1) {
                    $dayMenu['desserts'][] = $adapted;
                } elseif ($slotType === MealPlanSlotType::Soup) {
                    $dayMenu['soup'][] = $adapted;
                }
            }

            if ($dayMenu['breakfasts'] !== [] || $dayMenu['meals'] !== []) {
                $out[$dayNumber] = $dayMenu;
            }
        }

        return $out;
    }

    /**
     * Soups scheduled for each weekday (slot 1 then slot 2), adapted to the customer profile.
     *
     * @return array<int, list<array<string, mixed>>>
     */
    public static function scheduledSoupsByWeekday(
        CustomerProfile $profile,
        ?MealPlan $plan = null,
        array $adaptOptions = [],
    ): array {
        $plan ??= self::resolveProductionMealPlan();

        if ($plan === null) {
            return [];
        }

        $rows = MealPlanDayMeal::query()
            ->where('meal_plan_id', $plan->id)
            ->where('is_option_b', false)
            ->where('slot_type', MealPlanSlotType::Soup->value)
            ->with(['meal.ingredients'])
            ->orderBy('day_number')
            ->orderBy('slot_index')
            ->get()
            ->groupBy('day_number');

        $out = [];

        foreach (range(1, 7) as $dayNumber) {
            /** @var Collection<int, MealPlanDayMeal> $dayRows */
            $dayRows = $rows->get($dayNumber, collect());

            $adaptedMeals = [];

            foreach ($dayRows as $row) {
                if (! $row instanceof MealPlanDayMeal || ! $row->meal instanceof Meal) {
                    continue;
                }

                $adapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $row->meal, $adaptOptions);

                if ($adapted !== null) {
                    $adaptedMeals[] = $adapted;
                }
            }

            if ($adaptedMeals !== []) {
                $out[$dayNumber] = $adaptedMeals;
            }
        }

        return $out;
    }
}
