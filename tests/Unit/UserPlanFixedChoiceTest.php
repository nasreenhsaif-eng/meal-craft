<?php

use App\Models\CustomerProfile;
use App\Services\Nutrition\UserPlanCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('pick-2 fixed slot combinations each total to plan tier', function (array $selectedFixedSlots) {
    $profile = new CustomerProfile([
        'id' => 1,
        'daily_calorie_target' => 1800,
        'protein_percentage' => 30.0,
        'carb_percentage' => 40.0,
        'fat_percentage' => 30.0,
    ]);

    $plan = UserPlanCalculator::calculateUserPlan($profile, [
        'plan_tier' => 1800.0,
        'selected_fixed_slots' => $selectedFixedSlots,
    ]);

    expect($plan['day_total_calories'])->toBe(1800.0)
        ->and($plan['fixed_portion']['calories'])->toBe(300.0)
        ->and($plan['scalable_slot_targets']['breakfast']['calories'])->toBe(400.0)
        ->and($plan['scalable_slot_targets']['main_each']['calories'])->toBe(550.0)
        ->and(count($plan['fixed_portion']['per_slot']))->toBe(2);
})->with([
    'side and dessert' => [['side_salad', 'dessert']],
    'side and soup' => [['side_salad', 'soup']],
    'dessert and soup' => [['dessert', 'soup']],
]);

test('one fixed slot redistributes unused side budget to scalable meals', function () {
    $profile = new CustomerProfile([
        'id' => 1,
        'daily_calorie_target' => 2000,
        'protein_percentage' => 30.0,
        'carb_percentage' => 40.0,
        'fat_percentage' => 30.0,
    ]);

    $twoSides = UserPlanCalculator::calculateUserPlan($profile, [
        'plan_tier' => 2000.0,
        'selected_fixed_slots' => ['side_salad', 'dessert'],
    ]);

    $oneSide = UserPlanCalculator::calculateUserPlan($profile, [
        'plan_tier' => 2000.0,
        'selected_fixed_slots' => ['dessert'],
    ]);

    // Breakfast stays tier-fixed (450); unused fixed budget goes entirely to mains.
    expect($oneSide['day_total_calories'])->toEqualWithDelta(2000.0, 0.05)
        ->and($oneSide['fixed_portion']['calories'])->toBe(150.0)
        ->and($oneSide['scalable_slot_targets']['breakfast']['calories'])->toBe(450.0)
        ->and($oneSide['scalable_slot_targets']['main_each']['calories'])->toEqualWithDelta(700.0, 0.05)
        ->and($oneSide['scalable_slot_targets']['main_each']['calories'])
        ->toBeGreaterThan($twoSides['scalable_slot_targets']['main_each']['calories']);
});

test('actual fixed portion calories rebalance mains only so breakfast stays tier-fixed', function () {
    $profile = new CustomerProfile([
        'id' => 1,
        'daily_calorie_target' => 1800,
        'protein_percentage' => 30.0,
        'carb_percentage' => 40.0,
        'fat_percentage' => 30.0,
    ]);

    $plan = UserPlanCalculator::calculateUserPlan($profile, [
        'plan_tier' => 1800.0,
        'selected_fixed_slots' => ['soup', 'dessert'],
        'soup_calories' => 90.0,
        'dessert_calories' => 350.0,
    ]);

    // Fixed 440 + breakfast 400 + 2×480 mains = 1800; breakfast never shrinks for dessert.
    expect($plan['day_total_calories'])->toEqualWithDelta(1800.0, 0.05)
        ->and($plan['fixed_portion']['calories'])->toBe(440.0)
        ->and($plan['scalable_slot_targets']['breakfast']['calories'])->toBe(400.0)
        ->and($plan['scalable_slot_targets']['main_each']['calories'])->toEqualWithDelta(480.0, 0.05);
});
