<?php

namespace App\Services\Nutrition;

use App\Enums\CustomerActivityLevel;
use App\Enums\CustomerGoal;
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

    private const KCAL_TO_KJ = 4.184;

    /**
     * @return array{
     *     bmr: int,
     *     tdee: int,
     *     daily_calories: int,
     *     daily_calories_min: int,
     *     daily_calories_max: int,
     *     daily_kj_min: int,
     *     daily_kj_max: int,
     *     protein_percentage: float,
     *     carb_percentage: float,
     *     fat_percentage: float,
     *     protein_grams: int,
     *     carb_grams: int,
     *     fat_grams: int,
     *     goal: string,
     *     weight_goal: string,
     *     diet_protocol: string,
     *     current_phase: ?string,
     *     plan_tier: int,
     *     plan_tiers: list<int>,
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
        $weightGoal = self::resolveWeightGoal($profile, $weightKg, $targetWeight);
        $goal = self::resolveGoal($weightGoal);
        $calorieRange = self::calculateGoalCalorieRange((int) round($tdee), $weightGoal);
        $dailyCalories = (int) UserPlanCalculator::snapToPlanTier((float) $calorieRange['midpoint']);

        $dietProtocol = DietProtocol::tryFromStored($profile->diet_protocol);
        $periodData = $profile->period_tracking_data ?? [];
        $currentPhase = PeriodTrackingPhaseService::resolveCurrentPhase($periodData);

        $percentages = self::macroPercentagesForDietProtocol($dietProtocol, $currentPhase);
        $grams = self::macroGrams($dailyCalories, $percentages);

        return [
            'bmr' => (int) round($bmr),
            'tdee' => (int) round($tdee),
            'daily_calories' => $dailyCalories,
            'daily_calories_min' => $calorieRange['min'],
            'daily_calories_max' => $calorieRange['max'],
            'daily_kj_min' => self::kcalToKj($calorieRange['min']),
            'daily_kj_max' => self::kcalToKj($calorieRange['max']),
            'goal' => $goal,
            'weight_goal' => $weightGoal,
            'diet_protocol' => $dietProtocol->value,
            'current_phase' => $currentPhase?->value,
            'protein_percentage' => $percentages['protein_percentage'],
            'carb_percentage' => $percentages['carb_percentage'],
            'fat_percentage' => $percentages['fat_percentage'],
            'protein_grams' => $grams['protein_grams'],
            'carb_grams' => $grams['carb_grams'],
            'fat_grams' => $grams['fat_grams'],
            'plan_tier' => $dailyCalories,
            'plan_tiers' => UserPlanCalculator::planTiers(),
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

    /**
     * @return array{min: int, max: int, midpoint: int}
     */
    public static function calculateGoalCalorieRange(int $tdee, string $weightGoal): array
    {
        $min = match ($weightGoal) {
            'lose' => $tdee - 750,
            'gain' => $tdee + 300,
            default => $tdee,
        };

        $max = match ($weightGoal) {
            'lose' => $tdee - 500,
            'gain' => $tdee + 500,
            default => $tdee + 100,
        };

        $min = max(self::MIN_DAILY_CALORIES, (int) round($min));
        $max = max($min, (int) round($max));
        $midpoint = (int) round(($min + $max) / 2);

        return [
            'min' => $min,
            'max' => $max,
            'midpoint' => $midpoint,
        ];
    }

    public static function kcalToKj(int $kcal): int
    {
        return (int) round($kcal * self::KCAL_TO_KJ);
    }

    private static function resolveWeightGoal(CustomerProfile $profile, float $weightKg, float $targetWeightKg): string
    {
        $goal = $profile->goal;

        if ($goal instanceof CustomerGoal) {
            return match ($goal) {
                CustomerGoal::LoseWeight => 'lose',
                CustomerGoal::GainMuscle => 'gain',
                default => 'maintain',
            };
        }

        if ($targetWeightKg < $weightKg - 0.5) {
            return 'lose';
        }

        if ($targetWeightKg > $weightKg + 0.5) {
            return 'gain';
        }

        return 'maintain';
    }

    private static function resolveGoal(string $weightGoal): string
    {
        return match ($weightGoal) {
            'lose' => CustomerGoal::LoseWeight->value,
            'gain' => CustomerGoal::GainMuscle->value,
            default => CustomerGoal::Maintain->value,
        };
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
                'protein_percentage' => 40.0,
                'carb_percentage' => 40.0,
                'fat_percentage' => 20.0,
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
            'protein_percentage' => 40.0,
            'carb_percentage' => 40.0,
            'fat_percentage' => 20.0,
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
