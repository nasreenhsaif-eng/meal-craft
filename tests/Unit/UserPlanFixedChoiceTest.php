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

test('actual fixed portion calories rebalance scalable slots so day total matches tier', function () {
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

    expect($plan['day_total_calories'])->toEqualWithDelta(1800.0, 0.05)
        ->and($plan['fixed_portion']['calories'])->toBe(440.0)
        ->and($plan['scalable_slot_targets']['breakfast']['calories'])->toEqualWithDelta(362.67, 0.05)
        ->and($plan['scalable_slot_targets']['main_each']['calories'])->toEqualWithDelta(498.67, 0.05);
});
