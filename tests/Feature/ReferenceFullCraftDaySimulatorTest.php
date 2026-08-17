<?php

use App\Enums\MealPlanSlotType;
use App\Models\CustomerProfile;
use App\Models\Meal;
use App\Services\BalancedWeeklyRotationSchedule;
use App\Services\Nutrition\DayMicronutrientCoverageAnalyzer;
use App\Services\Nutrition\ReferenceFullCraftDaySimulator;

test('reference full craft simulator can swap slot three main for daily liver main', function () {
    $liverMealName = BalancedWeeklyRotationSchedule::mealNameForDay(1, MealPlanSlotType::Main, 5);
    $liverMeal = Meal::queryForMealLibrary()->where('name', $liverMealName)->first();

    if ($liverMeal === null) {
        test()->markTestSkipped('Liver rotation meals are not seeded.');
    }

    $profile = CustomerProfile::factory()->create([
        'daily_calorie_target' => 1500,
        'protein_percentage' => 35.0,
        'carb_percentage' => 35.0,
        'fat_percentage' => 30.0,
    ]);

    $withLiver = ReferenceFullCraftDaySimulator::simulate($profile, 1, 1500.0, ['side_salad', 'dessert'], true);

    $liverMainTitles = collect($withLiver['adapted_meals'])
        ->where('slot', 'main')
        ->pluck('title')
        ->all();

    expect($liverMainTitles)->toHaveCount(2)
        ->and(collect($liverMainTitles)->contains(
            fn (string $title): bool => str_contains(strtolower($title), 'liver'),
        ))->toBeTrue()
        ->and($liverMainTitles[1])->toBe($liverMealName);
});

test('day micronutrient coverage analyzer forwards with liver swap flag', function () {
    $profile = CustomerProfile::factory()->create([
        'daily_calorie_target' => 1500,
        'protein_percentage' => 35.0,
        'carb_percentage' => 35.0,
        'fat_percentage' => 30.0,
    ]);

    $report = DayMicronutrientCoverageAnalyzer::simulateFullCraftDay(
        $profile,
        1,
        1500.0,
        ['side_salad', 'dessert'],
        true,
    );

    expect($report)->toHaveKeys(['day_number', 'plan_tier', 'passes', 'nutrients'])
        ->and($report['day_number'])->toBe(1);
});
