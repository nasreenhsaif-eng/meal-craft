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

            $dayAdaptOptions = AdaptedMenuFixedPortionResolver::mergeIntoBuildOptions(
                array_merge($adaptOptions, ['day_of_week' => $dayNumber]),
            );

            $dayMenu = [
                'breakfasts' => [],
                'meals' => [],
                'sideSalads' => [],
                'desserts' => [],
                'soup' => [],
            ];

            /** @var array<int, Meal> $carouselMainsBySlot */
            $carouselMainsBySlot = [];
            /** @var list<Meal> $primaryMainMeals */
            $primaryMainMeals = [];

            foreach ($dayRows as $row) {
                if (! $row instanceof MealPlanDayMeal || ! $row->meal instanceof Meal) {
                    continue;
                }

                $slotType = $row->slot_type instanceof MealPlanSlotType
                    ? $row->slot_type
                    : MealPlanSlotType::tryFrom((string) $row->slot_type);

                if (! $slotType instanceof MealPlanSlotType) {
                    continue;
                }

                $mealForAdaptation = $row->meal;

                if ($slotType === MealPlanSlotType::Dessert) {
                    $mealForAdaptation = self::resolveDessertMealForProfile($mealForAdaptation, $profile);
                }

                if ($slotType === MealPlanSlotType::Breakfast) {
                    $mealForAdaptation = SavoryEggBreakfastMeals::resolveMealForProfile($mealForAdaptation, $profile);
                }

                $adapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $mealForAdaptation, array_merge(
                    $dayAdaptOptions,
                    ['schedule_slot' => AdaptedMenuBuilder::adaptationSlotForMealPlanSlot($slotType)],
                ));

                if ($adapted === null) {
                    continue;
                }

                $slotIndex = (int) $row->slot_index;

                if ($slotType === MealPlanSlotType::Breakfast && $slotIndex === 1) {
                    if (ChiaDessertMeals::isChiaDessert($row->meal) || ! SavoryEggBreakfastMeals::isSavoryEggBreakfast($row->meal)) {
                        continue;
                    }

                    $dayMenu['breakfasts'][] = $adapted;
                } elseif ($slotType === MealPlanSlotType::Main && in_array($slotIndex, [1, 2, 3, 4, 5], true)) {
                    $carouselMainsBySlot[$slotIndex] = $row->meal;

                    if (in_array($slotIndex, [1, 3], true)) {
                        $primaryMainMeals[] = $row->meal;
                    }
                } elseif ($slotType === MealPlanSlotType::Salad && in_array($slotIndex, [1, 2], true)) {
                    $dayMenu['sideSalads'][] = $adapted;
                } elseif ($slotType === MealPlanSlotType::Dessert && in_array($slotIndex, [1, 2], true)) {
                    $dayMenu['desserts'][] = $adapted;
                } elseif ($slotType === MealPlanSlotType::Soup) {
                    $dayMenu['soup'][] = $adapted;
                }
            }

            if ($dayMenu['breakfasts'] === []) {
                $fallbackMeal = self::resolveRotationBreakfastMeal($dayNumber, $profile);

                if ($fallbackMeal instanceof Meal) {
                    $adapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $fallbackMeal, array_merge(
                        $dayAdaptOptions,
                        ['schedule_slot' => AdaptedMenuBuilder::adaptationSlotForMealPlanSlot(MealPlanSlotType::Breakfast)],
                    ));

                    if ($adapted !== null) {
                        $dayMenu['breakfasts'][] = $adapted;
                    }
                }
            }

            if ($dayMenu['breakfasts'] !== [] || $carouselMainsBySlot !== []) {
                self::ensureRotationDessertsForDay($dayNumber, $dayMenu, $profile, $dayAdaptOptions);

                if ($carouselMainsBySlot !== []) {
                    $mainAdaptOptions = array_merge($dayAdaptOptions, [
                        'craft_key' => (string) ($adaptOptions['craft_key'] ?? CraftCaloriePlanner::CRAFT_FULL),
                    ]);
                    $selectedMainMealIds = self::normalizeSelectedMainMealIds($adaptOptions['selected_main_meal_ids'] ?? null);
                    $requestedDay = (int) ($adaptOptions['day_of_week'] ?? 0);
                    $applySelectionToDay = $selectedMainMealIds !== []
                        && ($requestedDay === 0 || $requestedDay === $dayNumber);

                    /** @var array<int, array<string, mixed>> $adaptedById */
                    $adaptedById = [];

                    foreach ($carouselMainsBySlot as $meal) {
                        $adapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $meal, array_merge(
                            $dayAdaptOptions,
                            ['schedule_slot' => AdaptedMenuBuilder::adaptationSlotForMealPlanSlot(MealPlanSlotType::Main)],
                        ));

                        if ($adapted !== null) {
                            $adaptedById[$meal->id] = $adapted;
                        }
                    }

                    /** @var list<Meal> $selectedMealModels */
                    $selectedMealModels = [];

                    if ($applySelectionToDay) {
                        foreach ($carouselMainsBySlot as $meal) {
                            if (in_array((int) $meal->id, $selectedMainMealIds, true)) {
                                $selectedMealModels[] = $meal;
                            }
                        }
                    }

                    if ($selectedMealModels !== []) {
                        $rebalancedSelected = AdaptedMenuBuilder::adaptMainMealsForProfile(
                            $profile,
                            $selectedMealModels,
                            $mainAdaptOptions,
                        );

                        foreach ($rebalancedSelected as $row) {
                            $mealId = (int) ($row['id'] ?? 0);

                            if ($mealId > 0) {
                                $adaptedById[$mealId] = $row;
                            }
                        }
                    }

                    ksort($carouselMainsBySlot);

                    foreach ($carouselMainsBySlot as $meal) {
                        $adapted = $adaptedById[$meal->id] ?? null;

                        if (is_array($adapted)) {
                            $dayMenu['meals'][] = $adapted;
                        }
                    }

                    $reconciliationMains = $selectedMealModels !== [] ? $selectedMealModels : $primaryMainMeals;

                    if ($applySelectionToDay && $selectedMealModels !== []) {
                        $mainAdaptOptions['selected_main_meal_ids'] = $selectedMainMealIds;
                    }

                    $reconciled = DayMacroReconciliation::reconcile(
                        $profile,
                        $dayMenu,
                        $reconciliationMains,
                        $mainAdaptOptions,
                    );
                    $dayMenu = $reconciled['dayMenu'];

                    if ($macroWarningsByDay !== null && $reconciled['warnings'] !== []) {
                        $macroWarningsByDay[$dayNumber] = $reconciled['warnings'];
                    }
                }

                $out[$dayNumber] = $dayMenu;
            }
        }

        return $out;
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
    private static function ensureRotationDessertsForDay(
        int $dayNumber,
        array &$dayMenu,
        CustomerProfile $profile,
        array $dayAdaptOptions,
    ): void {
        $hasChia = false;

        foreach ($dayMenu['desserts'] as $adapted) {
            $name = is_array($adapted) ? (string) ($adapted['name'] ?? '') : '';

            if ($name !== '' && ChiaDessertMeals::isChiaDessert($name)) {
                $hasChia = true;

                break;
            }
        }

        if ($hasChia) {
            return;
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
        $name = BalancedWeeklyRotationSchedule::mealNameForDay($dayNumber, MealPlanSlotType::Dessert, 1);

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
     * @return list<int>
     */
    private static function normalizeSelectedMainMealIds(mixed $raw): array
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
