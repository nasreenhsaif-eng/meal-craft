<?php

namespace App\Services;

use App\Enums\CustomerCraftMealSlot;
use App\Http\Controllers\Admin\MealLibraryController;
use App\Models\CustomerCraftPlan;
use App\Models\CustomerCraftPlanDay;
use App\Models\CustomerCraftPlanDayMeal;
use App\Models\CustomerProfile;
use App\Models\Meal;
use App\Services\Nutrition\AdaptedMenuBuilder;
use App\Services\Nutrition\CraftCaloriePlanner;
use App\Services\Nutrition\UserPlanCalculator;

final class CustomerCraftPlanPresentationService
{
    /** @var list<string> */
    private const WEEKDAY_LABELS = [
        'Sunday',
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
    ];

    /** @var array<string, string> */
    private const CRAFT_TITLES = [
        'full' => 'Full Craft',
        'day' => 'Day Craft',
        'afternoon' => 'Afternoon Craft',
        'intermittent' => 'Intermittent Craft',
        'business' => 'Business Craft',
    ];

    /** @var array<string, string> */
    private const SLOT_TO_CATEGORY = [
        'breakfast' => 'breakfasts',
        'main' => 'meals',
        'side_salad' => 'sideSalads',
        'dessert' => 'desserts',
        'soup' => 'soup',
    ];

    public function __construct(
        private MealLibraryController $mealLibrary,
    ) {}

    /**
     * @return array{
     *     craftKey: string,
     *     craftTitle: string,
     *     weekDuration: int,
     *     selectedWeekdays: list<int>,
     *     planTierCalories: int,
     *     submittedAt: string|null,
     *     days: list<array{
     *         dayNumber: int,
     *         label: string,
     *         includeSoup: bool,
     *         categories: array{
     *             breakfasts: list<array<string, mixed>>,
     *             meals: list<array<string, mixed>>,
     *             sideSalads: list<array<string, mixed>>,
     *             desserts: list<array<string, mixed>>,
     *             soup: list<array<string, mixed>>
     *         }
     *     }>
     * }
     */
    public function presentSummary(CustomerCraftPlan $plan, int $planTierCalories): array
    {
        $plan->loadMissing(['customerProfile', 'days.meals.meal.ingredients']);

        $profile = $plan->customerProfile;

        if ($profile instanceof CustomerProfile && $profile->daily_calorie_target !== null) {
            $basePlan = UserPlanCalculator::calculateUserPlan($profile);
            $craftPlan = CraftCaloriePlanner::applyCraftToPlan($basePlan, $plan->craft_key);
            $craftDayCalories = (float) ($craftPlan['craft_day_calories'] ?? 0);

            if ($craftDayCalories > 0) {
                $planTierCalories = (int) round($craftDayCalories);
            }
        }

        $categoryKeys = ['breakfasts', 'meals', 'sideSalads', 'desserts', 'soup'];
        $emptyCategories = array_fill_keys($categoryKeys, []);

        /** @var list<array{dayNumber: int, label: string, includeSoup: bool, categories: array<string, list<array<string, mixed>>>}> $presentedDays */
        $presentedDays = [];

        foreach ($plan->days->sortBy('day_of_week')->values() as $day) {
            if (! $day instanceof CustomerCraftPlanDay) {
                continue;
            }

            $categories = $profile instanceof CustomerProfile
                ? $this->presentAdaptedCategoriesForDay($profile, $plan, $day)
                : $this->presentBaselineCategoriesForDay($day);

            $dayNumber = (int) $day->day_of_week;

            $presentedDays[] = [
                'dayNumber' => $dayNumber,
                'label' => self::WEEKDAY_LABELS[$dayNumber - 1] ?? __('Day :number', ['number' => $dayNumber]),
                'includeSoup' => (bool) $day->include_soup,
                'categories' => $categories !== [] ? $categories : $emptyCategories,
            ];
        }

        return [
            'craftKey' => $plan->craft_key,
            'craftTitle' => self::CRAFT_TITLES[$plan->craft_key] ?? ucfirst($plan->craft_key),
            'weekDuration' => (int) $plan->week_duration,
            'selectedWeekdays' => array_values($plan->selected_weekdays ?? []),
            'planTierCalories' => $planTierCalories,
            'submittedAt' => $plan->submitted_at?->toIso8601String(),
            'days' => $presentedDays,
        ];
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function presentAdaptedCategoriesForDay(
        CustomerProfile $profile,
        CustomerCraftPlan $plan,
        CustomerCraftPlanDay $day,
    ): array {
        $adaptOptions = [
            'include_soup' => $day->include_soup,
            'craft_key' => $plan->craft_key,
            ...self::fixedPortionAdaptOptions($day),
        ];

        /** @var array<string, list<array<string, mixed>>> $categories */
        $categories = [
            'breakfasts' => [],
            'meals' => [],
            'sideSalads' => [],
            'desserts' => [],
            'soup' => [],
        ];

        $sortedMeals = $day->meals
            ->sortBy(fn (CustomerCraftPlanDayMeal $row): string => $row->slot->value.'-'.str_pad((string) $row->position, 3, '0', STR_PAD_LEFT))
            ->values();

        /** @var list<Meal> $mainMeals */
        $mainMeals = [];

        foreach ($sortedMeals as $row) {
            if ($row->meal === null || $row->slot !== CustomerCraftMealSlot::Main) {
                continue;
            }

            $mainMeals[] = $row->meal;
        }

        $adaptedMains = AdaptedMenuBuilder::adaptMainMealsForProfile($profile, $mainMeals, $adaptOptions);
        $adaptedMainById = collect($adaptedMains)->keyBy('id');

        foreach ($sortedMeals as $row) {
            if ($row->meal === null) {
                continue;
            }

            $categoryKey = self::SLOT_TO_CATEGORY[$row->slot->value] ?? null;

            if ($categoryKey === null) {
                continue;
            }

            $adapted = $row->slot === CustomerCraftMealSlot::Main
                ? $adaptedMainById->get((string) $row->meal->id)
                : AdaptedMenuBuilder::adaptMealForProfile($profile, $row->meal, $adaptOptions);

            if (! is_array($adapted)) {
                continue;
            }

            $categories[$categoryKey][] = $this->mealLibrary->applyAdaptedToMealRow(
                $this->mealLibrary->presentMealRowForUi($row->meal),
                $adapted,
                $row->meal,
            );
        }

        return $categories;
    }

    /**
     * @return array{soup_calories?: float, side_salad_calories?: float, dessert_calories?: float}
     */
    private function fixedPortionAdaptOptions(CustomerCraftPlanDay $day): array
    {
        $options = self::soupCaloriesAdaptOption($day);

        $sideMeal = $day->meals
            ->first(fn (CustomerCraftPlanDayMeal $row): bool => $row->slot === CustomerCraftMealSlot::SideSalad)?->meal;

        if ($sideMeal !== null) {
            $calories = (float) $sideMeal->nutritionForDisplay()['calories'];

            if ($calories > 0) {
                $options['side_salad_calories'] = $calories;
            }
        }

        $dessertMeal = $day->meals
            ->first(fn (CustomerCraftPlanDayMeal $row): bool => $row->slot === CustomerCraftMealSlot::Dessert)?->meal;

        if ($dessertMeal !== null) {
            $calories = (float) $dessertMeal->nutritionForDisplay()['calories'];

            if ($calories > 0) {
                $options['dessert_calories'] = $calories;
            }
        }

        return $options;
    }

    /**
     * @return array{soup_calories?: float}
     */
    private function soupCaloriesAdaptOption(CustomerCraftPlanDay $day): array
    {
        if (! $day->include_soup) {
            return [];
        }

        $soupMeal = $day->meals
            ->first(fn (CustomerCraftPlanDayMeal $row): bool => $row->slot === CustomerCraftMealSlot::Soup)?->meal;

        if ($soupMeal === null) {
            return [];
        }

        $calories = (float) $soupMeal->nutritionForDisplay()['calories'];

        if ($calories <= 0) {
            return [];
        }

        return ['soup_calories' => $calories];
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function presentBaselineCategoriesForDay(CustomerCraftPlanDay $day): array
    {
        /** @var array<string, list<array<string, mixed>>> $categories */
        $categories = [
            'breakfasts' => [],
            'meals' => [],
            'sideSalads' => [],
            'desserts' => [],
            'soup' => [],
        ];

        $sortedMeals = $day->meals
            ->sortBy(fn (CustomerCraftPlanDayMeal $row): string => $row->slot->value.'-'.str_pad((string) $row->position, 3, '0', STR_PAD_LEFT))
            ->values();

        foreach ($sortedMeals as $row) {
            if ($row->meal === null) {
                continue;
            }

            $categoryKey = self::SLOT_TO_CATEGORY[$row->slot->value] ?? null;

            if ($categoryKey === null) {
                continue;
            }

            $categories[$categoryKey][] = $this->mealLibrary->presentMealRowForUi($row->meal);
        }

        return $categories;
    }
}
