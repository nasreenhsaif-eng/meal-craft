<?php

namespace App\Services\Nutrition;

use App\Enums\CustomerActivityLevel;
use App\Enums\CustomerSex;
use App\Enums\CyclePhase;
use App\Enums\DietProtocol;
use App\Models\CustomerProfile;
use Illuminate\Support\Carbon;

/**
 * Computes BMR, TDEE, goal-adjusted calories, and diet-protocol macro targets for onboarding.
 */
final class OnboardingDailyTargetsCalculator
{
    private const MIN_DAILY_CALORIES = 1200;

    private const DEFICIT_CALORIES = 500;

    private const SURPLUS_CALORIES = 300;

    /**
     * @return array{
     *     bmr: int,
     *     tdee: int,
     *     daily_calories: int,
     *     protein_percentage: float,
     *     carb_percentage: float,
     *     fat_percentage: float,
     *     protein_grams: int,
     *     carb_grams: int,
     *     fat_grams: int,
     *     goal: string,
     *     diet_protocol: string,
     *     current_phase: ?string,
     * }
     */
    public static function calculate(CustomerProfile $profile): array
    {
        $weightKg = (float) $profile->weight_kg;
        $heightCm = (float) $profile->height_cm;
        $age = self::resolveAge($profile);
        $sex = $profile->sex ?? CustomerSex::Female;
        $activity = CustomerActivityLevel::tryFromStored($profile->activity_level?->value ?? null);

        $bmr = OnboardingCalorieCalculator::mifflinStJeorBmr($weightKg, $heightCm, $age, $sex);
        $multiplier = self::activityMultiplier($activity);
        $tdee = $bmr * $multiplier;

        $targetWeight = (float) ($profile->target_weight_kg ?? $weightKg);
        $dailyCalories = self::applyGoalAdjustment($tdee, $weightKg, $targetWeight);
        $goal = self::resolveGoal($weightKg, $targetWeight);

        $dietProtocol = DietProtocol::tryFromStored($profile->diet_protocol);
        $periodData = $profile->period_tracking_data ?? [];
        $currentPhase = PeriodTrackingPhaseService::resolveCurrentPhase($periodData);

        $percentages = self::macroPercentagesForDietProtocol($dietProtocol, $currentPhase);
        $grams = self::macroGrams($dailyCalories, $percentages);

        return [
            'bmr' => (int) round($bmr),
            'tdee' => (int) round($tdee),
            'daily_calories' => $dailyCalories,
            'goal' => $goal,
            'diet_protocol' => $dietProtocol->value,
            'current_phase' => $currentPhase?->value,
            'protein_percentage' => $percentages['protein_percentage'],
            'carb_percentage' => $percentages['carb_percentage'],
            'fat_percentage' => $percentages['fat_percentage'],
            'protein_grams' => $grams['protein_grams'],
            'carb_grams' => $grams['carb_grams'],
            'fat_grams' => $grams['fat_grams'],
        ];
    }

    public static function activityMultiplier(CustomerActivityLevel $activity): float
    {
        $key = $activity->multiplierKey();
        $multipliers = config('customer_nutrition.onboarding_activity_multipliers', []);

        return (float) ($multipliers[$key] ?? 1.375);
    }

    private static function resolveAge(CustomerProfile $profile): int
    {
        if ($profile->age !== null) {
            return (int) $profile->age;
        }

        $birthdate = $profile->birthdate ?? $profile->date_of_birth;

        if ($birthdate !== null) {
            return (int) Carbon::parse($birthdate)->diffInYears(now());
        }

        return 0;
    }

    private static function applyGoalAdjustment(float $tdee, float $weightKg, float $targetWeightKg): int
    {
        if ($targetWeightKg < $weightKg - 0.5) {
            return (int) round(max(self::MIN_DAILY_CALORIES, $tdee - self::DEFICIT_CALORIES));
        }

        if ($targetWeightKg > $weightKg + 0.5) {
            return (int) round(max(self::MIN_DAILY_CALORIES, $tdee + self::SURPLUS_CALORIES));
        }

        return (int) round(max(self::MIN_DAILY_CALORIES, $tdee));
    }

    private static function resolveGoal(float $weightKg, float $targetWeightKg): string
    {
        if ($targetWeightKg < $weightKg - 0.5) {
            return 'lose_weight';
        }

        if ($targetWeightKg > $weightKg + 0.5) {
            return 'gain_muscle';
        }

        return 'maintain';
    }

    /**
     * @return array{protein_percentage: float, carb_percentage: float, fat_percentage: float}
     */
    public static function macroPercentagesForDietProtocol(
        DietProtocol|string $dietProtocol,
        ?CyclePhase $currentPhase = null,
    ): array {
        $protocol = $dietProtocol instanceof DietProtocol
            ? $dietProtocol
            : DietProtocol::tryFromStored(is_string($dietProtocol) ? $dietProtocol : null);

        if ($protocol === DietProtocol::CycleSync) {
            return self::cycleSyncMacroPercentages($currentPhase);
        }

        $presets = config('customer_nutrition.diet_protocol_macro_presets', []);

        if (! isset($presets[$protocol->value])) {
            return $presets['balanced'] ?? [
                'protein_percentage' => 30.0,
                'carb_percentage' => 40.0,
                'fat_percentage' => 30.0,
            ];
        }

        return $presets[$protocol->value];
    }

    /**
     * @return array{protein_percentage: float, carb_percentage: float, fat_percentage: float}
     */
    private static function cycleSyncMacroPercentages(?CyclePhase $currentPhase): array
    {
        $ketobiotic = config('customer_nutrition.diet_protocol_macro_presets.ketobiotic', [
            'protein_percentage' => 20.0,
            'carb_percentage' => 10.0,
            'fat_percentage' => 70.0,
        ]);

        $balanced = config('customer_nutrition.diet_protocol_macro_presets.balanced', [
            'protein_percentage' => 30.0,
            'carb_percentage' => 40.0,
            'fat_percentage' => 30.0,
        ]);

        if ($currentPhase === CyclePhase::Menstrual || $currentPhase === CyclePhase::Follicular) {
            return $ketobiotic;
        }

        return $balanced;
    }

    /**
     * @param  array{protein_percentage: float, carb_percentage: float, fat_percentage: float}  $percentages
     * @return array{protein_grams: int, carb_grams: int, fat_grams: int}
     */
    public static function macroGrams(int $calories, array $percentages): array
    {
        $safeCalories = max(0, $calories);

        return [
            'protein_grams' => (int) round(($safeCalories * ($percentages['protein_percentage'] / 100)) / 4),
            'carb_grams' => (int) round(($safeCalories * ($percentages['carb_percentage'] / 100)) / 4),
            'fat_grams' => (int) round(($safeCalories * ($percentages['fat_percentage'] / 100)) / 9),
        ];
    }
}
