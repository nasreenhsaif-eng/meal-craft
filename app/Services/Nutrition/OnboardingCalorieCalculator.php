<?php

namespace App\Services\Nutrition;

use App\Enums\CustomerActivityLevel;
use App\Enums\CustomerSex;

/**
 * Estimates daily calorie needs from anthropometrics (Mifflin–St Jeor BMR × activity).
 */
final class OnboardingCalorieCalculator
{
    public static function estimateDailyCalories(
        float $weightKg,
        float $heightCm,
        int $age,
        CustomerSex $sex,
        CustomerActivityLevel $activityLevel,
    ): int {
        $bmr = self::mifflinStJeorBmr($weightKg, $heightCm, $age, $sex);
        $multiplier = (float) (config('customer_nutrition.activity_multipliers')[$activityLevel->value] ?? 1.2);
        $tdee = $bmr * $multiplier;

        return (int) round(max(1200.0, $tdee));
    }

    public static function mifflinStJeorBmr(
        float $weightKg,
        float $heightCm,
        int $age,
        CustomerSex $sex,
    ): float {
        $base = (10.0 * $weightKg) + (6.25 * $heightCm) - (5.0 * $age);

        return match ($sex) {
            CustomerSex::Male => $base + 5.0,
            CustomerSex::Female => $base - 161.0,
        };
    }
}
