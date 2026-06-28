<?php

use App\Services\Nutrition\DayMicronutrientCoverageAnalyzer;
use App\Support\NutrientDailyRdi;

test('aggregateAdaptedNutrition sums adapted meal nutrition keys', function () {
    $totals = DayMicronutrientCoverageAnalyzer::aggregateAdaptedNutrition([
        ['adapted_nutrition' => ['calories' => 400, 'iron' => 3, 'potassium' => 800]],
        ['adapted_nutrition' => ['calories' => 500, 'iron' => 2.5, 'potassium' => 600]],
    ]);

    expect($totals['calories'])->toBe(900.0)
        ->and($totals['iron'])->toBe(5.5)
        ->and($totals['potassium'])->toBe(1400.0);
});

test('analyzeDayNutrition enforces floor targets at 1500 tier', function () {
    $dayNutrition = [
        'calories' => 1500,
        'iron' => 17.64,
        'potassium' => 2600,
        'sodium' => 2300,
        'vitamin_d' => 1.0,
    ];

    $rows = DayMicronutrientCoverageAnalyzer::analyzeDayNutrition($dayNutrition, 1500);
    $iron = collect($rows)->firstWhere('key', 'iron');

    expect($iron)->not->toBeNull()
        ->and($iron['percent'])->toEqualWithDelta(98.0, 0.1)
        ->and($iron['meets_target'])->toBeTrue();
});

test('analyzeDayNutrition does not enforce floor targets at 1000 tier', function () {
    $dayNutrition = [
        'calories' => 1000,
        'iron' => 5.0,
    ];

    $rows = DayMicronutrientCoverageAnalyzer::analyzeDayNutrition($dayNutrition, 1000);
    $iron = collect($rows)->firstWhere('key', 'iron');

    expect($iron)->not->toBeNull()
        ->and($iron['percent'])->toBeLessThan(NutrientDailyRdi::FLOOR_TARGET_PERCENT)
        ->and($iron['meets_target'])->toBeTrue();
});

test('vitamin d is tracked as best effort and never fails meets_target', function () {
    $rows = DayMicronutrientCoverageAnalyzer::analyzeDayNutrition(['vitamin_d' => 0.5], 1500);
    $vitaminD = collect($rows)->firstWhere('key', 'vitamin_d');

    expect($vitaminD)->not->toBeNull()
        ->and($vitaminD['status'])->toBe('best_effort')
        ->and($vitaminD['meets_target'])->toBeTrue();
});
