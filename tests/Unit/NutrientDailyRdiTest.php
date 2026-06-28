<?php

use App\Support\NutrientDailyRdi;

test('rdi values mirror frontend nutrientDailyRdi labels', function () {
    expect(NutrientDailyRdi::rdiForLabel('Iron (mg)'))->toBe(18.0)
        ->and(NutrientDailyRdi::rdiForLabel('Potassium (mg)'))->toBe(2600.0)
        ->and(NutrientDailyRdi::rdiForLabel('Vitamin D (mcg)'))->toBe(15.0);
});

test('enforced and informational tiers follow plan policy', function () {
    expect(NutrientDailyRdi::enforcedTiers())->toBe([1500, 1800, 2000])
        ->and(NutrientDailyRdi::informationalTiers())->toBe([1000, 1200])
        ->and(NutrientDailyRdi::tierEnforced(1500))->toBeTrue()
        ->and(NutrientDailyRdi::tierEnforced(1200))->toBeFalse();
});

test('nutrient status classifies floor ceiling and best effort nutrients', function () {
    expect(NutrientDailyRdi::nutrientStatus('Iron (mg)'))->toBe('floor')
        ->and(NutrientDailyRdi::nutrientStatus('Sodium (mg)'))->toBe('ceiling')
        ->and(NutrientDailyRdi::nutrientStatus('Vitamin D (mcg)'))->toBe('best_effort');
});

test('percent of rdi and floor target helpers', function () {
    expect(NutrientDailyRdi::percentOfRdi('Iron (mg)', 9.0))->toBe(50.0)
        ->and(NutrientDailyRdi::meetsFloorTarget('Iron (mg)', 98.0))->toBeTrue()
        ->and(NutrientDailyRdi::meetsFloorTarget('Iron (mg)', 97.0))->toBeFalse()
        ->and(NutrientDailyRdi::meetsCeilingTarget('Sodium (mg)', 101.0))->toBeFalse();
});
