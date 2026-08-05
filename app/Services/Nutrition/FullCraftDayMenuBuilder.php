<?php

namespace App\Services\Nutrition;

use App\Enums\DietProtocol;
use App\Enums\MealPlanSlotType;
use App\Models\CustomerProfile;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\MealPlanDayMeal;
use App\Services\MealPlanDefaultDaySelections;
use App\Support\ChiaDessertMeals;
use App\Support\NutrientDenseBreakfastOptions;
use App\Support\NutrientDenseDessertMeals;
use App\Support\PrimaryFullCraftMainSlots;
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

        $selectedMainMealIds = self::normalizeSelectedMainMealIds($adaptOptions['selected_main_meal_ids'] ?? null);
        $applySelection = $selectedMainMealIds !== [];

        $mealPlan = self::mealPlanFromDayRows($dayRows);
        $defaultMainIds = $mealPlan instanceof MealPlan
            ? MealPlanDefaultDaySelections::mealIdsForCategory($mealPlan, $dayNumber, 'meals')
            : [];

        if (! $applySelection) {
            $selectedMainMealIds = $defaultMainIds !== []
                ? array_values(array_filter(
                    $defaultMainIds,
                    static fn (int $id): bool => self::carouselContainsMealId($carouselMainsBySlot, $id),
                ))
                : [];

            if ($selectedMainMealIds === []) {
                $selectedMainMealIds = self::defaultSelectedMainMealIdsFromCarousel($profile, $carouselMainsBySlot);
            }
        }

        /** @var list<Meal> $selectedMealModels */
        $selectedMealModels = [];

        foreach ($carouselMainsBySlot as $meal) {
            if (in_array((int) $meal->id, $selectedMainMealIds, true)) {
                $selectedMealModels[] = $meal;
            }
        }

        $primaryMainMeals = self::primaryMainMealsFromCarousel($profile, $carouselMainsBySlot, $mealPlan, $dayNumber);

        if (NutrientDenseBreakfastOptions::appliesTo($profile)) {
            $dayMenu['breakfasts'] = self::buildNutrientDenseBreakfastDeck(
                $profile,
                $dayNumber,
                $dayAdaptOptions,
                $mealPlan,
            );
        } else {
            $dayMenu['breakfasts'] = self::stampRecommendedFromPlanDefaults(
                self::stampSlotMetadataOnAdaptedList($dayMenu['breakfasts'], 1, true),
                $mealPlan,
                $dayNumber,
                'breakfasts',
            );
        }

        $dayMenu = self::stampFixedSidesRecommendedFromPlanDefaults($dayMenu, $mealPlan, $dayNumber);

        return self::finalizeDayMenuWithMainReconciliation(
            $profile,
            $dayMenu,
            $carouselMainsBySlot,
            $selectedMealModels,
            $primaryMainMeals,
            $dayAdaptOptions,
            $selectedMainMealIds,
            $mealPlan,
            $dayNumber,
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

            $slotIndex = (int) $row->slot_index;

            if ($slotType === MealPlanSlotType::Main && in_array($slotIndex, [1, 2, 3, 4, 5, 6], true)) {
                $carouselMainsBySlot[$slotIndex] = $row->meal;

                continue;
            }

            if ($slotType === MealPlanSlotType::Breakfast && NutrientDenseBreakfastOptions::appliesTo($profile)) {
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

            if ($slotType === MealPlanSlotType::Breakfast && $slotIndex === 1) {
                if (ChiaDessertMeals::isChiaDessert($row->meal) || ! SavoryEggBreakfastMeals::isSavoryEggBreakfast($row->meal)) {
                    continue;
                }

                $dayMenu['breakfasts'][] = self::stampSlotMetadata($adapted, $slotIndex, true);
            } elseif ($slotType === MealPlanSlotType::Salad && in_array($slotIndex, [1, 2], true)) {
                $dayMenu['sideSalads'][] = $adapted;
            } elseif ($slotType === MealPlanSlotType::Dessert && in_array($slotIndex, [1, 2], true)) {
                $dayMenu['desserts'][] = $adapted;
            } elseif ($slotType === MealPlanSlotType::Soup) {
                $dayMenu['soup'][] = $adapted;
            }
        }

        if (NutrientDenseBreakfastOptions::appliesTo($profile)) {
            $dayMenu['breakfasts'] = self::buildNutrientDenseBreakfastDeck(
                $profile,
                $dayNumber,
                $dayAdaptOptions,
                self::mealPlanFromDayRows($dayRows),
            );
        } elseif ($dayMenu['breakfasts'] === []) {
            $fallbackMeal = ProductionWeeklyMenuSchedule::resolveRotationBreakfastMeal($dayNumber, $profile);

            if ($fallbackMeal instanceof Meal) {
                $adapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $fallbackMeal, array_merge(
                    $dayAdaptOptions,
                    ['schedule_slot' => AdaptedMenuBuilder::adaptationSlotForMealPlanSlot(MealPlanSlotType::Breakfast)],
                ));

                if ($adapted !== null) {
                    $dayMenu['breakfasts'][] = self::stampSlotMetadata($adapted, 1, true);
                }
            }
        }

        $mealPlan = self::mealPlanFromDayRows($dayRows);
        $dayMenu = self::stampFixedSidesRecommendedFromPlanDefaults($dayMenu, $mealPlan, $dayNumber);
        $dayMenu['breakfasts'] = self::stampRecommendedFromPlanDefaults(
            $dayMenu['breakfasts'],
            $mealPlan,
            $dayNumber,
            'breakfasts',
        );

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

        ksort($carouselMainsBySlot);
        $primaryMainMeals = self::primaryMainMealsFromCarousel($profile, $carouselMainsBySlot, $mealPlan, $dayNumber);

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
            $mealPlan,
            $dayNumber,
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
        ?MealPlan $mealPlan = null,
        int $dayNumber = 0,
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

        foreach ($carouselMainsBySlot as $slotIndex => $meal) {
            $adapted = $adaptedById[$meal->id] ?? null;

            if (is_array($adapted)) {
                $isRecommended = self::isMainMealRecommended(
                    $profile,
                    $mealPlan,
                    $dayNumber,
                    (int) $meal->id,
                    (int) $slotIndex,
                );

                $dayMenu['meals'][] = self::stampSlotMetadata(
                    $adapted,
                    (int) $slotIndex,
                    $isRecommended,
                );
            }
        }

        $reconciliationMains = $selectedMealModels !== [] ? $selectedMealModels : $primaryMainMeals;

        if ($selectedMainMealIds !== []) {
            $mainAdaptOptions['selected_main_meal_ids'] = $selectedMainMealIds;
        }

        $breakfastIds = self::normalizeSelectedMainMealIds($mainAdaptOptions['selected_breakfast_meal_ids'] ?? null);

        if ($breakfastIds === [] && count($dayMenu['breakfasts'] ?? []) > 1) {
            $recommendedId = null;

            foreach ($dayMenu['breakfasts'] as $breakfast) {
                if (! is_array($breakfast)) {
                    continue;
                }

                if (! empty($breakfast['is_recommended'])) {
                    $recommendedId = (int) ($breakfast['id'] ?? 0);
                    break;
                }
            }

            if ($recommendedId === null || $recommendedId <= 0) {
                $recommendedId = (int) (($dayMenu['breakfasts'][0]['id'] ?? 0));
            }

            if ($recommendedId > 0) {
                $mainAdaptOptions['selected_breakfast_meal_ids'] = [$recommendedId];
            }
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
        $mealPlan = self::mealPlanFromDayRows($dayRows);
        $dayNumber = 1;

        foreach ($dayRows as $row) {
            if ($row instanceof MealPlanDayMeal) {
                $dayNumber = max($dayNumber, (int) $row->day_number);
            }
        }

        if ($mealPlan instanceof MealPlan) {
            $stored = MealPlanDefaultDaySelections::forDay($mealPlan, $dayNumber);

            if ($stored !== null) {
                return $stored;
            }
        }

        return self::conventionDaySelectionForRows($profile, $dayRows);
    }

    /**
     * Protocol convention defaults (ignores admin-saved picks).
     *
     * @param  Collection<int, MealPlanDayMeal>  $dayRows
     * @return array<string, list<int>>
     */
    public static function conventionDaySelectionForRows(CustomerProfile $profile, Collection $dayRows): array
    {
        /** @var array<string, list<int>> $selection */
        $selection = [
            'breakfasts' => [],
            'meals' => [],
            'sideSalads' => [],
            'desserts' => [],
            'soup' => [],
        ];

        /** @var array<int, int> $mainIdsBySlot */
        $mainIdsBySlot = [];
        $dayNumber = 1;

        foreach ($dayRows as $row) {
            if (! $row instanceof MealPlanDayMeal || ! $row->meal instanceof Meal) {
                continue;
            }

            $dayNumber = max($dayNumber, (int) $row->day_number);

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
                MealPlanSlotType::Main => $mainIdsBySlot[(int) $row->slot_index] = $mealId,
                MealPlanSlotType::Salad => count($selection['sideSalads']) < 1
                    ? $selection['sideSalads'][] = $mealId
                    : null,
                MealPlanSlotType::Dessert => count($selection['desserts']) < 1
                    ? $selection['desserts'][] = $mealId
                    : null,
                default => null,
            };
        }

        if (NutrientDenseBreakfastOptions::appliesTo($profile)) {
            $omelette = NutrientDenseBreakfastOptions::resolveOmeletteMeal($profile);

            if ($omelette instanceof Meal) {
                $selection['breakfasts'][] = (int) $omelette->id;
            }
        } else {
            foreach ($dayRows as $row) {
                if (! $row instanceof MealPlanDayMeal || ! $row->meal instanceof Meal) {
                    continue;
                }

                $slotType = $row->slot_type instanceof MealPlanSlotType
                    ? $row->slot_type
                    : MealPlanSlotType::tryFrom((string) $row->slot_type);

                if ($slotType === MealPlanSlotType::Breakfast && count($selection['breakfasts']) < 1) {
                    $selection['breakfasts'][] = (int) $row->meal_id;
                    break;
                }
            }
        }

        foreach (PrimaryFullCraftMainSlots::forProfile($profile) as $slotIndex) {
            if (isset($mainIdsBySlot[$slotIndex]) && count($selection['meals']) < 2) {
                $selection['meals'][] = $mainIdsBySlot[$slotIndex];
            }
        }

        if ($selection['meals'] === [] && $mainIdsBySlot !== []) {
            ksort($mainIdsBySlot);
            $selection['meals'] = array_slice(array_values($mainIdsBySlot), 0, 2);
        }

        return $selection;
    }

    /**
     * @param  array<int, Meal>  $carouselMainsBySlot
     * @return list<int>
     */
    private static function defaultSelectedMainMealIdsFromCarousel(CustomerProfile $profile, array $carouselMainsBySlot): array
    {
        $ids = [];

        foreach (PrimaryFullCraftMainSlots::forProfile($profile) as $slotIndex) {
            if (isset($carouselMainsBySlot[$slotIndex])) {
                $ids[] = (int) $carouselMainsBySlot[$slotIndex]->id;
            }
        }

        if ($ids !== []) {
            return $ids;
        }

        ksort($carouselMainsBySlot);

        return array_slice(
            array_map(static fn (Meal $meal): int => (int) $meal->id, array_values($carouselMainsBySlot)),
            0,
            2,
        );
    }

    /**
     * @param  array<int, Meal>  $carouselMainsBySlot
     * @return list<Meal>
     */
    private static function primaryMainMealsFromCarousel(
        CustomerProfile $profile,
        array $carouselMainsBySlot,
        ?MealPlan $mealPlan = null,
        int $dayNumber = 0,
    ): array {
        if ($mealPlan instanceof MealPlan && $dayNumber > 0) {
            $defaultIds = MealPlanDefaultDaySelections::mealIdsForCategory($mealPlan, $dayNumber, 'meals');
            $meals = [];

            foreach ($defaultIds as $mealId) {
                foreach ($carouselMainsBySlot as $meal) {
                    if ((int) $meal->id === $mealId) {
                        $meals[] = $meal;
                        break;
                    }
                }
            }

            if ($meals !== []) {
                return array_slice($meals, 0, 2);
            }
        }

        $meals = [];

        foreach (PrimaryFullCraftMainSlots::forProfile($profile) as $slotIndex) {
            if (isset($carouselMainsBySlot[$slotIndex])) {
                $meals[] = $carouselMainsBySlot[$slotIndex];
            }
        }

        if ($meals !== []) {
            return $meals;
        }

        ksort($carouselMainsBySlot);

        return array_slice(array_values($carouselMainsBySlot), 0, 2);
    }

    /**
     * @param  array<string, mixed>  $dayAdaptOptions
     * @return list<array<string, mixed>>
     */
    private static function buildNutrientDenseBreakfastDeck(
        CustomerProfile $profile,
        int $dayNumber,
        array $dayAdaptOptions,
        ?MealPlan $mealPlan = null,
    ): array {
        $deck = [];
        $recommendedIds = $mealPlan instanceof MealPlan
            ? MealPlanDefaultDaySelections::mealIdsForCategory($mealPlan, $dayNumber, 'breakfasts')
            : [];

        foreach (NutrientDenseBreakfastOptions::optionMealsForDay($dayNumber, $profile, $recommendedIds) as $option) {
            $adapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $option['meal'], array_merge(
                $dayAdaptOptions,
                ['schedule_slot' => AdaptedMenuBuilder::adaptationSlotForMealPlanSlot(MealPlanSlotType::Breakfast)],
            ));

            if ($adapted === null) {
                continue;
            }

            $deck[] = self::stampSlotMetadata(
                $adapted,
                $option['plan_slot_index'],
                $option['is_recommended'],
            );
        }

        return $deck;
    }

    /**
     * @param  array<string, mixed>  $adapted
     * @return array<string, mixed>
     */
    private static function stampSlotMetadata(array $adapted, int $planSlotIndex, bool $isRecommended): array
    {
        $adapted['plan_slot_index'] = $planSlotIndex;
        $adapted['is_recommended'] = $isRecommended;

        return $adapted;
    }

    /**
     * @param  list<array<string, mixed>>  $adaptedList
     * @return list<array<string, mixed>>
     */
    private static function stampSlotMetadataOnAdaptedList(array $adaptedList, int $planSlotIndex, bool $isRecommended): array
    {
        return array_map(
            static fn (array $adapted): array => self::stampSlotMetadata($adapted, $planSlotIndex, $isRecommended),
            $adaptedList,
        );
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

    /**
     * @param  Collection<int, MealPlanDayMeal>  $dayRows
     */
    private static function mealPlanFromDayRows(Collection $dayRows): ?MealPlan
    {
        $first = $dayRows->first(static fn (mixed $row): bool => $row instanceof MealPlanDayMeal);

        if (! $first instanceof MealPlanDayMeal) {
            return null;
        }

        $first->loadMissing('mealPlan');

        return $first->mealPlan instanceof MealPlan ? $first->mealPlan : null;
    }

    /**
     * @param  array<int, Meal>  $carouselMainsBySlot
     */
    private static function carouselContainsMealId(array $carouselMainsBySlot, int $mealId): bool
    {
        foreach ($carouselMainsBySlot as $meal) {
            if ((int) $meal->id === $mealId) {
                return true;
            }
        }

        return false;
    }

    private static function isMainMealRecommended(
        CustomerProfile $profile,
        ?MealPlan $mealPlan,
        int $dayNumber,
        int $mealId,
        int $slotIndex,
    ): bool {
        if ($mealPlan instanceof MealPlan && $dayNumber > 0) {
            $flag = MealPlanDefaultDaySelections::isRecommendedMealId($mealPlan, $dayNumber, 'meals', $mealId);

            if ($flag !== null) {
                return $flag;
            }
        }

        return PrimaryFullCraftMainSlots::isPrimarySlot($slotIndex, $profile);
    }

    /**
     * @param  list<array<string, mixed>>  $adaptedList
     * @return list<array<string, mixed>>
     */
    private static function stampRecommendedFromPlanDefaults(
        array $adaptedList,
        ?MealPlan $mealPlan,
        int $dayNumber,
        string $categoryKey,
    ): array {
        if (! $mealPlan instanceof MealPlan || $dayNumber < 1) {
            return $adaptedList;
        }

        $dayDefaults = MealPlanDefaultDaySelections::forDay($mealPlan, $dayNumber);

        if ($dayDefaults === null) {
            return $adaptedList;
        }

        $recommendedIds = $dayDefaults[$categoryKey] ?? [];

        return array_map(static function (array $adapted) use ($recommendedIds): array {
            $mealId = (int) ($adapted['id'] ?? 0);
            $adapted['is_recommended'] = $mealId > 0 && in_array($mealId, $recommendedIds, true);

            return $adapted;
        }, $adaptedList);
    }

    /**
     * @param  array{
     *     breakfasts?: list<array<string, mixed>>,
     *     meals?: list<array<string, mixed>>,
     *     sideSalads?: list<array<string, mixed>>,
     *     desserts?: list<array<string, mixed>>,
     *     soup?: list<array<string, mixed>>
     * }  $dayMenu
     * @return array{
     *     breakfasts?: list<array<string, mixed>>,
     *     meals?: list<array<string, mixed>>,
     *     sideSalads?: list<array<string, mixed>>,
     *     desserts?: list<array<string, mixed>>,
     *     soup?: list<array<string, mixed>>
     * }
     */
    private static function stampFixedSidesRecommendedFromPlanDefaults(
        array $dayMenu,
        ?MealPlan $mealPlan,
        int $dayNumber,
    ): array {
        foreach (['sideSalads', 'desserts', 'soup'] as $categoryKey) {
            $bucket = $dayMenu[$categoryKey] ?? [];

            if (! is_array($bucket) || $bucket === []) {
                continue;
            }

            if ($mealPlan instanceof MealPlan && MealPlanDefaultDaySelections::forDay($mealPlan, $dayNumber) !== null) {
                $dayMenu[$categoryKey] = self::stampRecommendedFromPlanDefaults(
                    $bucket,
                    $mealPlan,
                    $dayNumber,
                    $categoryKey,
                );

                continue;
            }

            // Convention: first salad + first dessert recommended when no admin defaults.
            $dayMenu[$categoryKey] = array_map(
                static function (array $adapted, int $index) use ($categoryKey): array {
                    $adapted['is_recommended'] = match ($categoryKey) {
                        'sideSalads', 'desserts' => $index === 0,
                        default => false,
                    };

                    return $adapted;
                },
                $bucket,
                array_keys($bucket),
            );
        }

        return $dayMenu;
    }
}
