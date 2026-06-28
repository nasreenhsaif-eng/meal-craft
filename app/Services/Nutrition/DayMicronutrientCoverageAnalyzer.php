<?php

namespace App\Services\Nutrition;

use App\Models\CustomerProfile;
use App\Support\MealPlanSlotBasedDayNutrition;
use App\Support\NutrientDailyRdi;

final class DayMicronutrientCoverageAnalyzer
{
    /**
     * @param  list<array<string, mixed>>  $adaptedMeals
     * @return array<string, float>
     */
    public static function aggregateAdaptedNutrition(array $adaptedMeals): array
    {
        $totals = [];

        foreach (MealPlanSlotBasedDayNutrition::nutritionKeys() as $key) {
            $totals[$key] = 0.0;
        }

        foreach ($adaptedMeals as $meal) {
            $nutrition = is_array($meal['adapted_nutrition'] ?? null)
                ? $meal['adapted_nutrition']
                : [];

            foreach (MealPlanSlotBasedDayNutrition::nutritionKeys() as $key) {
                $totals[$key] += (float) ($nutrition[$key] ?? 0);
            }
        }

        foreach ($totals as $key => $value) {
            $totals[$key] = round($value, 2);
        }

        return $totals;
    }

    /**
     * @param  array<string, float>  $dayNutrition
     * @return list<array{
     *     label: string,
     *     key: string,
     *     total: float,
     *     rdi: float,
     *     percent: float,
     *     status: string,
     *     meets_target: bool,
     * }>
     */
    public static function analyzeDayNutrition(array $dayNutrition, int $planTier): array
    {
        $enforced = NutrientDailyRdi::tierEnforced($planTier);
        $rows = [];

        foreach (NutrientDailyRdi::NUTRITION_KEY_TO_LABEL as $key => $label) {
            $rdi = NutrientDailyRdi::rdiForLabel($label);

            if ($rdi === null) {
                continue;
            }

            $total = (float) ($dayNutrition[$key] ?? 0);
            $percent = NutrientDailyRdi::percentOfRdi($label, $total) ?? 0.0;
            $status = NutrientDailyRdi::nutrientStatus($label);

            $meetsTarget = match ($status) {
                'ceiling' => NutrientDailyRdi::meetsCeilingTarget($label, $percent),
                'best_effort' => true,
                default => ! $enforced || NutrientDailyRdi::meetsFloorTarget($label, $percent),
            };

            $rows[] = [
                'label' => $label,
                'key' => $key,
                'total' => $total,
                'rdi' => $rdi,
                'percent' => round($percent, 2),
                'status' => $status,
                'meets_target' => $meetsTarget,
            ];
        }

        return $rows;
    }

    /**
     * @return array{
     *     day_number: int,
     *     plan_tier: float,
     *     selected_fixed_slots: list<string>,
     *     enforced: bool,
     *     day_calories: float,
     *     nutrients: list<array<string, mixed>>,
     *     failing_floor: list<string>,
     *     failing_ceiling: list<string>,
     *     passes: bool,
     * }
     */
    public static function simulateFullCraftDay(
        CustomerProfile $profile,
        int $dayNumber,
        float $planTier,
        array $selectedFixedSlots,
    ): array {
        $simulation = ReferenceFullCraftDaySimulator::simulate(
            $profile,
            $dayNumber,
            $planTier,
            $selectedFixedSlots,
        );

        $nutrients = self::analyzeDayNutrition($simulation['day_nutrition'], (int) round($planTier));
        $enforced = NutrientDailyRdi::tierEnforced((int) round($planTier));

        $failingFloor = [];
        $failingCeiling = [];

        foreach ($nutrients as $row) {
            if ($row['status'] === 'floor' && ! $row['meets_target']) {
                $failingFloor[] = $row['label'];
            }

            if ($row['status'] === 'ceiling' && ! $row['meets_target']) {
                $failingCeiling[] = $row['label'];
            }
        }

        $passes = ! $enforced || ($failingFloor === [] && $failingCeiling === []);

        return [
            'day_number' => $simulation['day_number'],
            'plan_tier' => $simulation['plan_tier'],
            'selected_fixed_slots' => $simulation['selected_fixed_slots'],
            'enforced' => $enforced,
            'day_calories' => $simulation['day_calories'],
            'nutrients' => $nutrients,
            'failing_floor' => $failingFloor,
            'failing_ceiling' => $failingCeiling,
            'passes' => $passes,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function auditMatrix(CustomerProfile $profile): array
    {
        $results = [];

        foreach (NutrientDailyRdi::allAuditTiers() as $tier) {
            foreach (NutrientDailyRdi::fixedSlotCombinations() as $combination) {
                $slots = NutrientDailyRdi::parseFixedSlotCombination($combination);

                foreach (range(1, 7) as $dayNumber) {
                    $results[] = self::simulateFullCraftDay($profile, $dayNumber, (float) $tier, $slots);
                }
            }
        }

        return $results;
    }
}
