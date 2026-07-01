<?php

namespace App\Services\Nutrition;

use App\Models\CustomerProfile;
use App\Models\Meal;

/**
 * Closes day-level macro gaps by boosting scalable slots (breakfast + mains) after fixed picks are set.
 */
class DayMacroReconciliation
{
    /**
     * @param  array{
     *     breakfasts: list<array<string, mixed>>,
     *     meals: list<array<string, mixed>>,
     *     sideSalads: list<array<string, mixed>>,
     *     desserts: list<array<string, mixed>>,
     *     soup: list<array<string, mixed>>
     * }  $dayMenu
     * @param  list<Meal>  $mainMeals
     * @param  array<string, mixed>  $options
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
    public static function reconcile(
        CustomerProfile $profile,
        array $dayMenu,
        array $mainMeals,
        array $options = [],
    ): array {
        $craftKey = (string) ($options['craft_key'] ?? '');

        if (! in_array($craftKey, [CraftCaloriePlanner::CRAFT_FULL, CraftCaloriePlanner::CRAFT_DAY], true)) {
            return ['dayMenu' => $dayMenu, 'warnings' => []];
        }

        $plan = UserPlanCalculator::calculateUserPlan($profile, $options);
        $plan = CraftCaloriePlanner::applyCraftToPlan($plan, $craftKey);
        $plan = AdaptedMenuBuilder::planWithBreakfastFloorRebalanceForProfile($profile, $plan, $options);

        $targets = is_array($plan['daily_macros'] ?? null) ? $plan['daily_macros'] : [];
        $tolerance = UserPlanCalculator::dayMacroTolerance();
        $warnings = [];

        $dayMenuForTotals = self::dayMenuForMacroTotals($dayMenu, $options);
        $actual = self::sumDayMacros($dayMenuForTotals);
        $targetProtein = (float) ($targets['protein_g'] ?? 0);
        $proteinDeficit = round($targetProtein - $actual['protein_g'], 2);

        if ($proteinDeficit > $tolerance['protein_g']) {
            $primaryAdaptedMains = self::adaptedMainsForMeals($dayMenu['meals'], $mainMeals);

            if ($primaryAdaptedMains !== []) {
                $balancedPrimary = AdaptedMenuBuilder::balanceMainMealProteinForDayDeficit(
                    $primaryAdaptedMains,
                    $plan,
                    $mainMeals,
                    $proteinDeficit,
                    self::fixedPortionCalories($dayMenu),
                    self::scalableNonMainCalories($dayMenu),
                );

                $dayMenu['meals'] = self::mergeAdaptedMainsById($dayMenu['meals'], $balancedPrimary);
            }

            $afterProtein = self::sumDayMacros(self::dayMenuForMacroTotals($dayMenu, $options));
            $remainingDeficit = round($targetProtein - $afterProtein['protein_g'], 2);

            if ($remainingDeficit > $tolerance['protein_g']) {
                $warnings[] = sprintf(
                    'Day protein is %.0fg below target (%.0fg selected vs %.0fg target).',
                    $remainingDeficit,
                    $afterProtein['protein_g'],
                    $targetProtein,
                );
            }
        }

        $dayMenu = self::reconcileCarbCalorieSurplus($dayMenu, $mainMeals, $plan, $targets, $tolerance, $options);
        $dayMenu = self::reconcileCarbCalorieDeficit($dayMenu, $mainMeals, $plan, $targets, $tolerance, $options);

        return ['dayMenu' => $dayMenu, 'warnings' => $warnings];
    }

    /**
     * @param  array{
     *     breakfasts: list<array<string, mixed>>,
     *     meals: list<array<string, mixed>>,
     *     sideSalads: list<array<string, mixed>>,
     *     desserts: list<array<string, mixed>>,
     *     soup: list<array<string, mixed>>
     * }  $dayMenu
     * @param  list<Meal>  $mainMeals
     * @param  array<string, mixed>  $plan
     * @param  array<string, float>  $targets
     * @param  array<string, float>  $tolerance
     * @param  array<string, mixed>  $options
     * @return array{
     *     breakfasts: list<array<string, mixed>>,
     *     meals: list<array<string, mixed>>,
     *     sideSalads: list<array<string, mixed>>,
     *     desserts: list<array<string, mixed>>,
     *     soup: list<array<string, mixed>>
     * }
     */
    private static function reconcileCarbCalorieDeficit(
        array $dayMenu,
        array $mainMeals,
        array $plan,
        array $targets,
        array $tolerance,
        array $options,
    ): array {
        if ($mainMeals === []) {
            return $dayMenu;
        }

        $dayMenuForTotals = self::dayMenuForMacroTotals($dayMenu, $options);
        $actual = self::sumDayMacros($dayMenuForTotals);
        $dayTargetCalories = (float) ($plan['craft_day_calories'] ?? $plan['plan_tier'] ?? 0);
        $dayCalorieTolerance = UserPlanCalculator::dayCalorieTolerance();
        $targetProtein = (float) ($targets['protein_g'] ?? 0);
        $targetCarbs = (float) ($targets['carbs_g'] ?? 0);
        $targetFat = (float) ($targets['fat_g'] ?? 0);
        $calorieDeficit = round($dayTargetCalories - $actual['calories'], 2);
        $carbDeficit = round($targetCarbs - $actual['carbs_g'], 2);
        $proteinDeficit = round($targetProtein - $actual['protein_g'], 2);
        $fatGap = abs(round($targetFat - $actual['fat_g'], 2));

        if (
            $calorieDeficit <= $dayCalorieTolerance
            || $proteinDeficit > $tolerance['protein_g']
            || $fatGap > $tolerance['fat_g']
            || $carbDeficit <= $tolerance['carbs_g']
        ) {
            return $dayMenu;
        }

        $primaryAdaptedMains = self::adaptedMainsForMeals($dayMenu['meals'], $mainMeals);

        if ($primaryAdaptedMains === []) {
            return $dayMenu;
        }

        $balancedPrimary = AdaptedMenuBuilder::balanceMainMealCarbsForDayDeficit(
            $primaryAdaptedMains,
            $plan,
            $mainMeals,
            $carbDeficit,
            $calorieDeficit,
        );

        $dayMenu['meals'] = self::mergeAdaptedMainsById($dayMenu['meals'], $balancedPrimary);

        return $dayMenu;
    }

    /**
     * @param  array{
     *     breakfasts: list<array<string, mixed>>,
     *     meals: list<array<string, mixed>>,
     *     sideSalads: list<array<string, mixed>>,
     *     desserts: list<array<string, mixed>>,
     *     soup: list<array<string, mixed>>
     * }  $dayMenu
     * @param  list<Meal>  $mainMeals
     * @param  array<string, mixed>  $plan
     * @param  array<string, float>  $targets
     * @param  array<string, float>  $tolerance
     * @param  array<string, mixed>  $options
     * @return array{
     *     breakfasts: list<array<string, mixed>>,
     *     meals: list<array<string, mixed>>,
     *     sideSalads: list<array<string, mixed>>,
     *     desserts: list<array<string, mixed>>,
     *     soup: list<array<string, mixed>>
     * }
     */
    private static function reconcileCarbCalorieSurplus(
        array $dayMenu,
        array $mainMeals,
        array $plan,
        array $targets,
        array $tolerance,
        array $options,
    ): array {
        if ($mainMeals === []) {
            return $dayMenu;
        }

        $dayMenuForTotals = self::dayMenuForMacroTotals($dayMenu, $options);
        $actual = self::sumDayMacros($dayMenuForTotals);
        $dayTargetCalories = (float) ($plan['craft_day_calories'] ?? $plan['plan_tier'] ?? 0);
        $dayCalorieTolerance = UserPlanCalculator::dayCalorieTolerance();
        $targetProtein = (float) ($targets['protein_g'] ?? 0);
        $targetCarbs = (float) ($targets['carbs_g'] ?? 0);
        $targetFat = (float) ($targets['fat_g'] ?? 0);
        $calorieSurplus = round($actual['calories'] - $dayTargetCalories, 2);
        $carbSurplus = round($actual['carbs_g'] - $targetCarbs, 2);
        $fatSurplus = round($actual['fat_g'] - $targetFat, 2);
        $proteinDeficit = round($targetProtein - $actual['protein_g'], 2);

        if (
            $calorieSurplus <= $dayCalorieTolerance
            || $proteinDeficit > $tolerance['protein_g']
            || ($carbSurplus <= $tolerance['carbs_g'] && $fatSurplus <= $tolerance['fat_g'])
        ) {
            return $dayMenu;
        }

        $primaryAdaptedMains = self::adaptedMainsForMeals($dayMenu['meals'], $mainMeals);

        if ($primaryAdaptedMains === []) {
            return $dayMenu;
        }

        $trimmedPrimary = AdaptedMenuBuilder::trimMainMealsForDaySurplus(
            $primaryAdaptedMains,
            $plan,
            $mainMeals,
            max(0.0, $carbSurplus),
            max(0.0, $fatSurplus),
            $calorieSurplus,
        );

        $dayMenu['meals'] = self::mergeAdaptedMainsById($dayMenu['meals'], $trimmedPrimary);

        return $dayMenu;
    }

    /**
     * @param  array{
     *     breakfasts: list<array<string, mixed>>,
     *     meals: list<array<string, mixed>>,
     *     sideSalads: list<array<string, mixed>>,
     *     desserts: list<array<string, mixed>>,
     *     soup: list<array<string, mixed>>
     * }  $dayMenu
     * @return array{calories: float, protein_g: float, carbs_g: float, fat_g: float}
     */
    public static function sumDayMacros(array $dayMenu): array
    {
        $totals = ['calories' => 0.0, 'protein_g' => 0.0, 'carbs_g' => 0.0, 'fat_g' => 0.0];

        foreach (['breakfasts', 'meals', 'sideSalads', 'desserts', 'soup'] as $bucket) {
            foreach ($dayMenu[$bucket] ?? [] as $meal) {
                if (! is_array($meal)) {
                    continue;
                }

                $macros = is_array($meal['macros'] ?? null) ? $meal['macros'] : [];
                $adapted = is_array($meal['adapted_nutrition'] ?? null) ? $meal['adapted_nutrition'] : [];
                $totals['calories'] += (float) ($meal['calories'] ?? $adapted['calories'] ?? 0);
                $totals['protein_g'] += (float) ($macros['protein_g'] ?? $adapted['protein'] ?? 0);
                $totals['carbs_g'] += (float) ($macros['carbs_g'] ?? $adapted['carbs'] ?? 0);
                $totals['fat_g'] += (float) ($macros['fat_g'] ?? $adapted['fat'] ?? 0);
            }
        }

        return [
            'calories' => round($totals['calories'], 2),
            'protein_g' => round($totals['protein_g'], 2),
            'carbs_g' => round($totals['carbs_g'], 2),
            'fat_g' => round($totals['fat_g'], 2),
        ];
    }

    /**
     * @param  array{
     *     breakfasts: list<array<string, mixed>>,
     *     meals: list<array<string, mixed>>,
     *     sideSalads: list<array<string, mixed>>,
     *     desserts: list<array<string, mixed>>,
     *     soup: list<array<string, mixed>>
     * }  $dayMenu
     */
    private static function fixedPortionCalories(array $dayMenu): float
    {
        $total = 0.0;

        foreach (['sideSalads', 'desserts', 'soup'] as $bucket) {
            foreach ($dayMenu[$bucket] ?? [] as $meal) {
                if (! is_array($meal)) {
                    continue;
                }

                $adapted = is_array($meal['adapted_nutrition'] ?? null) ? $meal['adapted_nutrition'] : [];
                $total += (float) ($meal['calories'] ?? $adapted['calories'] ?? 0);
            }
        }

        return round($total, 2);
    }

    /**
     * @param  array{
     *     breakfasts: list<array<string, mixed>>,
     *     meals: list<array<string, mixed>>,
     *     sideSalads: list<array<string, mixed>>,
     *     desserts: list<array<string, mixed>>,
     *     soup: list<array<string, mixed>>
     * }  $dayMenu
     * @return array{calories: float, protein_g: float, carbs_g: float, fat_g: float}
     */
    private static function scalableNonMainCalories(array $dayMenu): array
    {
        $totals = ['calories' => 0.0, 'protein_g' => 0.0, 'carbs_g' => 0.0, 'fat_g' => 0.0];

        foreach ($dayMenu['breakfasts'] ?? [] as $meal) {
            if (! is_array($meal)) {
                continue;
            }

            $macros = is_array($meal['macros'] ?? null) ? $meal['macros'] : [];
            $adapted = is_array($meal['adapted_nutrition'] ?? null) ? $meal['adapted_nutrition'] : [];
            $totals['calories'] += (float) ($meal['calories'] ?? $adapted['calories'] ?? 0);
            $totals['protein_g'] += (float) ($macros['protein_g'] ?? $adapted['protein'] ?? 0);
            $totals['carbs_g'] += (float) ($macros['carbs_g'] ?? $adapted['carbs'] ?? 0);
            $totals['fat_g'] += (float) ($macros['fat_g'] ?? $adapted['fat'] ?? 0);
        }

        return [
            'calories' => round($totals['calories'], 2),
            'protein_g' => round($totals['protein_g'], 2),
            'carbs_g' => round($totals['carbs_g'], 2),
            'fat_g' => round($totals['fat_g'], 2),
        ];
    }

    /**
     * @param  array{
     *     breakfasts: list<array<string, mixed>>,
     *     meals: list<array<string, mixed>>,
     *     sideSalads: list<array<string, mixed>>,
     *     desserts: list<array<string, mixed>>,
     *     soup: list<array<string, mixed>>
     * }  $dayMenu
     * @param  array<string, mixed>  $options
     * @return array{
     *     breakfasts: list<array<string, mixed>>,
     *     meals: list<array<string, mixed>>,
     *     sideSalads: list<array<string, mixed>>,
     *     desserts: list<array<string, mixed>>,
     *     soup: list<array<string, mixed>>
     * }
     */
    private static function dayMenuForMacroTotals(array $dayMenu, array $options): array
    {
        $selectedIds = $options['selected_main_meal_ids'] ?? null;
        $requiredMainCount = max(1, (int) config('customer_nutrition.scalable_slots.main', 2));

        if (! is_array($selectedIds) || count($selectedIds) < $requiredMainCount) {
            return $dayMenu;
        }

        $normalizedIds = array_values(array_unique(array_map(static fn (mixed $id): int => (int) $id, $selectedIds)));

        $dayMenu['meals'] = array_values(array_filter(
            $dayMenu['meals'] ?? [],
            static function (mixed $meal) use ($normalizedIds): bool {
                if (! is_array($meal)) {
                    return false;
                }

                return in_array((int) ($meal['id'] ?? 0), $normalizedIds, true);
            },
        ));

        return $dayMenu;
    }

    /**
     * @param  list<array<string, mixed>>  $adaptedMains
     * @param  list<Meal>  $meals
     * @return list<array<string, mixed>>
     */
    private static function adaptedMainsForMeals(array $adaptedMains, array $meals): array
    {
        $mealIds = array_map(static fn (Meal $meal): int => (int) $meal->id, $meals);
        $out = [];

        foreach ($adaptedMains as $adapted) {
            $id = (int) ($adapted['id'] ?? 0);

            if (in_array($id, $mealIds, true)) {
                $out[] = $adapted;
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $adaptedMains
     * @param  list<array<string, mixed>>  $balancedMains
     * @return list<array<string, mixed>>
     */
    private static function mergeAdaptedMainsById(array $adaptedMains, array $balancedMains): array
    {
        $balancedById = collect($balancedMains)->keyBy('id');

        return array_map(
            static fn (array $adapted): array => $balancedById->get($adapted['id'] ?? null) ?? $adapted,
            $adaptedMains,
        );
    }
}
