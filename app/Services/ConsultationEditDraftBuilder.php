<?php

namespace App\Services;

use App\Models\CustomerCraftPlan;
use App\Models\CustomerCraftPlanDay;
use App\Models\CustomerCraftPlanDayMeal;

final class ConsultationEditDraftBuilder
{
    /** @var array<string, string> */
    private const SLOT_TO_CATEGORY = [
        'breakfast' => 'breakfasts',
        'main' => 'meals',
        'side_salad' => 'sideSalads',
        'dessert' => 'desserts',
        'soup' => 'soup',
    ];

    /**
     * Lightweight payload for restoring consultation selections in the browser.
     *
     * @return array{
     *     craftKey: string,
     *     weekDuration: int,
     *     selectedWeekdays: list<int>,
     *     days: list<array{
     *         dayNumber: int,
     *         includeSoup: bool,
     *         categories: array{
     *             breakfasts: list<array{id: string}>,
     *             meals: list<array{id: string}>,
     *             sideSalads: list<array{id: string}>,
     *             desserts: list<array{id: string}>,
     *             soup: list<array{id: string}>
     *         }
     *     }>
     * }|null
     */
    public function buildFromPlan(CustomerCraftPlan $plan): ?array
    {
        $plan->loadMissing(['days.meals']);

        if ($plan->days->isEmpty()) {
            return null;
        }

        $categoryKeys = ['breakfasts', 'meals', 'sideSalads', 'desserts', 'soup'];
        $emptyCategories = array_fill_keys($categoryKeys, []);

        /** @var list<array{dayNumber: int, includeSoup: bool, categories: array<string, list<array{id: string}>>}> $days */
        $days = [];

        foreach ($plan->days->sortBy('day_of_week')->values() as $day) {
            if (! $day instanceof CustomerCraftPlanDay) {
                continue;
            }

            $categories = $emptyCategories;

            foreach ($day->meals->sortBy(fn (CustomerCraftPlanDayMeal $row): string => $row->slot->value.'-'.str_pad((string) $row->position, 3, '0', STR_PAD_LEFT))->values() as $row) {
                $categoryKey = self::SLOT_TO_CATEGORY[$row->slot->value] ?? null;
                if ($categoryKey === null || $row->meal_id === null) {
                    continue;
                }

                $categories[$categoryKey][] = ['id' => (string) $row->meal_id];
            }

            $days[] = [
                'dayNumber' => (int) $day->day_of_week,
                'includeSoup' => (bool) $day->include_soup,
                'categories' => $categories,
            ];
        }

        if ($days === []) {
            return null;
        }

        return [
            'craftKey' => $plan->craft_key,
            'weekDuration' => (int) $plan->week_duration,
            'selectedWeekdays' => array_values($plan->selected_weekdays ?? []),
            'days' => $days,
        ];
    }
}
