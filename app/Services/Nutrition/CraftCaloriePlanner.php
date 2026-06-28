<?php

namespace App\Services\Nutrition;

use InvalidArgumentException;

/**
 * Maps each consultation craft to a day calorie budget and per-slot scaling targets.
 */
final class CraftCaloriePlanner
{
    public const CRAFT_FULL = 'full';

    public const CRAFT_DAY = 'day';

    public const CRAFT_AFTERNOON = 'afternoon';

    public const CRAFT_INTERMITTENT = 'intermittent';

    public const CRAFT_BUSINESS = 'business';

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [
            self::CRAFT_FULL,
            self::CRAFT_DAY,
            self::CRAFT_AFTERNOON,
            self::CRAFT_INTERMITTENT,
            self::CRAFT_BUSINESS,
        ];
    }

    /**
     * @param  array<string, mixed>  $basePlan
     * @return array<string, mixed>
     */
    public static function applyCraftToPlan(array $basePlan, string $craftKey): array
    {
        if (! in_array($craftKey, self::keys(), true)) {
            throw new InvalidArgumentException("Unknown craft key [{$craftKey}].");
        }

        $tier = (float) ($basePlan['plan_tier'] ?? 0);
        $breakfast = (float) ($basePlan['scalable_slot_targets']['breakfast']['calories'] ?? 0);
        $mainEach = (float) ($basePlan['scalable_slot_targets']['main_each']['calories'] ?? 0);
        $fixedChoiceTotal = UserPlanCalculator::coreFixedPortionCaloriesTotal();
        $businessConfig = UserPlanCalculator::businessCraftConfig();

        $macroPct = self::resolveMacroPercentages($basePlan);

        $scalableSlotTargets = $basePlan['scalable_slot_targets'];

        $craftDayCalories = match ($craftKey) {
            self::CRAFT_FULL => round($tier, 2),
            self::CRAFT_AFTERNOON => round($tier - $breakfast, 2),
            self::CRAFT_DAY => round($tier - $mainEach, 2),
            self::CRAFT_INTERMITTENT => round($tier - $breakfast - $mainEach, 2),
            self::CRAFT_BUSINESS => round(
                $businessConfig['main_target'] + $businessConfig['side_calories'],
                2,
            ),
            default => round($tier, 2),
        };

        if ($craftKey === self::CRAFT_INTERMITTENT) {
            $mainTarget = max(0.0, round($craftDayCalories - $fixedChoiceTotal, 2));
            $scalableSlotTargets = [
                'breakfast' => self::slotTarget(0.0, $macroPct),
                'main_each' => self::slotTarget($mainTarget, $macroPct),
            ];
        }

        if ($craftKey === self::CRAFT_BUSINESS) {
            $scalableSlotTargets = [
                'breakfast' => self::slotTarget(0.0, $macroPct),
                'main_each' => self::slotTarget($businessConfig['main_target'], $macroPct),
            ];
        }

        if ($craftKey === self::CRAFT_AFTERNOON) {
            $scalableSlotTargets = [
                'breakfast' => self::slotTarget(0.0, $macroPct),
                'main_each' => $basePlan['scalable_slot_targets']['main_each'],
            ];
        }

        return array_merge($basePlan, [
            'craft_key' => $craftKey,
            'craft_day_calories' => $craftDayCalories,
            'craft_soup_counts_as_add_on' => false,
            'business_main_target' => $craftKey === self::CRAFT_BUSINESS
                ? $businessConfig['main_target']
                : null,
            'scalable_slot_targets' => $scalableSlotTargets,
            'craft' => [
                'key' => $craftKey,
                'day_calories' => $craftDayCalories,
                'soup_counts_as_add_on' => false,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $basePlan
     * @return array{protein: float, carb: float, fat: float}
     */
    private static function resolveMacroPercentages(array $basePlan): array
    {
        return [
            'protein' => (float) ($basePlan['protein_percentage'] ?? 30),
            'carb' => (float) ($basePlan['carb_percentage'] ?? 40),
            'fat' => (float) ($basePlan['fat_percentage'] ?? 30),
        ];
    }

    /**
     * @param  array{protein: float, carb: float, fat: float}  $macroPct
     * @return array{calories: float, macros: array{protein_g: float, carbs_g: float, fat_g: float}}
     */
    private static function slotTarget(float $calories, array $macroPct): array
    {
        return [
            'calories' => round($calories, 2),
            'macros' => UserPlanCalculator::macroGramsFromCaloriesAndPercentages(
                $calories,
                $macroPct['protein'],
                $macroPct['carb'],
                $macroPct['fat'],
            ),
        ];
    }
}
