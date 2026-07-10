<?php

namespace App\Services\Nutrition;

use App\Enums\MealPlanSlotType;
use App\Models\CustomerProfile;
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
     *     selected_fixed_slots?: list<string>,
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
        $dayOfWeek = isset($options['day_of_week']) ? (int) $options['day_of_week'] : 0;

        if ($dayOfWeek < 1 || $dayOfWeek > 7) {
            return $merged;
        }

        $fromSchedule = self::fromProductionSchedule($dayOfWeek, $productionPlan);

        if (! isset($merged['side_salad_calories']) && isset($fromSchedule['side_salad_calories'])) {
            $merged['side_salad_calories'] = $fromSchedule['side_salad_calories'];
        }

        if (! isset($merged['dessert_calories']) && isset($fromSchedule['dessert_calories'])) {
            $merged['dessert_calories'] = $fromSchedule['dessert_calories'];
        }

        $soupSelected = in_array('soup', $options['selected_fixed_slots'] ?? [], true)
            || ($options['include_soup'] ?? false);

        if (
            ! isset($merged['soup_calories'])
            && $soupSelected
            && isset($fromSchedule['soup_calories'])
        ) {
            $merged['soup_calories'] = $fromSchedule['soup_calories'];
        }

        return $merged;
    }

    /**
     * @param  array<string, list<int|string>>  $daySelection
     * @param  array<int, Meal>  $mealsById
     * @param  array<string, mixed>  $baseOptions
     * @return array{
     *     side_salad_calories?: float,
     *     dessert_calories?: float,
     *     soup_calories?: float,
     * }
     */
    public static function fromSelectedCarouselMeals(
        CustomerProfile $profile,
        array $daySelection,
        array $mealsById,
        array $baseOptions = [],
    ): array {
        /** @var array<string, array{optionKey: string, slot: string}> $categoryMap */
        $categoryMap = [
            'sideSalads' => ['optionKey' => 'side_salad_calories', 'slot' => 'side_salad'],
            'desserts' => ['optionKey' => 'dessert_calories', 'slot' => 'dessert'],
            'soup' => ['optionKey' => 'soup_calories', 'slot' => 'soup'],
        ];

        $out = [];

        foreach ($categoryMap as $categoryKey => $mapping) {
            $mealIds = $daySelection[$categoryKey] ?? [];

            if (! is_array($mealIds) || $mealIds === []) {
                continue;
            }

            $mealId = (int) ($mealIds[0] ?? 0);
            $meal = $mealsById[$mealId] ?? null;

            if (! $meal instanceof Meal) {
                continue;
            }

            $adapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $meal, array_merge(
                $baseOptions,
                ['schedule_slot' => $mapping['slot']],
            ));

            if (! is_array($adapted)) {
                continue;
            }

            $adaptedNutrition = is_array($adapted['adapted_nutrition'] ?? null)
                ? $adapted['adapted_nutrition']
                : [];
            $calories = (float) ($adaptedNutrition['calories'] ?? 0);

            if ($calories <= 0) {
                continue;
            }

            $out[$mapping['optionKey']] = round($calories, 2);
        }

        return $out;
    }

    /**
     * @return array{
     *     side_salad_calories?: float,
     *     dessert_calories?: float,
     *     soup_calories?: float,
     * }
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
