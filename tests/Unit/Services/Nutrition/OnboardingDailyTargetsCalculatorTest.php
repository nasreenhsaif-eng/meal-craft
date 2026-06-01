<?php

use App\Enums\CustomerActivityLevel;
use App\Enums\CustomerSex;
use App\Enums\CyclePhase;
use App\Enums\DietProtocol;
use App\Models\CustomerProfile;
use App\Services\Nutrition\OnboardingDailyTargetsCalculator;
use App\Services\Nutrition\PeriodTrackingPhaseService;

it('applies a 500 kcal deficit when target weight is below current weight', function () {
    $profile = new CustomerProfile([
        'sex' => CustomerSex::Female,
        'age' => 32,
        'weight_kg' => 72,
        'target_weight_kg' => 65,
        'height_cm' => 168,
        'activity_level' => CustomerActivityLevel::LightlyActive,
        'diet_protocol' => DietProtocol::Balanced->value,
    ]);

    $targets = OnboardingDailyTargetsCalculator::calculate($profile);

    expect($targets['daily_calories'])->toBeLessThan($targets['tdee']);
    expect($targets['tdee'] - $targets['daily_calories'])->toBeGreaterThanOrEqual(500);
});

it('uses ketobiotic macro percentages for ketobiotic diet protocol', function () {
    $percentages = OnboardingDailyTargetsCalculator::macroPercentagesForDietProtocol(DietProtocol::Ketobiotic);

    expect($percentages['carb_percentage'])->toBe(10.0)
        ->and($percentages['fat_percentage'])->toBe(70.0);
});

it('uses balanced macro split by default', function () {
    $percentages = OnboardingDailyTargetsCalculator::macroPercentagesForDietProtocol(DietProtocol::Balanced);

    expect($percentages['protein_percentage'])->toBe(30.0)
        ->and($percentages['carb_percentage'])->toBe(40.0)
        ->and($percentages['fat_percentage'])->toBe(30.0);
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
        ->and($percentages['protein_percentage'])->toBe(30.0);
});

it('builds period tracking data with a current phase', function () {
    $data = PeriodTrackingPhaseService::buildPeriodTrackingData(
        [['start' => now()->subDays(3)->toDateString(), 'end' => now()->subDay()->toDateString()]],
        28,
    );

    expect($data)->toHaveKeys(['last_period_date', 'cycle_length', 'current_phase', 'logged_periods'])
        ->and($data['current_phase'])->toBe(CyclePhase::Menstrual->value);
});
