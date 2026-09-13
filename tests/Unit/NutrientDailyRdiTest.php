<?php

use App\Enums\CustomerActivityLevel;
use App\Enums\CustomerSex;
use App\Support\NutrientDailyRdi;

test('rdi values mirror frontend nutrientDailyRdi labels', function () {
    expect(NutrientDailyRdi::rdiForLabel('Iron (mg)'))->toBe(18.0)
        ->and(NutrientDailyRdi::rdiForLabel('Potassium (mg)'))->toBe(2600.0)
        ->and(NutrientDailyRdi::rdiForLabel('Vitamin D (mcg)'))->toBe(15.0)
        ->and(NutrientDailyRdi::rdiForLabel('Magnesium (mg)'))->toBe(320.0)
        ->and(NutrientDailyRdi::rdiForLabel('Sugar (g)'))->toBe(25.0);
});

test('female baseline iron is 18 mg and male baseline iron is 8 mg', function () {
    $female = NutrientDailyRdi::calculate(CustomerSex::Female, CustomerActivityLevel::Sedentary);
    $male = NutrientDailyRdi::calculate(CustomerSex::Male, CustomerActivityLevel::Sedentary);

    expect($female['Iron (mg)'])->toBe(18.0)
        ->and($male['Iron (mg)'])->toBe(8.0);
});

test('somewhat active female iron is 10 percent above the sex baseline', function () {
    $targets = NutrientDailyRdi::calculate(CustomerSex::Female, CustomerActivityLevel::LightlyActive);

    expect($targets['Iron (mg)'])->toBe(19.8);
});

test('extremely active female raises iron magnesium and sodium versus somewhat active', function () {
    $somewhat = NutrientDailyRdi::calculate(CustomerSex::Female, CustomerActivityLevel::LightlyActive, 1500);
    $extreme = NutrientDailyRdi::calculate(CustomerSex::Female, CustomerActivityLevel::VeryActive, 1500);

    expect($somewhat['Iron (mg)'])->toBe(19.8)
        ->and($extreme['Iron (mg)'])->toBe(27.0)
        ->and($somewhat['Magnesium (mg)'])->toBe(320.0)
        ->and($extreme['Magnesium (mg)'])->toBe(384.0)
        ->and($somewhat['Sodium (mg)'])->toBe(2300.0)
        ->and($extreme['Sodium (mg)'])->toBe(3300.0)
        ->and($somewhat['Fiber (g)'])->toBe(25.0)
        ->and($extreme['Fiber (g)'])->toBe(25.0);
});

test('fiber uses the sex floor at 1500 kcal and the calorie formula at 2800 kcal', function () {
    $femaleAt1500 = NutrientDailyRdi::calculate(CustomerSex::Female, CustomerActivityLevel::VeryActive, 1500);
    $maleAt1500 = NutrientDailyRdi::calculate(CustomerSex::Male, CustomerActivityLevel::VeryActive, 1500);
    $femaleAt2800 = NutrientDailyRdi::calculate(CustomerSex::Female, CustomerActivityLevel::VeryActive, 2800);

    expect($femaleAt1500['Fiber (g)'])->toBe(25.0)
        ->and($maleAt1500['Fiber (g)'])->toBe(38.0)
        ->and($femaleAt2800['Fiber (g)'])->toBe(39.2);
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
