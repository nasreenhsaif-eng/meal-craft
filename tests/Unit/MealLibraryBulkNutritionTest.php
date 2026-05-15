<?php

use App\Support\MealLibraryBulkNutrition;
use Tests\TestCase;

uses(TestCase::class);

test('bulk nutrition prefers ingredient rollup divided by servings', function () {
    $batch = [
        'calories' => 400.0,
        'protein' => 40.0,
        'carbs' => 20.0,
        'fat' => 8.0,
    ];

    $resolved = MealLibraryBulkNutrition::resolvePersistedNutrition(
        $batch,
        true,
        2.0,
        ['calories' => 9999.0, 'protein' => 99.0, 'carbs' => 88.0, 'fat' => 77.0],
        true,
    );

    expect($resolved['nutrition_aggregates_synced'])->toBeFalse()
        ->and((float) $resolved['attributes']['total_calories'])->toBe(200.0)
        ->and((float) $resolved['attributes']['total_protein'])->toBe(20.0)
        ->and((float) $resolved['attributes']['total_carbs'])->toBe(10.0)
        ->and((float) $resolved['attributes']['total_fat'])->toBe(4.0);
});

test('bulk nutrition uses csv batch macros when ingredient rollup is empty', function () {
    $resolved = MealLibraryBulkNutrition::resolvePersistedNutrition(
        ['calories' => 0.0, 'protein' => 0.0, 'carbs' => 0.0, 'fat' => 0.0],
        true,
        4.0,
        ['calories' => 800.0, 'protein' => 48.0, 'carbs' => 80.0, 'fat' => 32.0],
        false,
    );

    expect((float) $resolved['attributes']['total_calories'])->toBe(200.0)
        ->and((float) $resolved['attributes']['total_protein'])->toBe(12.0)
        ->and((float) $resolved['attributes']['total_carbs'])->toBe(20.0)
        ->and((float) $resolved['attributes']['total_fat'])->toBe(8.0);
});

test('non bulk nutrition keeps batch totals and synced flag when ingredients exist', function () {
    $batch = ['calories' => 320.0, 'protein' => 12.0, 'carbs' => 40.0, 'fat' => 10.0];

    $resolved = MealLibraryBulkNutrition::resolvePersistedNutrition($batch, false, null, null, true);

    expect($resolved['nutrition_aggregates_synced'])->toBeTrue()
        ->and((float) $resolved['attributes']['total_calories'])->toBe(320.0);
});
