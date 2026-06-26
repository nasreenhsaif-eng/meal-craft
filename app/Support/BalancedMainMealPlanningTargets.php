<?php

namespace App\Support;

use App\Services\Nutrition\UserPlanCalculator;

/**
 * Menu-development planning targets for Balanced scalable main meals (per slot).
 */
final class BalancedMainMealPlanningTargets
{
    public const TARGET_CALORIES = 360.0;

    public const PROTEIN_PERCENTAGE = 40.0;

    public const CARB_PERCENTAGE = 30.0;

    public const FAT_PERCENTAGE = 30.0;

    /**
     * @return array{target_calories: float, target_protein: float, target_carbs: float, target_fat: float}
     */
    public static function forDesignBand(): array
    {
        $macros = UserPlanCalculator::macroGramsFromCaloriesAndPercentages(
            self::TARGET_CALORIES,
            self::PROTEIN_PERCENTAGE,
            self::CARB_PERCENTAGE,
            self::FAT_PERCENTAGE,
        );

        return [
            'target_calories' => self::TARGET_CALORIES,
            'target_protein' => $macros['protein_g'],
            'target_carbs' => $macros['carbs_g'],
            'target_fat' => $macros['fat_g'],
        ];
    }
}
