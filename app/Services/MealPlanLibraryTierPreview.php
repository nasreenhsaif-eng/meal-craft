<?php

namespace App\Services;

use App\Enums\MealPlanSlotType;
use App\Http\Controllers\Admin\MealLibraryController;
use App\Models\CustomerProfile;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\MealPlanDayMeal;
use App\Models\User;
use App\Services\Nutrition\AdaptedMenuFixedPortionResolver;
use App\Services\Nutrition\FullCraftDayMenuBuilder;
use App\Support\ChiaDessertMeals;
use App\Support\CraftLibraryTierMap;
use App\Support\NutrientDenseBreakfastOptions;
use App\Support\PrimaryFullCraftMainSlots;
use App\Support\ScheduledTiersMealResolver;
use Illuminate\Support\Collection;

final class MealPlanLibraryTierPreview
{
    /** @var list<string> */
    private const WEEKDAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    public function __construct(
        private MealLibraryController $mealLibrary,
    ) {}

    /**
     * Structured plan days with authored calorie tabs attached — no scaling.
     *
     * @return list<array{
     *     dayNumber: int,
     *     label: string,
     *     categories: array<string, list<array<string, mixed>>>,
     *     reconciliationWarnings: list<string>
     * }>
     */
    public function catalogDays(MealPlan $mealPlan): array
    {
        $mealPlan->loadMissing([
            'dayMeals' => static function ($query): void {
                $query->where('is_option_b', false)
                    ->orderBy('day_number')
                    ->orderBy('slot_type')
                    ->orderBy('slot_index');
            },
            'dayMeals.meal.ingredients',
            'dayMeals.meal.calorieTiers.ingredients',
        ]);

        $dayCount = max(1, $mealPlan->structuredPlanningDayCount());
        $categoryKeys = ['breakfasts', 'meals', 'sideSalads', 'desserts', 'soup'];
        $isNutrientDensePlan = $mealPlan->usesNutrientDenseProtocol();
        $storedDefaults = MealPlanDefaultDaySelections::forPlan($mealPlan);
        $hasStoredDefaults = $storedDefaults !== [];

        /** @var array<int, array{dayNumber: int, label: string, categories: array<string, list<array<string, mixed>>>, reconciliationWarnings: list<string>}> $daysByNumber */
        $daysByNumber = [];
        for ($dayNumber = 1; $dayNumber <= $dayCount; $dayNumber++) {
            $daysByNumber[$dayNumber] = [
                'dayNumber' => $dayNumber,
                'label' => self::WEEKDAY_LABELS[$dayNumber - 1] ?? __('Day :number', ['number' => $dayNumber]),
                'categories' => array_fill_keys($categoryKeys, []),
                'reconciliationWarnings' => [],
            ];
        }

        foreach ($mealPlan->dayMeals as $dayMeal) {
            if (! $dayMeal instanceof MealPlanDayMeal || $dayMeal->meal === null) {
                continue;
            }

            $dayNumber = (int) $dayMeal->day_number;
            if (! isset($daysByNumber[$dayNumber])) {
                continue;
            }

            $slotType = $dayMeal->slot_type instanceof MealPlanSlotType
                ? $dayMeal->slot_type
                : MealPlanSlotType::tryFrom((string) $dayMeal->slot_type);

            if (! $slotType instanceof MealPlanSlotType) {
                continue;
            }

            $categoryKey = $this->slotTypeToCategoryKey($slotType);
            $presentedMeal = ScheduledTiersMealResolver::forMeal($dayMeal->meal);
            $row = $this->mealLibrary->presentMealRowForUi($presentedMeal);
            $row['calorieTiers'] = $this->mealLibrary->compactCalorieTiersForPlanPreview($presentedMeal);
            $slotIndex = (int) $dayMeal->slot_index;

            if (
                $isNutrientDensePlan
                && $categoryKey === 'desserts'
                && ($slotIndex === 3 || ChiaDessertMeals::isChiaDessert($dayMeal->meal))
            ) {
                $categoryKey = 'breakfasts';
                $slotIndex = NutrientDenseBreakfastOptions::CHIA_SLOT_INDEX;
            }

            $row['plan_slot_index'] = $slotIndex;

            if ($hasStoredDefaults) {
                $storedIds = $storedDefaults[$dayNumber][$categoryKey] ?? [];
                $row['isRecommended'] = in_array((int) $presentedMeal->id, $storedIds, true)
                    || in_array((int) $dayMeal->meal_id, $storedIds, true);
            } elseif ($categoryKey === 'meals') {
                $primarySlots = $isNutrientDensePlan
                    ? PrimaryFullCraftMainSlots::NUTRIENT_DENSE
                    : PrimaryFullCraftMainSlots::BALANCED;
                $row['isRecommended'] = in_array($slotIndex, $primarySlots, true);
            } else {
                $row['isRecommended'] = $slotIndex === 1;
            }

            $daysByNumber[$dayNumber]['categories'][$categoryKey][] = $row;
        }

        return array_values($daysByNumber);
    }

    /**
     * @param  array<int, array<string, list<int|string>>>  $daySelectionsByDay  dayNumber => categoryKey => meal ids
     * @return list<array{
     *     dayNumber: int,
     *     label: string,
     *     categories: array<string, list<array<string, mixed>>>,
     *     reconciliationWarnings: list<string>
     * }>
     */
    public function daysForTier(
        MealPlan $mealPlan,
        int $planTier,
        User $user,
        array $daySelectionsByDay = [],
    ): array {
        $days = $this->catalogDays($mealPlan);

        if ($this->catalogUsesLibraryTabs($days)) {
            return $this->overlayLibraryTabs($days, $planTier);
        }

        $mealPlan->loadMissing([
            'dayMeals' => static function ($query): void {
                $query->where('is_option_b', false)
                    ->orderBy('day_number')
                    ->orderBy('slot_type')
                    ->orderBy('slot_index');
            },
            'dayMeals.meal.ingredients',
            'dayMeals.meal.calorieTiers.ingredients',
        ]);

        $profile = $this->previewProfileForTier($mealPlan, $planTier, $user);
        $dayCount = max(1, $mealPlan->structuredPlanningDayCount());
        $categoryKeys = ['breakfasts', 'meals', 'sideSalads', 'desserts', 'soup'];

        /** @var Collection<int, Collection<int, MealPlanDayMeal>> $rowsByDay */
        $rowsByDay = $mealPlan->dayMeals->groupBy('day_number');

        /** @var array<int, Meal> $mealsById */
        $mealsById = [];

        foreach ($mealPlan->dayMeals as $dayMeal) {
            if ($dayMeal->meal instanceof Meal) {
                $mealsById[(int) $dayMeal->meal->id] = $dayMeal->meal;
            }
        }

        /** @var list<array{dayNumber: int, label: string, categories: array<string, list<array<string, mixed>>>, reconciliationWarnings: list<string>}> $days */
        $days = [];

        for ($dayNumber = 1; $dayNumber <= $dayCount; $dayNumber++) {
            /** @var Collection<int, MealPlanDayMeal> $dayRows */
            $dayRows = $rowsByDay->get($dayNumber, collect());

            $daySelection = $daySelectionsByDay[$dayNumber] ?? [];

            $uiMealMaps = FullCraftDayMenuBuilder::uiMealMapsForDay($profile, $dayRows);

            if ($daySelection === []) {
                $daySelection = FullCraftDayMenuBuilder::defaultDaySelectionForRows($profile, $dayRows);
            }

            $adaptedSelection = $this->translateSelectionToAdaptedIds($daySelection, $uiMealMaps);
            $buildOptions = $this->buildOptionsForDay($planTier, $dayNumber, $adaptedSelection, $profile, $mealsById);
            $resolvedMealsById = $uiMealMaps['resolved'];

            $built = FullCraftDayMenuBuilder::buildPreviewDayFromRows(
                $profile,
                $dayNumber,
                $dayRows,
                $buildOptions,
            );

            $categories = array_fill_keys($categoryKeys, []);

            foreach ($categoryKeys as $categoryKey) {
                $bucket = match ($categoryKey) {
                    'breakfasts' => 'breakfasts',
                    'meals' => 'meals',
                    'sideSalads' => 'sideSalads',
                    'desserts' => 'desserts',
                    'soup' => 'soup',
                    default => null,
                };

                if ($bucket === null) {
                    continue;
                }

                foreach ($built['dayMenu'][$bucket] ?? [] as $adapted) {
                    if (! is_array($adapted)) {
                        continue;
                    }

                    $mealId = (int) ($adapted['id'] ?? 0);
                    $resolvedMeal = $resolvedMealsById[$mealId] ?? $mealsById[$mealId] ?? Meal::query()->with('ingredients')->find($mealId);

                    if (! $resolvedMeal instanceof Meal) {
                        continue;
                    }

                    $baseRow = $this->mealLibrary->presentMealRowForUi($resolvedMeal);
                    $categories[$categoryKey][] = $this->mealLibrary->applyAdaptedToMealRow(
                        $baseRow,
                        $adapted,
                        $resolvedMeal,
                    );
                }
            }

            $days[] = [
                'dayNumber' => $dayNumber,
                'label' => self::WEEKDAY_LABELS[$dayNumber - 1] ?? __('Day :number', ['number' => $dayNumber]),
                'categories' => $categories,
                'reconciliationWarnings' => $built['warnings'],
            ];
        }

        return $days;
    }

    /**
     * @param  array<string, list<int|string>>  $daySelection
     * @param  array<int, Meal>  $mealsById
     * @return array<string, mixed>
     */
    private function buildOptionsForDay(
        int $planTier,
        int $dayNumber,
        array $daySelection,
        CustomerProfile $profile,
        array $mealsById,
    ): array {
        $options = [
            'plan_tier' => (float) $planTier,
            'craft_key' => 'full',
            'day_of_week' => $dayNumber,
        ];

        $selectionKeys = [
            'breakfasts' => 'selected_breakfast_meal_ids',
            'meals' => 'selected_main_meal_ids',
            'sideSalads' => 'selected_side_salad_meal_ids',
            'desserts' => 'selected_dessert_meal_ids',
            'soup' => 'selected_soup_meal_ids',
        ];

        foreach ($selectionKeys as $categoryKey => $optionKey) {
            $mealIds = $this->normalizeMealIds($daySelection[$categoryKey] ?? []);

            if ($mealIds !== []) {
                $options[$optionKey] = $mealIds;
            }
        }

        $fixedSlots = [];

        if ($this->normalizeMealIds($daySelection['sideSalads'] ?? []) !== []) {
            $fixedSlots[] = 'side_salad';
        }

        if ($this->normalizeMealIds($daySelection['desserts'] ?? []) !== []) {
            $fixedSlots[] = 'dessert';
        }

        if ($this->normalizeMealIds($daySelection['soup'] ?? []) !== []) {
            $fixedSlots[] = 'soup';
        }

        if ($fixedSlots !== []) {
            $options['selected_fixed_slots'] = $fixedSlots;
        }

        $selectedFixedCalories = AdaptedMenuFixedPortionResolver::fromSelectedCarouselMeals(
            $profile,
            $daySelection,
            $mealsById,
            $options,
        );

        return array_merge($options, $selectedFixedCalories);
    }

    /**
     * Map UI / scheduled meal ids to adapted ids used in reconciled day menus.
     *
     * @param  array<string, list<int|string>>  $daySelection
     * @param  array{resolved: array<int, Meal>, scheduled: array<int, Meal>}  $uiMealMaps
     * @return array<string, list<int>>
     */
    private function translateSelectionToAdaptedIds(array $daySelection, array $uiMealMaps): array
    {
        /** @var array<int, Meal> $resolvedBySelectionId */
        $resolvedBySelectionId = $uiMealMaps['resolved'];

        /** @var array<string, list<int>> $adaptedSelection */
        $adaptedSelection = [];

        foreach ($daySelection as $categoryKey => $mealIds) {
            if (! is_array($mealIds)) {
                continue;
            }

            $adaptedSelection[$categoryKey] = [];

            foreach ($this->normalizeMealIds($mealIds) as $mealId) {
                $resolved = $resolvedBySelectionId[$mealId] ?? null;
                $adaptedSelection[$categoryKey][] = $resolved instanceof Meal
                    ? (int) $resolved->id
                    : $mealId;
            }
        }

        return $adaptedSelection;
    }

    /**
     * @param  list<int|string>  $raw
     * @return list<int>
     */
    private function normalizeMealIds(array $raw): array
    {
        $ids = [];

        foreach ($raw as $id) {
            $normalized = (int) $id;

            if ($normalized > 0) {
                $ids[] = $normalized;
            }
        }

        return array_values(array_unique($ids));
    }

    private function previewProfileForTier(MealPlan $mealPlan, int $planTier, User $user): CustomerProfile
    {
        $user->loadMissing('customerProfile');

        $profile = $user->customerProfile ?? new CustomerProfile([
            'user_id' => $user->id,
        ]);

        $isNutrientDense = $mealPlan->usesNutrientDenseProtocol();

        $profile->forceFill([
            'daily_calorie_target' => $planTier,
            'diet_protocol' => $mealPlan->dietProtocol()->value,
            'protein_percentage' => $isNutrientDense ? 32 : ($profile->protein_percentage ?? 35),
            'carb_percentage' => $isNutrientDense ? 28 : ($profile->carb_percentage ?? 35),
            'fat_percentage' => $isNutrientDense ? 40 : ($profile->fat_percentage ?? 30),
        ]);

        return $profile;
    }

    /**
     * @param  list<array{categories: array<string, list<array<string, mixed>>>}>  $days
     */
    private function catalogUsesLibraryTabs(array $days): bool
    {
        foreach ($days as $day) {
            foreach (['breakfasts', 'meals'] as $categoryKey) {
                foreach ($day['categories'][$categoryKey] ?? [] as $meal) {
                    if (is_array($meal['calorieTiers'] ?? null) && $meal['calorieTiers'] !== []) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param  list<array{dayNumber: int, label: string, categories: array<string, list<array<string, mixed>>>, reconciliationWarnings?: list<string>}>  $days
     * @return list<array{dayNumber: int, label: string, categories: array<string, list<array<string, mixed>>>, reconciliationWarnings: list<string>}>
     */
    private function overlayLibraryTabs(array $days, int $planTier): array
    {
        $map = CraftLibraryTierMap::row(CraftLibraryTierMap::DEFAULT_CRAFT, $planTier);

        foreach ($days as $index => $day) {
            $categories = $day['categories'] ?? [];
            $categories['breakfasts'] = $this->overlayCategoryMeals($categories['breakfasts'] ?? [], $map['breakfast']);
            $categories['meals'] = $this->overlayCategoryMeals($categories['meals'] ?? [], $map['main_each']);

            if (! $map['include_side_salad']) {
                $categories['sideSalads'] = [];
            }

            if (! $map['include_dessert']) {
                $categories['desserts'] = [];
            }

            if (! $map['include_soup']) {
                $categories['soup'] = [];
            }

            if ($map['breakfast'] <= 0) {
                $categories['breakfasts'] = [];
            }

            $days[$index]['categories'] = $categories;
            $days[$index]['reconciliationWarnings'] = [];
        }

        return $days;
    }

    /**
     * @param  list<array<string, mixed>>  $meals
     * @return list<array<string, mixed>>
     */
    private function overlayCategoryMeals(array $meals, int $tab): array
    {
        if ($tab <= 0) {
            return $meals;
        }

        return array_map(fn (array $meal): array => $this->overlayMealRowFromTabs($meal, $tab), $meals);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function overlayMealRowFromTabs(array $row, int $tab): array
    {
        $tiers = is_array($row['calorieTiers'] ?? null) ? $row['calorieTiers'] : [];

        foreach ($tiers as $tier) {
            if (! is_array($tier) || (int) ($tier['calorie_tier'] ?? 0) !== $tab) {
                continue;
            }

            /** @var array{calories: int, protein: float, carbs: float, fat: float} $macros */
            $macros = is_array($tier['macros'] ?? null) ? $tier['macros'] : ($row['macros'] ?? []);
            $row['macros'] = $macros;
            $row['caloriesNumber'] = $macros['calories'] ?? null;
            $row['isScaled'] = false;
            $row['libraryCalorieTier'] = $tab;
            $row['kitchenIngredientRows'] = $tier['kitchenIngredientRows'] ?? [];

            if (isset($row['detailView']) && is_array($row['detailView'])) {
                $row['detailView']['macros'] = $macros;

                if (is_array($tier['nutrition'] ?? null)) {
                    $row['detailView']['nutrition'] = $tier['nutrition'];
                }

                if (is_array($tier['nutritionalData'] ?? null)) {
                    $row['detailView']['nutritionalData'] = $tier['nutritionalData'];
                }
            }

            return $row;
        }

        $row['isScaled'] = false;

        return $row;
    }

    private function slotTypeToCategoryKey(MealPlanSlotType $slotType): string
    {
        return match ($slotType) {
            MealPlanSlotType::Breakfast => 'breakfasts',
            MealPlanSlotType::Main => 'meals',
            MealPlanSlotType::Salad => 'sideSalads',
            MealPlanSlotType::Dessert => 'desserts',
            MealPlanSlotType::Soup => 'soup',
        };
    }
}
