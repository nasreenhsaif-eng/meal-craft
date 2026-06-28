<?php

use App\Models\CustomerProfile;
use App\Services\Nutrition\UserPlanCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('calculateUserPlan uses explicit tier slot targets and pick-2 fixed portions', function () {
    $profile = new CustomerProfile([
        'id' => 1,
        'daily_calorie_target' => 1500,
        'protein_percentage' => 30.0,
        'carb_percentage' => 40.0,
        'fat_percentage' => 30.0,
    ]);

    $plan = UserPlanCalculator::calculateUserPlan($profile);

    expect($plan['plan_tier'])->toBe(1500.0)
        ->and($plan['fixed_portion']['calories'])->toBe(300.0)
        ->and($plan['fixed_portion']['per_slot']['side_salad'])->toBe(150.0)
        ->and($plan['fixed_portion']['per_slot']['dessert'])->toBe(150.0)
        ->and($plan['fixed_portion']['per_slot']['soup'])->toBe(150.0)
        ->and($plan['scalable_slot_targets']['breakfast']['calories'])->toBe(300.0)
        ->and($plan['scalable_slot_targets']['main_each']['calories'])->toBe(450.0)
        ->and($plan['core_day_calories'])->toBe(1500.0)
        ->and($plan['day_total_calories'])->toBe(1500.0)
        ->and($plan['include_soup'])->toBeFalse();
});

test('selected fixed slots budget only chosen categories at 150 kcal each', function () {
    $profile = new CustomerProfile([
        'id' => 1,
        'daily_calorie_target' => 2000,
        'protein_percentage' => 30.0,
        'carb_percentage' => 40.0,
        'fat_percentage' => 30.0,
    ]);

    $sideAndSoup = UserPlanCalculator::calculateUserPlan($profile, [
        'selected_fixed_slots' => ['side_salad', 'soup'],
    ]);

    expect($sideAndSoup['include_soup'])->toBeTrue()
        ->and($sideAndSoup['fixed_portion']['calories'])->toBe(300.0)
        ->and($sideAndSoup['fixed_portion']['per_slot'])->toBe([
            'side_salad' => 150.0,
            'soup' => 150.0,
        ])
        ->and($sideAndSoup['scalable_slot_targets']['breakfast']['calories'])->toBe(450.0)
        ->and($sideAndSoup['scalable_slot_targets']['main_each']['calories'])->toBe(625.0)
        ->and($sideAndSoup['day_total_calories'])->toBe(2000.0);
});

test('fixed chia breakfast flag is tracked but tier breakfast targets still apply', function () {
    $profile = new CustomerProfile([
        'id' => 1,
        'daily_calorie_target' => 1500,
        'protein_percentage' => 30.0,
        'carb_percentage' => 40.0,
        'fat_percentage' => 30.0,
    ]);

    $plan = UserPlanCalculator::calculateUserPlan($profile, [
        'fixed_chia_breakfast' => true,
    ]);

    expect($plan['fixed_chia_breakfast'])->toBeTrue()
        ->and($plan['fixed_portion']['calories'])->toBe(300.0)
        ->and($plan['scalable_slot_targets']['breakfast']['calories'])->toBe(300.0)
        ->and($plan['scalable_slot_targets']['main_each']['calories'])->toBe(450.0)
        ->and($plan['day_total_calories'])->toBe(1500.0);
});

test('tier slot targets match spreadsheet at each plan tier', function (int $tier, float $breakfast, float $mainEach) {
    $profile = new CustomerProfile([
        'id' => 1,
        'daily_calorie_target' => $tier,
        'protein_percentage' => 30.0,
        'carb_percentage' => 40.0,
        'fat_percentage' => 30.0,
    ]);

    $plan = UserPlanCalculator::calculateUserPlan($profile, ['plan_tier' => (float) $tier]);

    expect($plan['scalable_slot_targets']['breakfast']['calories'])->toBe($breakfast)
        ->and($plan['scalable_slot_targets']['main_each']['calories'])->toBe($mainEach)
        ->and($plan['day_total_calories'])->toBe((float) $tier);
})->with([
    [1000, 200.0, 250.0],
    [1200, 200.0, 350.0],
    [1500, 300.0, 450.0],
    [1800, 400.0, 550.0],
    [2000, 450.0, 625.0],
]);

test('calculateUserPlan derives scaling multiplier from scalable budget and library baseline', function () {
    $profile = new CustomerProfile([
        'id' => 1,
        'daily_calorie_target' => 2000,
        'protein_percentage' => 30.0,
        'carb_percentage' => 40.0,
        'fat_percentage' => 30.0,
    ]);

    $plan = UserPlanCalculator::calculateUserPlan($profile);

    expect($plan['fixed']['calories'])->toBe(300.0)
        ->and($plan['remaining']['calories'])->toBe(1700.0)
        ->and($plan['scaling_multiplier'])->toBeGreaterThan(0);

    $baselineTotal = $plan['baseline_scalable']['calories'];
    expect($plan['scaling_multiplier'])->toEqual(round(1700 / $baselineTotal, 4));
});

test('snapToPlanTier returns nearest configured tier', function () {
    expect(UserPlanCalculator::snapToPlanTier(1480))->toBe(1500.0)
        ->and(UserPlanCalculator::snapToPlanTier(1620))->toBe(1500.0)
        ->and(UserPlanCalculator::snapToPlanTier(1900))->toBe(1800.0);
});

test('macro grams follow 4-4-9 rule from calorie percentages', function () {
    $macros = UserPlanCalculator::macroGramsFromCaloriesAndPercentages(2000, 30, 40, 30);

    expect($macros['protein_g'])->toBe(150.0)
        ->and($macros['carbs_g'])->toBe(200.0)
        ->and($macros['fat_g'])->toBe(66.67);
});
