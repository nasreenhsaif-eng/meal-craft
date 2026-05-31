<?php

use App\Models\CustomerProfile;
use App\Services\Nutrition\UserPlanCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('calculateUserPlan subtracts 450 kcal fixed budget and derives multiplier', function () {
    $profile = new CustomerProfile([
        'id' => 1,
        'daily_calorie_target' => 2000,
        'protein_percentage' => 30.0,
        'carb_percentage' => 40.0,
        'fat_percentage' => 30.0,
    ]);

    $plan = UserPlanCalculator::calculateUserPlan($profile);

    expect($plan['fixed']['calories'])->toBe(450.0)
        ->and($plan['remaining']['calories'])->toBe(1550.0)
        ->and($plan['scaling_multiplier'])->toBeGreaterThan(0);

    $baselineTotal = $plan['baseline_scalable']['calories'];
    expect($plan['scaling_multiplier'])->toEqual(round(1550 / $baselineTotal, 4));
});

test('macro grams follow 4-4-9 rule from calorie percentages', function () {
    $macros = UserPlanCalculator::macroGramsFromCaloriesAndPercentages(2000, 30, 40, 30);

    expect($macros['protein_g'])->toBe(150.0)
        ->and($macros['carbs_g'])->toBe(200.0)
        ->and($macros['fat_g'])->toBe(66.67);
});
