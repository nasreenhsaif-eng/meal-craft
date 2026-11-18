<?php

use App\Models\CustomerProfile;
use App\Services\Nutrition\CraftCaloriePlanner;
use App\Services\Nutrition\UserPlanCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('craft calorie budgets use the library map at 1500 kcal', function () {
    $profile = new CustomerProfile([
        'id' => 1,
        'daily_calorie_target' => 1500,
        'protein_percentage' => 30.0,
        'carb_percentage' => 40.0,
        'fat_percentage' => 30.0,
    ]);

    $basePlan = UserPlanCalculator::calculateUserPlan($profile);

    $full = CraftCaloriePlanner::applyCraftToPlan($basePlan, CraftCaloriePlanner::CRAFT_FULL);
    $afternoon = CraftCaloriePlanner::applyCraftToPlan($basePlan, CraftCaloriePlanner::CRAFT_AFTERNOON);
    $day = CraftCaloriePlanner::applyCraftToPlan($basePlan, CraftCaloriePlanner::CRAFT_DAY);
    $intermittent = CraftCaloriePlanner::applyCraftToPlan($basePlan, CraftCaloriePlanner::CRAFT_INTERMITTENT);
    $business = CraftCaloriePlanner::applyCraftToPlan($basePlan, CraftCaloriePlanner::CRAFT_BUSINESS);

    expect($full['craft_day_calories'])->toBe(1500.0)
        ->and($full['scalable_slot_targets']['breakfast']['calories'])->toBe(300.0)
        ->and($full['scalable_slot_targets']['main_each']['calories'])->toBe(400.0)
        ->and($afternoon['craft_day_calories'])->toBe(1500.0)
        ->and($afternoon['scalable_slot_targets']['breakfast']['calories'])->toBe(0.0)
        ->and($afternoon['scalable_slot_targets']['main_each']['calories'])->toBe(550.0)
        ->and($day['craft_day_calories'])->toBe(1400.0)
        ->and($day['scalable_slot_targets']['breakfast']['calories'])->toBe(500.0)
        ->and($day['scalable_slot_targets']['main_each']['calories'])->toBe(500.0)
        ->and($intermittent['craft_day_calories'])->toBe(1150.0)
        ->and($intermittent['scalable_slot_targets']['main_each']['calories'])->toBe(600.0)
        ->and($business['craft_day_calories'])->toBe(650.0)
        ->and($business['scalable_slot_targets']['main_each']['calories'])->toBe(400.0);
});

test('intermittent craft uses a discrete main tab instead of pick-2 scaling', function () {
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
        ->and($intermittent['library_slots']['include_soup'])->toBeTrue()
        ->and($intermittent['scalable_slot_targets']['main_each']['calories'])->toBe(600.0);
});

test('full craft 2000 is breakfast 500 and mains 600', function () {
    $profile = new CustomerProfile([
        'id' => 1,
        'daily_calorie_target' => 2000,
        'protein_percentage' => 30.0,
        'carb_percentage' => 40.0,
        'fat_percentage' => 30.0,
    ]);

    $full = CraftCaloriePlanner::applyCraftToPlan(
        UserPlanCalculator::calculateUserPlan($profile),
        CraftCaloriePlanner::CRAFT_FULL,
    );

    expect($full['scalable_slot_targets']['breakfast']['calories'])->toBe(500.0)
        ->and($full['scalable_slot_targets']['main_each']['calories'])->toBe(600.0)
        ->and($full['library_slots']['include_dessert'])->toBeTrue();
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
