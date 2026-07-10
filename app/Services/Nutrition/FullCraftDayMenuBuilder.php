<?php

namespace App\Services\Nutrition;

use App\Enums\DietProtocol;
use App\Enums\MealPlanSlotType;
use App\Models\CustomerProfile;
use App\Models\Meal;
use App\Models\MealPlanDayMeal;
use App\Support\ChiaDessertMeals;
use App\Support\NutrientDenseDessertMeals;
use App\Support\SavoryEggBreakfastMeals;
use Illuminate\Support\Collection;

/**
 * Builds a reconciled Full Craft day menu (breakfast, mains, fixed sides) for a single weekday.
 */
final class FullCraftDayMenuBuilder
{
    /**
     * @param  Collection<int, MealPlanDayMeal>  $dayRows
     * @param  array<string, mixed>  $adaptOptions
     * @return array{
     *     dayMenu: array{
     *         breakfasts: list<array<string, mixed>>,
     *         meals: list<array<string, mixed>>,
     *         sideSalads: list<array<string, mixed>>,
     *         desserts: list<array<string, mixed>>,
     *         soup: list<array<string, mixed>>
     *     },
     *     warnings: list<string>
     * }
     */
    public static function buildPreviewDayFromRows(
        CustomerProfile $profile,
        int $dayNumber,
        Collection $dayRows,
        array $adaptOptions = [],
    ): array {
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

            $meal = self::resolveMealForProfile($row->meal, $slotType, $profile);

            if ($slotType === MealPlanSlotType::Main) {
                $carouselMainsBySlot[(int) $row->slot_index] = $meal;

                continue;
            }

            $adapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $meal, array_merge(
                $dayAdaptOptions,
                ['schedule_slot' => AdaptedMenuBuilder::adaptationSlotForMealPlanSlot($slotType)],
            ));

            if ($adapted === null) {
                continue;
            }

            $bucket = self::bucketForSlotType($slotType);

            if ($bucket !== null) {
                $dayMenu[$bucket][] = $adapted;
            }
        }

        ksort($carouselMainsBySlot);

        /** @var list<Meal> $allMainMeals */
        $allMainMeals = array_values($carouselMainsBySlot);

        $selectedMainMealIds = self::normalizeSelectedMainMealIds($adaptOptions['selected_main_meal_ids'] ?? null);
        $applySelection = $selectedMainMealIds !== [];

        if (! $applySelection && $allMainMeals !== []) {
            $selectedMainMealIds = array_slice(
                array_map(static fn (Meal $meal): int => (int) $meal->id, $allMainMeals),
                0,
                2,
            );
        }

        /** @var list<Meal> $selectedMealModels */
        $selectedMealModels = [];

        foreach ($allMainMeals as $meal) {
            if (in_array((int) $meal->id, $selectedMainMealIds, true)) {
                $selectedMealModels[] = $meal;
            }
        }

        $primaryMainMeals = array_slice($allMainMeals, 0, 2);

        return self::finalizeDayMenuWithMainReconciliation(
            $profile,
            $dayMenu,
            $carouselMainsBySlot,
            $selectedMealModels,
            $primaryMainMeals,
            $dayAdaptOptions,
            $selectedMainMealIds,
        );
    }

    /**
     * @param  Collection<int, MealPlanDayMeal>  $dayRows
     * @param  array<string, mixed>  $adaptOptions
     * @return array{
     *     dayMenu: array{
     *         breakfasts: list<array<string, mixed>>,
     *         meals: list<array<string, mixed>>,
     *         sideSalads: list<array<string, mixed>>,
     *         desserts: list<array<string, mixed>>,
     *         soup: list<array<string, mixed>>
     *     },
     *     warnings: list<string>
     * }|null
     */
    public static function buildProductionDayFromRows(
        CustomerProfile $profile,
        int $dayNumber,
        Collection $dayRows,
        array $adaptOptions = [],
    ): ?array {
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

            $mealForAdaptation = self::resolveMealForProfile($row->meal, $slotType, $profile);

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
            } elseif ($slotType === MealPlanSlotType::Main && in_array($slotIndex, [1, 2, 3, 4, 5, 6], true)) {
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
            $fallbackMeal = ProductionWeeklyMenuSchedule::resolveRotationBreakfastMeal($dayNumber, $profile);

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

        if ($dayMenu['breakfasts'] === [] && $carouselMainsBySlot === []) {
            return null;
        }

        ProductionWeeklyMenuSchedule::ensureRotationDessertsForDay(
            $dayNumber,
            $dayMenu,
            $profile,
            $dayAdaptOptions,
        );

        if ($carouselMainsBySlot === []) {
            return ['dayMenu' => $dayMenu, 'warnings' => []];
        }

        $selectedMainMealIds = self::normalizeSelectedMainMealIds($adaptOptions['selected_main_meal_ids'] ?? null);
        $requestedDay = (int) ($adaptOptions['day_of_week'] ?? 0);
        $applySelectionToDay = $selectedMainMealIds !== []
            && ($requestedDay === 0 || $requestedDay === $dayNumber);

        /** @var list<Meal> $selectedMealModels */
        $selectedMealModels = [];

        if ($applySelectionToDay) {
            foreach ($carouselMainsBySlot as $meal) {
                if (in_array((int) $meal->id, $selectedMainMealIds, true)) {
                    $selectedMealModels[] = $meal;
                }
            }
        }

        return self::finalizeDayMenuWithMainReconciliation(
            $profile,
            $dayMenu,
            $carouselMainsBySlot,
            $selectedMealModels,
            $primaryMainMeals,
            $dayAdaptOptions,
            $applySelectionToDay ? $selectedMainMealIds : [],
        );
    }

    /**
     * @param  array{
     *     breakfasts: list<array<string, mixed>>,
     *     meals: list<array<string, mixed>>,
     *     sideSalads: list<array<string, mixed>>,
     *     desserts: list<array<string, mixed>>,
     *     soup: list<array<string, mixed>>
     * }  $dayMenu
     * @param  array<int, Meal>  $carouselMainsBySlot
     * @param  list<Meal>  $selectedMealModels
     * @param  list<Meal>  $primaryMainMeals
     * @param  array<string, mixed>  $dayAdaptOptions
     * @param  list<int>  $selectedMainMealIds
     * @return array{
     *     dayMenu: array{
     *         breakfasts: list<array<string, mixed>>,
     *         meals: list<array<string, mixed>>,
     *         sideSalads: list<array<string, mixed>>,
     *         desserts: list<array<string, mixed>>,
     *         soup: list<array<string, mixed>>
     *     },
     *     warnings: list<string>
     * }
     */
    public static function finalizeDayMenuWithMainReconciliation(
        CustomerProfile $profile,
        array $dayMenu,
        array $carouselMainsBySlot,
        array $selectedMealModels,
        array $primaryMainMeals,
        array $dayAdaptOptions,
        array $selectedMainMealIds = [],
    ): array {
        $mainAdaptOptions = array_merge($dayAdaptOptions, [
            'craft_key' => (string) ($dayAdaptOptions['craft_key'] ?? CraftCaloriePlanner::CRAFT_FULL),
        ]);

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

        $dayMenu['meals'] = [];

        foreach ($carouselMainsBySlot as $meal) {
            $adapted = $adaptedById[$meal->id] ?? null;

            if (is_array($adapted)) {
                $dayMenu['meals'][] = $adapted;
            }
        }

        $reconciliationMains = $selectedMealModels !== [] ? $selectedMealModels : $primaryMainMeals;

        if ($selectedMainMealIds !== []) {
            $mainAdaptOptions['selected_main_meal_ids'] = $selectedMainMealIds;
        }

        $reconciled = DayMacroReconciliation::reconcile(
            $profile,
            $dayMenu,
            $reconciliationMains,
            $mainAdaptOptions,
        );

        return [
            'dayMenu' => $reconciled['dayMenu'],
            'warnings' => $reconciled['warnings'],
        ];
    }

    /**
     * Map adapted meal ids (after profile resolution) to the Meal model used for UI rows.
     *
     * @param  Collection<int, MealPlanDayMeal>  $dayRows
     * @return array{resolved: array<int, Meal>, scheduled: array<int, Meal>}
     */
    public static function uiMealMapsForDay(CustomerProfile $profile, Collection $dayRows): array
    {
        /** @var array<int, Meal> $resolvedByAdaptedId */
        $resolvedByAdaptedId = [];
        /** @var array<int, Meal> $scheduledByAdaptedId */
        $scheduledByAdaptedId = [];

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

            $scheduled = $row->meal;
            $resolved = self::resolveMealForProfile($scheduled, $slotType, $profile);
            $resolvedByAdaptedId[(int) $resolved->id] = $resolved;
            $scheduledByAdaptedId[(int) $resolved->id] = $scheduled;
            $resolvedByAdaptedId[(int) $scheduled->id] = $resolved;
            $scheduledByAdaptedId[(int) $scheduled->id] = $scheduled;
        }

        return [
            'resolved' => $resolvedByAdaptedId,
            'scheduled' => $scheduledByAdaptedId,
        ];
    }

    /**
     * Default tier-preview picks: 1 breakfast, 2 mains, 1 side salad, 1 dessert (no soup).
     * Uses scheduled meal ids so admin tier preview selections match UI meal cards.
     *
     * @param  Collection<int, MealPlanDayMeal>  $dayRows
     * @return array<string, list<int>>
     */
    public static function defaultDaySelectionForRows(CustomerProfile $profile, Collection $dayRows): array
    {
        /** @var array<string, list<int>> $selection */
        $selection = [
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

            $slotType = $row->slot_type instanceof MealPlanSlotType
                ? $row->slot_type
                : MealPlanSlotType::tryFrom((string) $row->slot_type);

            if (! $slotType instanceof MealPlanSlotType) {
                continue;
            }

            $mealId = (int) $row->meal_id;

            if ($mealId <= 0) {
                continue;
            }

            match ($slotType) {
                MealPlanSlotType::Breakfast => count($selection['breakfasts']) < 1
                    ? $selection['breakfasts'][] = $mealId
                    : null,
                MealPlanSlotType::Main => count($selection['meals']) < 2
                    ? $selection['meals'][] = $mealId
                    : null,
                MealPlanSlotType::Salad => count($selection['sideSalads']) < 1
                    ? $selection['sideSalads'][] = $mealId
                    : null,
                MealPlanSlotType::Dessert => count($selection['desserts']) < 1
                    ? $selection['desserts'][] = $mealId
                    : null,
                default => null,
            };
        }

        return $selection;
    }

    private static function resolveMealForProfile(Meal $meal, MealPlanSlotType $slotType, CustomerProfile $profile): Meal
    {
        if ($slotType === MealPlanSlotType::Breakfast) {
            return SavoryEggBreakfastMeals::resolveMealForProfile($meal, $profile);
        }

        if ($slotType === MealPlanSlotType::Dessert) {
            if (DietProtocol::tryFromStored($profile->diet_protocol) === DietProtocol::NutrientDense) {
                return NutrientDenseDessertMeals::resolveMealForProfile($meal, $profile);
            }

            return ChiaDessertMeals::resolveMealForProfile($meal, $profile);
        }

        return $meal;
    }

    private static function bucketForSlotType(MealPlanSlotType $slotType): ?string
    {
        return match ($slotType) {
            MealPlanSlotType::Breakfast => 'breakfasts',
            MealPlanSlotType::Salad => 'sideSalads',
            MealPlanSlotType::Dessert => 'desserts',
            MealPlanSlotType::Soup => 'soup',
            default => null,
        };
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
