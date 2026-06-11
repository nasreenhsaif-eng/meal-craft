<?php

use App\Enums\CustomerActivityLevel;
use App\Enums\CustomerGoal;
use App\Enums\CustomerSex;
use App\Enums\CyclePhase;
use App\Enums\DietProtocol;
use App\Models\CustomerProfile;
use App\Services\Nutrition\OnboardingDailyTargetsCalculator;
use App\Services\Nutrition\PeriodTrackingPhaseService;

it('applies a deficit calorie range when goal is lose weight', function () {
    $profile = new CustomerProfile([
        'sex' => CustomerSex::Female,
        'age' => 32,
        'weight_kg' => 72,
        'target_weight_kg' => 65,
        'height_cm' => 168,
        'activity_level' => CustomerActivityLevel::LightlyActive,
        'diet_protocol' => DietProtocol::Balanced->value,
        'goal' => CustomerGoal::LoseWeight,
    ]);

    $targets = OnboardingDailyTargetsCalculator::calculate($profile);

    expect($targets['weight_goal'])->toBe('lose')
        ->and($targets['daily_calories_max'])->toBeLessThan($targets['tdee'])
        ->and($targets['tdee'] - $targets['daily_calories_min'])->toBeGreaterThanOrEqual(500)
        ->and($targets['daily_kj_min'])->toBe((int) round($targets['daily_calories_min'] * 4.184))
        ->and($targets['daily_kj_max'])->toBe((int) round($targets['daily_calories_max'] * 4.184));
});

it('applies a maintain calorie range from TDEE to TDEE + 100', function () {
    $profile = new CustomerProfile([
        'sex' => CustomerSex::Female,
        'age' => 32,
        'weight_kg' => 68,
        'target_weight_kg' => 68,
        'height_cm' => 165,
        'activity_level' => CustomerActivityLevel::LightlyActive,
        'diet_protocol' => DietProtocol::Balanced->value,
        'goal' => CustomerGoal::Maintain,
    ]);

    $targets = OnboardingDailyTargetsCalculator::calculate($profile);

    expect($targets['weight_goal'])->toBe('maintain')
        ->and($targets['daily_calories_min'])->toBe($targets['tdee'])
        ->and($targets['daily_calories_max'])->toBe($targets['tdee'] + 100);
});

it('applies a surplus calorie range when goal is gain muscle', function () {
    $profile = new CustomerProfile([
        'sex' => CustomerSex::Male,
        'age' => 35,
        'weight_kg' => 75,
        'target_weight_kg' => 80,
        'height_cm' => 178,
        'activity_level' => CustomerActivityLevel::ModeratelyActive,
        'diet_protocol' => DietProtocol::Balanced->value,
        'goal' => CustomerGoal::GainMuscle,
    ]);

    $targets = OnboardingDailyTargetsCalculator::calculate($profile);

    expect($targets['weight_goal'])->toBe('gain')
        ->and($targets['daily_calories_min'])->toBeGreaterThan($targets['tdee'])
        ->and($targets['daily_calories_max'] - $targets['tdee'])->toBeGreaterThanOrEqual(300);
});

it('uses ketobiotic macro percentages for ketobiotic diet protocol', function () {
    $percentages = OnboardingDailyTargetsCalculator::macroPercentagesForDietProtocol(DietProtocol::Ketobiotic);

    expect($percentages['carb_percentage'])->toBe(10.0)
        ->and($percentages['fat_percentage'])->toBe(70.0);
});

it('uses balanced macro split by default', function () {
    $percentages = OnboardingDailyTargetsCalculator::macroPercentagesForDietProtocol(DietProtocol::Balanced);

    expect($percentages['protein_percentage'])->toBe(40.0)
        ->and($percentages['carb_percentage'])->toBe(40.0)
        ->and($percentages['fat_percentage'])->toBe(20.0);
});

it('uses ketobiotic macros for cycle sync during menstrual phase', function () {
    $percentages = OnboardingDailyTargetsCalculator::macroPercentagesForDietProtocol(
        DietProtocol::CycleSync,
        CyclePhase::Menstrual,
    );

    expect($percentages['carb_percentage'])->toBe(10.0)
        ->and($percentages['fat_percentage'])->toBe(70.0);
});

it('uses balanced macros for cycle sync during luteal phase', function () {
    $percentages = OnboardingDailyTargetsCalculator::macroPercentagesForDietProtocol(
        DietProtocol::CycleSync,
        CyclePhase::Luteal,
    );

    expect($percentages['carb_percentage'])->toBe(40.0)
        ->and($percentages['protein_percentage'])->toBe(40.0);
});

it('builds period tracking data with a current phase', function () {
    $data = PeriodTrackingPhaseService::buildPeriodTrackingData(
        [['start' => now()->subDays(3)->toDateString(), 'end' => now()->subDay()->toDateString()]],
        28,
    );

    expect($data)->toHaveKeys(['last_period_date', 'cycle_length', 'current_phase', 'logged_periods'])
        ->and($data['current_phase'])->toBe(CyclePhase::Menstrual->value);
});
