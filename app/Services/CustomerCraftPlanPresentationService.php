<?php

namespace App\Services;

use App\Http\Controllers\Admin\MealLibraryController;
use App\Models\CustomerCraftPlan;
use App\Models\CustomerCraftPlanDay;
use App\Models\CustomerCraftPlanDayMeal;

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
        $plan->loadMissing(['days.meals.meal.ingredients']);

        $categoryKeys = ['breakfasts', 'meals', 'sideSalads', 'desserts', 'soup'];
        $emptyCategories = array_fill_keys($categoryKeys, []);

        /** @var list<array{dayNumber: int, label: string, includeSoup: bool, categories: array<string, list<array<string, mixed>>>}> $presentedDays */
        $presentedDays = [];

        foreach ($plan->days->sortBy('day_of_week')->values() as $day) {
            if (! $day instanceof CustomerCraftPlanDay) {
                continue;
            }

            $categories = $emptyCategories;

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

            $dayNumber = (int) $day->day_of_week;

            $presentedDays[] = [
                'dayNumber' => $dayNumber,
                'label' => self::WEEKDAY_LABELS[$dayNumber - 1] ?? __('Day :number', ['number' => $dayNumber]),
                'includeSoup' => (bool) $day->include_soup,
                'categories' => $categories,
            ];
        }

        return [
            'craftKey' => $plan->craft_key,
            'craftTitle' => self::CRAFT_TITLES[$plan->craft_key] ?? ucfirst($plan->craft_key),
            'weekDuration' => (int) $plan->week_duration,
            'planTierCalories' => $planTierCalories,
            'submittedAt' => $plan->submitted_at?->toIso8601String(),
            'days' => $presentedDays,
        ];
    }
}
