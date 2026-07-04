<?php

namespace App\Services;

use App\Enums\DietProtocol;
use App\Enums\MealPlanLibraryCategory;
use App\Http\Controllers\Admin\MealLibraryController;
use App\Models\CustomerProfile;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\MealPlanDayMeal;
use App\Models\User;
use App\Services\Nutrition\FullCraftDayMenuBuilder;
use Illuminate\Support\Collection;

final class MealPlanLibraryTierPreview
{
    /** @var list<string> */
    private const WEEKDAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    public function __construct(
        private MealLibraryController $mealLibrary,
    ) {}

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
        $mealPlan->loadMissing([
            'dayMeals' => static function ($query): void {
                $query->where('is_option_b', false)
                    ->orderBy('day_number')
                    ->orderBy('slot_type')
                    ->orderBy('slot_index');
            },
            'dayMeals.meal.ingredients',
        ]);

        $profile = $this->previewProfileForTier($mealPlan, $planTier, $user);
        $dayCount = max(1, $mealPlan->structuredPlanningDayCount());
        $categoryKeys = ['breakfasts', 'meals', 'sideSalads', 'desserts', 'soup'];
        $emptyCategories = array_fill_keys($categoryKeys, []);

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
            $buildOptions = $this->buildOptionsForDay($planTier, $dayNumber, $adaptedSelection);
            $resolvedMealsById = $uiMealMaps['resolved'];
            $scheduledMealsByAdaptedId = $uiMealMaps['scheduled'];

            $built = FullCraftDayMenuBuilder::buildPreviewDayFromRows(
                $profile,
                $dayNumber,
                $dayRows,
                $buildOptions,
            );

            $categories = $emptyCategories;

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
                    $resolvedMeal = $resolvedMealsById[$mealId] ?? $mealsById[$mealId] ?? null;
                    $scheduledMeal = $scheduledMealsByAdaptedId[$mealId] ?? $mealsById[$mealId] ?? $resolvedMeal;

                    if (! $resolvedMeal instanceof Meal || ! $scheduledMeal instanceof Meal) {
                        continue;
                    }

                    $baseRow = $this->mealLibrary->presentMealRowForUi($scheduledMeal);
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
     * @return array<string, mixed>
     */
    private function buildOptionsForDay(int $planTier, int $dayNumber, array $daySelection): array
    {
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

        return $options;
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

        $isNutrientDense = $mealPlan->plan_category === MealPlanLibraryCategory::NutrientDense;

        $profile->forceFill([
            'daily_calorie_target' => $planTier,
            'diet_protocol' => $this->dietProtocolForPlan($mealPlan)->value,
            'protein_percentage' => $isNutrientDense ? 32 : ($profile->protein_percentage ?? 35),
            'carb_percentage' => $isNutrientDense ? 28 : ($profile->carb_percentage ?? 35),
            'fat_percentage' => $isNutrientDense ? 40 : ($profile->fat_percentage ?? 30),
        ]);

        return $profile;
    }

    private function dietProtocolForPlan(MealPlan $mealPlan): DietProtocol
    {
        return match ($mealPlan->plan_category) {
            MealPlanLibraryCategory::NutrientDense => DietProtocol::NutrientDense,
            default => DietProtocol::Balanced,
        };
    }
}
