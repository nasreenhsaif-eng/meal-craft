<?php

namespace App\Services\Nutrition;

use App\Support\CraftLibraryTierMap;
use InvalidArgumentException;

/**
 * Maps each consultation craft to a day calorie budget and Meal Tiers Library plate tabs.
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

        $incomingTier = (float) ($basePlan['plan_tier'] ?? 0);
        $craftTotal = CraftLibraryTierMap::snapToCraftTotal($incomingTier, $craftKey);
        $row = CraftLibraryTierMap::row($craftKey, $craftTotal);

        $macroPct = self::resolveMacroPercentages($basePlan);
        $breakfastCalories = (float) $row['breakfast'];
        $mainEachCalories = (float) $row['main_each'];

        $scalableSlotTargets = [
            'breakfast' => self::slotTarget($breakfastCalories, $macroPct),
            'main_each' => self::mainSlotTarget($mainEachCalories),
        ];

        return array_merge($basePlan, [
            'craft_key' => $craftKey,
            'plan_tier' => (float) $craftTotal,
            'craft_day_calories' => (float) $craftTotal,
            'craft_soup_counts_as_add_on' => false,
            'business_main_target' => $craftKey === self::CRAFT_BUSINESS
                ? $mainEachCalories
                : null,
            'scalable_slot_targets' => $scalableSlotTargets,
            'library_slots' => $row,
            'craft' => [
                'key' => $craftKey,
                'day_calories' => (float) $craftTotal,
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

    /**
     * @return array{calories: float, macros: array{protein_g: float, carbs_g: float, fat_g: float}}
     */
    private static function mainSlotTarget(float $calories): array
    {
        return [
            'calories' => round($calories, 2),
            'macros' => UserPlanCalculator::mainEachMacroGrams($calories),
        ];
    }
}
