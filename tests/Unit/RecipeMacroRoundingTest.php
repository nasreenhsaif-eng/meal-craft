<?php

use App\Support\RecipeMacroRounding;

test('finalizes macros to one decimal and calories to nearest integer', function () {
    $final = RecipeMacroRounding::finalize([
        'calories' => 312.4,
        'protein' => 30.94,
        'carbs' => 12.26,
        'fat' => 8.15,
        'iron' => 1.23456,
    ]);

    expect($final['calories'])->toBe(312.0)
        ->and($final['protein'])->toBe(30.9)
        ->and($final['carbs'])->toBe(12.3)
        ->and($final['fat'])->toBe(8.2)
        ->and($final['iron'])->toBe(1.2346);
});

test('does not inflate fat when summing unrounded then rounding once', function () {
    $rawFat = 10.94 + 10.94 + 9.02;
    $final = RecipeMacroRounding::finalize([
        'calories' => 400,
        'protein' => 0,
        'carbs' => 0,
        'fat' => $rawFat,
    ]);

    expect($rawFat)->toEqualWithDelta(30.9, 0.001)
        ->and($final['fat'])->toBe(30.9)
        ->and($final['fat'])->not->toBe(33.0);
});

test('atwater audit flags recalculation sweep when drift exceeds five kcal', function () {
    $audit = RecipeMacroRounding::finalizeWithAudit([
        'calories' => 500,
        'protein' => 10,
        'carbs' => 10,
        'fat' => 10,
    ]);

    expect($audit['needs_calorie_recalculation_sweep'])->toBeTrue()
        ->and($audit['calories_recalculated_from_atwater'])->toBeFalse()
        ->and($audit['nutrition']['calories'])->toBe(500.0)
        ->and($audit['calorie_drift_kcal'])->toBeGreaterThan(5.0);
});

test('atwater sweep can replace calories when explicitly enabled', function () {
    $audit = RecipeMacroRounding::finalizeWithAudit([
        'calories' => 500,
        'protein' => 10,
        'carbs' => 10,
        'fat' => 10,
    ], applyAtwaterSweep: true);

    expect($audit['calories_recalculated_from_atwater'])->toBeTrue()
        ->and($audit['nutrition']['calories'])->toBe(170.0);
});

test('atwater audit does not flag when within five kcal', function () {
    $audit = RecipeMacroRounding::finalizeWithAudit([
        'calories' => 172,
        'protein' => 10,
        'carbs' => 10,
        'fat' => 10,
    ]);

    expect($audit['needs_calorie_recalculation_sweep'])->toBeFalse()
        ->and($audit['nutrition']['calories'])->toBe(172.0);
});
