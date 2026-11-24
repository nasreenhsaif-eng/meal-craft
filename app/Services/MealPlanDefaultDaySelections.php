<?php

namespace App\Services;

use App\Models\CustomerProfile;
use App\Models\MealPlan;
use App\Models\MealPlanDayMeal;
use App\Services\Nutrition\FullCraftDayMenuBuilder;
use Illuminate\Support\Collection;

/**
 * Admin-chosen default picks per day/category for a structured meal plan.
 * Customers still may swap; these ids drive is_recommended / initial seed.
 */
final class MealPlanDefaultDaySelections
{
    /** @var list<string> */
    public const CATEGORY_KEYS = ['breakfasts', 'meals', 'sideSalads', 'desserts', 'soup'];

    /**
     * @return array<int, array<string, list<int>>>
     */
    public static function forPlan(MealPlan $plan): array
    {
        $raw = $plan->default_day_selections;

        if (! is_array($raw) || $raw === []) {
            return [];
        }

        return self::normalizeSelectionsMap($raw);
    }

    public static function hasStoredDefaults(MealPlan $plan): bool
    {
        return self::forPlan($plan) !== [];
    }

    /**
     * @return array<string, list<int>>|null
     */
    public static function forDay(MealPlan $plan, int $dayNumber): ?array
    {
        $all = self::forPlan($plan);

        if ($all === [] || ! isset($all[$dayNumber])) {
            return null;
        }

        return $all[$dayNumber];
    }

    /**
     * @return list<int>
     */
    public static function mealIdsForCategory(MealPlan $plan, int $dayNumber, string $categoryKey): array
    {
        $day = self::forDay($plan, $dayNumber);

        if ($day === null) {
            return [];
        }

        return $day[$categoryKey] ?? [];
    }

    /**
     * null = no stored defaults for this day (caller should use protocol convention).
     */
    public static function isRecommendedMealId(
        MealPlan $plan,
        int $dayNumber,
        string $categoryKey,
        int $mealId,
    ): ?bool {
        $day = self::forDay($plan, $dayNumber);

        if ($day === null) {
            return null;
        }

        if ($mealId <= 0) {
            return false;
        }

        return in_array($mealId, $day[$categoryKey] ?? [], true);
    }

    /**
     * @param  array<int|string, mixed>  $selections
     */
    public static function store(MealPlan $plan, array $selections): void
    {
        $normalized = self::normalizeSelectionsMap($selections);

        $plan->forceFill([
            'default_day_selections' => $normalized === [] ? null : $normalized,
        ])->save();
    }

    /**
     * Write convention defaults (ND slots / omelette / first salad + dessert) for every plan day.
     *
     * @param  Collection<int, MealPlanDayMeal>|null  $dayMeals
     */
    public static function seedFromConvention(MealPlan $plan, CustomerProfile $profile, ?Collection $dayMeals = null): void
    {
        $plan->loadMissing([
            'dayMeals' => static function ($query): void {
                $query->where('is_option_b', false)
                    ->orderBy('day_number')
                    ->orderBy('slot_type')
                    ->orderBy('slot_index');
            },
            'dayMeals.meal',
        ]);

        $rows = $dayMeals ?? $plan->dayMeals;
        $byDay = $rows->groupBy(static fn (MealPlanDayMeal $row): int => (int) $row->day_number);
        $dayCount = max(1, $plan->structuredPlanningDayCount());

        /** @var array<int, array<string, list<int>>> $selections */
        $selections = [];

        for ($dayNumber = 1; $dayNumber <= $dayCount; $dayNumber++) {
            /** @var Collection<int, MealPlanDayMeal> $dayRows */
            $dayRows = $byDay->get($dayNumber, collect());
            $selections[$dayNumber] = FullCraftDayMenuBuilder::conventionDaySelectionForRows($profile, $dayRows);
        }

        self::store($plan, $selections);
    }

    /**
     * @param  array<int|string, mixed>  $raw
     * @return array<int, array<string, list<int>>>
     */
    public static function normalizeSelectionsMap(array $raw): array
    {
        /** @var array<int, array<string, list<int>>> $out */
        $out = [];

        foreach ($raw as $dayNumber => $categories) {
            if (! is_numeric($dayNumber) || ! is_array($categories)) {
                continue;
            }

            $normalizedDay = (int) $dayNumber;

            if ($normalizedDay < 1) {
                continue;
            }

            /** @var array<string, list<int>> $day */
            $day = [];

            foreach (self::CATEGORY_KEYS as $categoryKey) {
                $day[$categoryKey] = self::normalizeMealIds($categories[$categoryKey] ?? []);
            }

            $out[$normalizedDay] = $day;
        }

        ksort($out);

        return $out;
    }

    /**
     * @return list<int>
     */
    public static function normalizeMealIds(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $ids = [];

        foreach ($raw as $id) {
            $intId = (int) $id;

            if ($intId > 0) {
                $ids[] = $intId;
            }
        }

        return array_values(array_unique($ids));
    }
}
