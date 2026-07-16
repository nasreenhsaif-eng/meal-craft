<?php

namespace App\Services\Nutrition;

use App\Enums\DietProtocol;
use App\Enums\MealPlanSchemaType;
use App\Enums\MealPlanSlotType;
use App\Models\CustomerProfile;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\MealPlanDayMeal;
use App\Services\BalancedWeeklyRotationSchedule;
use App\Services\NutrientDenseWeeklyMealPlanBuilder;
use App\Services\NutrientDenseWeeklyRotationSchedule;
use App\Support\ChiaDessertMeals;
use App\Support\NutrientDenseDessertMeals;
use App\Support\SavoryEggBreakfastMeals;
use Illuminate\Support\Collection;

/**
 * Admin weekly meal-plan assignments that customers see per weekday (Sun=1 … Sat=7).
 */
final class ProductionWeeklyMenuSchedule
{
    public static function resolveProductionMealPlan(?CustomerProfile $profile = null): ?MealPlan
    {
        if ($profile !== null && DietProtocol::tryFromStored($profile->diet_protocol) === DietProtocol::NutrientDense) {
            $configuredId = config('customer_nutrition.nutrient_dense_production_meal_plan_id');

            if (is_numeric($configuredId) && (int) $configuredId > 0) {
                $plan = MealPlan::query()
                    ->where('schema_type', MealPlanSchemaType::WeeklyStructured)
                    ->find((int) $configuredId);

                if ($plan !== null) {
                    return $plan;
                }
            }

            $named = MealPlan::query()
                ->where('schema_type', MealPlanSchemaType::WeeklyStructured)
                ->where('name', NutrientDenseWeeklyMealPlanBuilder::PLAN_NAME)
                ->latest('id')
                ->first();

            if ($named !== null) {
                return $named;
            }
        }

        return self::resolveBalancedProductionMealPlan();
    }

    public static function resolveBalancedProductionMealPlan(): ?MealPlan
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
        ?array &$macroWarningsByDay = null,
    ): array {
        $plan ??= self::resolveProductionMealPlan($profile);

        if ($plan === null) {
            return [];
        }

        $daysToBuild = self::daysToBuildFromAdaptOptions($adaptOptions);

        $rowsQuery = MealPlanDayMeal::query()
            ->where('meal_plan_id', $plan->id)
            ->where('is_option_b', false)
            ->with(['meal.ingredients'])
            ->orderBy('day_number')
            ->orderBy('slot_type')
            ->orderBy('slot_index');

        if (count($daysToBuild) === 1) {
            $rowsQuery->where('day_number', $daysToBuild[0]);
        }

        $rows = $rowsQuery->get()->groupBy('day_number');

        $out = [];

        foreach ($daysToBuild as $dayNumber) {
            /** @var Collection<int, MealPlanDayMeal> $dayRows */
            $dayRows = $rows->get($dayNumber, collect());

            if ($dayRows->isEmpty()) {
                continue;
            }

            $built = FullCraftDayMenuBuilder::buildProductionDayFromRows(
                $profile,
                $dayNumber,
                $dayRows,
                $adaptOptions,
            );

            if ($built === null) {
                continue;
            }

            if ($macroWarningsByDay !== null && $built['warnings'] !== []) {
                $macroWarningsByDay[$dayNumber] = $built['warnings'];
            }

            $out[$dayNumber] = $built['dayMenu'];
        }

        return $out;
    }

    /**
     * When day_of_week is set (1–7), only that weekday is adapted; otherwise Sun–Sat.
     *
     * @param  array<string, mixed>  $adaptOptions
     * @return list<int>
     */
    public static function daysToBuildFromAdaptOptions(array $adaptOptions): array
    {
        $requestedDay = isset($adaptOptions['day_of_week']) ? (int) $adaptOptions['day_of_week'] : 0;

        if ($requestedDay >= 1 && $requestedDay <= 7) {
            return [$requestedDay];
        }

        return range(1, 7);
    }

    /**
     * @param  array{
     *     breakfasts: list<array<string, mixed>>,
     *     meals: list<array<string, mixed>>,
     *     sideSalads: list<array<string, mixed>>,
     *     desserts: list<array<string, mixed>>,
     *     soup: list<array<string, mixed>>
     * }  $dayMenu
     * @param  array<string, mixed>  $dayAdaptOptions
     */
    public static function ensureRotationDessertsForDay(
        int $dayNumber,
        array &$dayMenu,
        CustomerProfile $profile,
        array $dayAdaptOptions,
    ): void {
        $isNutrientDense = DietProtocol::tryFromStored($profile->diet_protocol) === DietProtocol::NutrientDense;

        if ($isNutrientDense) {
            foreach ($dayMenu['desserts'] as $adapted) {
                $name = is_array($adapted) ? (string) ($adapted['name'] ?? '') : '';

                if ($name !== '' && in_array($name, NutrientDenseWeeklyRotationSchedule::NUTRIENT_DENSE_DESSERTS, true)) {
                    return;
                }
            }
        } else {
            foreach ($dayMenu['desserts'] as $adapted) {
                $name = is_array($adapted) ? (string) ($adapted['name'] ?? '') : '';

                if ($name !== '' && ChiaDessertMeals::isChiaDessert($name)) {
                    return;
                }
            }
        }

        $fallbackMeal = self::resolveRotationDessertMeal($dayNumber, $profile);

        if (! $fallbackMeal instanceof Meal) {
            return;
        }

        $adapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $fallbackMeal, array_merge(
            $dayAdaptOptions,
            ['schedule_slot' => AdaptedMenuBuilder::adaptationSlotForMealPlanSlot(MealPlanSlotType::Dessert)],
        ));

        if ($adapted !== null) {
            array_unshift($dayMenu['desserts'], $adapted);
        }
    }

    private static function resolveRotationDessertMeal(int $dayNumber, CustomerProfile $profile): ?Meal
    {
        $name = DietProtocol::tryFromStored($profile->diet_protocol) === DietProtocol::NutrientDense
            ? NutrientDenseWeeklyRotationSchedule::mealNameForDay($dayNumber, MealPlanSlotType::Dessert, 1)
            : BalancedWeeklyRotationSchedule::mealNameForDay($dayNumber, MealPlanSlotType::Dessert, 1);

        $meal = Meal::queryForMealLibrary()
            ->where('name', $name)
            ->with('ingredients')
            ->first();

        if (! $meal instanceof Meal) {
            return null;
        }

        return self::resolveDessertMealForProfile($meal, $profile);
    }

    private static function resolveDessertMealForProfile(Meal $meal, CustomerProfile $profile): Meal
    {
        if (DietProtocol::tryFromStored($profile->diet_protocol) === DietProtocol::NutrientDense) {
            return NutrientDenseDessertMeals::resolveMealForProfile($meal, $profile);
        }

        return ChiaDessertMeals::resolveMealForProfile($meal, $profile);
    }

    public static function resolveRotationBreakfastMeal(int $dayNumber, CustomerProfile $profile): ?Meal
    {
        $name = SavoryEggBreakfastMeals::scheduledBreakfastNameForDay($dayNumber, $profile);

        $meal = SavoryEggBreakfastMeals::findRotationMealByName($name);

        if ($meal instanceof Meal && SavoryEggBreakfastMeals::isSavoryEggBreakfast($meal)) {
            return $meal;
        }

        return null;
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
        $plan ??= self::resolveProductionMealPlan($profile);

        if ($plan === null) {
            return [];
        }

        $daysToBuild = self::daysToBuildFromAdaptOptions($adaptOptions);

        $rowsQuery = MealPlanDayMeal::query()
            ->where('meal_plan_id', $plan->id)
            ->where('is_option_b', false)
            ->where('slot_type', MealPlanSlotType::Soup->value)
            ->with(['meal.ingredients'])
            ->orderBy('day_number')
            ->orderBy('slot_index');

        if (count($daysToBuild) === 1) {
            $rowsQuery->where('day_number', $daysToBuild[0]);
        }

        $rows = $rowsQuery->get()->groupBy('day_number');

        $out = [];

        foreach ($daysToBuild as $dayNumber) {
            /** @var Collection<int, MealPlanDayMeal> $dayRows */
            $dayRows = $rows->get($dayNumber, collect());

            $dayAdaptOptions = AdaptedMenuFixedPortionResolver::mergeIntoBuildOptions(
                array_merge($adaptOptions, ['day_of_week' => $dayNumber]),
            );

            $adaptedMeals = [];

            foreach ($dayRows as $row) {
                if (! $row instanceof MealPlanDayMeal || ! $row->meal instanceof Meal) {
                    continue;
                }

                $adapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $row->meal, $dayAdaptOptions);

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

    /**
     * Find a reconciled adapted meal payload from the production weekday schedule.
     *
     * @param  array<string, mixed>  $adaptOptions
     * @return array<string, mixed>|null
     */
    public static function adaptedMealFromScheduledDay(
        CustomerProfile $profile,
        int $mealId,
        array $adaptOptions = [],
    ): ?array {
        $dayOfWeek = isset($adaptOptions['day_of_week']) ? (int) $adaptOptions['day_of_week'] : 0;
        $craftKey = isset($adaptOptions['craft_key']) ? (string) $adaptOptions['craft_key'] : '';

        if ($dayOfWeek < 1 || $dayOfWeek > 7) {
            return null;
        }

        if ($craftKey === '' || ! in_array($craftKey, CraftCaloriePlanner::keys(), true)) {
            return null;
        }

        $scheduleOptions = array_filter([
            'craft_key' => $craftKey,
            'include_soup' => ($adaptOptions['include_soup'] ?? false) ? true : null,
            'soup_calories' => isset($adaptOptions['soup_calories']) ? (float) $adaptOptions['soup_calories'] : null,
            'side_salad_calories' => isset($adaptOptions['side_salad_calories']) ? (float) $adaptOptions['side_salad_calories'] : null,
            'dessert_calories' => isset($adaptOptions['dessert_calories']) ? (float) $adaptOptions['dessert_calories'] : null,
            'plan_tier' => isset($adaptOptions['plan_tier']) ? (float) $adaptOptions['plan_tier'] : null,
            'selected_fixed_slots' => isset($adaptOptions['selected_fixed_slots']) ? $adaptOptions['selected_fixed_slots'] : null,
            'day_of_week' => $dayOfWeek,
            'selected_main_meal_ids' => isset($adaptOptions['selected_main_meal_ids']) ? $adaptOptions['selected_main_meal_ids'] : null,
        ], static fn ($value): bool => $value !== null && $value !== '' && $value !== []);

        $scheduled = self::scheduledFullCraftByWeekday($profile, null, $scheduleOptions);
        $dayMenu = $scheduled[$dayOfWeek] ?? null;

        if (! is_array($dayMenu)) {
            return null;
        }

        return self::findAdaptedMealInDayMenu($dayMenu, $mealId);
    }

    /**
     * @param  array{
     *     breakfasts?: list<array<string, mixed>>,
     *     meals?: list<array<string, mixed>>,
     *     sideSalads?: list<array<string, mixed>>,
     *     desserts?: list<array<string, mixed>>,
     *     soup?: list<array<string, mixed>>
     * }  $dayMenu
     * @return array<string, mixed>|null
     */
    public static function findAdaptedMealInDayMenu(array $dayMenu, int $mealId): ?array
    {
        foreach (['breakfasts', 'meals', 'sideSalads', 'desserts', 'soup'] as $bucket) {
            foreach ($dayMenu[$bucket] ?? [] as $meal) {
                if (! is_array($meal)) {
                    continue;
                }

                if ((int) ($meal['id'] ?? 0) === $mealId) {
                    return $meal;
                }
            }
        }

        return null;
    }

    /**
     * @return list<int>
     */
    public static function normalizeSelectedMainMealIds(mixed $raw): array
    {
        if (! is_array($raw) || $raw === []) {
            return [];
        }

        $ids = [];

        foreach ($raw as $id) {
            $normalized = (int) $id;

            if ($normalized > 0) {
                $ids[] = $normalized;
            }
        }

        return array_values(array_unique($ids));
    }
}
