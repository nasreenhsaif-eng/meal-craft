<?php

use App\Models\CustomerProfile;
use App\Services\Nutrition\UserPlanCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('calculateUserPlan allocates core tier across fixed portions and scalable slots', function () {
    $profile = new CustomerProfile([
        'id' => 1,
        'daily_calorie_target' => 1500,
        'protein_percentage' => 30.0,
        'carb_percentage' => 40.0,
        'fat_percentage' => 30.0,
    ]);

    $plan = UserPlanCalculator::calculateUserPlan($profile);

    expect($plan['plan_tier'])->toBe(1500.0)
        ->and($plan['fixed_portion']['calories'])->toBe(345.0)
        ->and($plan['fixed_portion']['per_slot']['side_salad'])->toBe(175.0)
        ->and($plan['fixed_portion']['per_slot']['dessert'])->toBe(170.0)
        ->and($plan['scalable_budget']['calories'])->toBe(1155.0)
        ->and($plan['scalable_slot_targets']['breakfast']['calories'])->toBe(231.0)
        ->and($plan['scalable_slot_targets']['main_each']['calories'])->toBe(462.0)
        ->and($plan['core_day_calories'])->toBe(1500.0)
        ->and($plan['day_total_calories'])->toBe(1500.0)
        ->and($plan['include_soup'])->toBeFalse();
});

test('included soup counts within tier and shrinks scalable slot targets', function () {
    $profile = new CustomerProfile([
        'id' => 1,
        'daily_calorie_target' => 1500,
        'protein_percentage' => 30.0,
        'carb_percentage' => 40.0,
        'fat_percentage' => 30.0,
    ]);

    $corePlan = UserPlanCalculator::calculateUserPlan($profile);
    $withSoup = UserPlanCalculator::calculateUserPlan($profile, [
        'include_soup' => true,
        'soup_calories' => 150.0,
    ]);

    expect($withSoup['include_soup'])->toBeTrue()
        ->and($withSoup['optional_add_on']['soup']['calories'])->toBe(150.0)
        ->and($withSoup['fixed_portion']['calories'])->toBe(495.0)
        ->and($withSoup['scalable_budget']['calories'])->toBe(1005.0)
        ->and($withSoup['scalable_slot_targets']['breakfast']['calories'])->toBe(201.0)
        ->and($withSoup['scalable_slot_targets']['main_each']['calories'])->toBe(402.0)
        ->and($withSoup['scalable_slot_targets']['breakfast']['calories'])
        ->toBeLessThan($corePlan['scalable_slot_targets']['breakfast']['calories'])
        ->and($withSoup['scalable_slot_targets']['main_each']['calories'])
        ->toBeLessThan($corePlan['scalable_slot_targets']['main_each']['calories'])
        ->and($withSoup['core_day_calories'])->toBe(1500.0)
        ->and($withSoup['day_total_calories'])->toBe(1500.0);
});

test('fixed chia breakfast counts 200 kcal toward tier and gives mains the remaining scalable budget', function () {
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
        ->and($plan['fixed_portion']['calories'])->toBe(545.0)
        ->and($plan['fixed_portion']['per_slot']['breakfast'])->toBe(200.0)
        ->and($plan['scalable_budget']['calories'])->toBe(955.0)
        ->and($plan['scalable_slot_targets']['breakfast']['calories'])->toBe(200.0)
        ->and($plan['scalable_slot_targets']['main_each']['calories'])->toBe(477.5)
        ->and($plan['day_total_calories'])->toBe(1500.0);
});

test('calculateUserPlan derives scaling multiplier from scalable budget and library baseline', function () {
    $profile = new CustomerProfile([
        'id' => 1,
        'daily_calorie_target' => 2000,
        'protein_percentage' => 30.0,
        'carb_percentage' => 40.0,
        'fat_percentage' => 30.0,
    ]);

    $plan = UserPlanCalculator::calculateUserPlan($profile);

    expect($plan['fixed']['calories'])->toBe(345.0)
        ->and($plan['remaining']['calories'])->toBe(1655.0)
        ->and($plan['scaling_multiplier'])->toBeGreaterThan(0);

    $baselineTotal = $plan['baseline_scalable']['calories'];
    expect($plan['scaling_multiplier'])->toEqual(round(1655 / $baselineTotal, 4));
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
