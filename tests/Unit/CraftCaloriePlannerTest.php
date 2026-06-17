<?php

use App\Models\CustomerProfile;
use App\Services\Nutrition\CraftCaloriePlanner;
use App\Services\Nutrition\UserPlanCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('craft calorie budgets match consultation craft rules at 1500 kcal tier', function () {
    $profile = new CustomerProfile([
        'id' => 1,
        'daily_calorie_target' => 1500,
        'protein_percentage' => 30.0,
        'carb_percentage' => 40.0,
        'fat_percentage' => 30.0,
    ]);

    $basePlan = UserPlanCalculator::calculateUserPlan($profile);

    $breakfast = (float) $basePlan['scalable_slot_targets']['breakfast']['calories'];
    $mainEach = (float) $basePlan['scalable_slot_targets']['main_each']['calories'];

    $full = CraftCaloriePlanner::applyCraftToPlan($basePlan, CraftCaloriePlanner::CRAFT_FULL);
    $afternoon = CraftCaloriePlanner::applyCraftToPlan($basePlan, CraftCaloriePlanner::CRAFT_AFTERNOON);
    $day = CraftCaloriePlanner::applyCraftToPlan($basePlan, CraftCaloriePlanner::CRAFT_DAY);
    $intermittent = CraftCaloriePlanner::applyCraftToPlan($basePlan, CraftCaloriePlanner::CRAFT_INTERMITTENT);
    $business = CraftCaloriePlanner::applyCraftToPlan($basePlan, CraftCaloriePlanner::CRAFT_BUSINESS);

    expect($full['craft_day_calories'])->toBe(1500.0)
        ->and($full['craft_soup_counts_as_add_on'])->toBeTrue()
        ->and($afternoon['craft_day_calories'])->toBe(round(1500.0 - $breakfast, 2))
        ->and($day['craft_day_calories'])->toBe(round(1500.0 - $mainEach, 2))
        ->and($intermittent['craft_day_calories'])->toBe(round(1500.0 - $breakfast - $mainEach, 2))
        ->and($business['craft_day_calories'])->toBe(500.0)
        ->and($business['scalable_slot_targets']['main_each']['calories'])->toBe(325.0);
});

test('intermittent craft scales the single main within soup salad and dessert budget', function () {
    $profile = new CustomerProfile([
        'id' => 1,
        'daily_calorie_target' => 1500,
        'protein_percentage' => 30.0,
        'carb_percentage' => 40.0,
        'fat_percentage' => 30.0,
    ]);

    $basePlan = UserPlanCalculator::calculateUserPlan($profile);
    $intermittent = CraftCaloriePlanner::applyCraftToPlan($basePlan, CraftCaloriePlanner::CRAFT_INTERMITTENT);

    expect($intermittent['craft_soup_counts_as_add_on'])->toBeFalse()
        ->and($intermittent['scalable_slot_targets']['main_each']['calories'])->toBe(312.0);
});

test('unknown craft keys throw', function () {
    $basePlan = UserPlanCalculator::calculateUserPlan(new CustomerProfile([
        'daily_calorie_target' => 1500,
        'protein_percentage' => 30,
        'carb_percentage' => 40,
        'fat_percentage' => 30,
    ]));

    CraftCaloriePlanner::applyCraftToPlan($basePlan, 'invalid');
})->throws(InvalidArgumentException::class);
