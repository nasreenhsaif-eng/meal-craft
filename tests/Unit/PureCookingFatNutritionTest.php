<?php

use App\Models\Ingredient;
use App\Models\Meal;
use App\Support\PureCookingFatNutrition;

test('identifies pure cooking oils butter and ghee but not nut butters', function () {
    expect(PureCookingFatNutrition::isPureCookingFat('Olive Oil'))->toBeTrue()
        ->and(PureCookingFatNutrition::isPureCookingFat('Avocado Oil'))->toBeTrue()
        ->and(PureCookingFatNutrition::isPureCookingFat('Butter (Unsalted)'))->toBeTrue()
        ->and(PureCookingFatNutrition::isPureCookingFat('Ghee'))->toBeTrue()
        ->and(PureCookingFatNutrition::isPureCookingFat('Peanut Butter'))->toBeFalse()
        ->and(PureCookingFatNutrition::isPureCookingFat('Coconut Butter'))->toBeFalse()
        ->and(PureCookingFatNutrition::isPureCookingFat('Butter Beans'))->toBeFalse();
});

test('enforces oil density of 0.92 when library density is corrupt', function () {
    $oil = new Ingredient([
        'name' => 'Olive Oil',
        'density' => 0.31,
        'fat' => 100,
        'calories' => 884,
    ]);

    expect(PureCookingFatNutrition::densityGramsPerMl($oil))->toBe(0.92)
        ->and(PureCookingFatNutrition::gramsFromMilliliters($oil, 30.0))->toEqualWithDelta(27.6, 0.01);
});

test('30ml vegetable oil yields roughly 27g fat and 240 calories', function () {
    $oil = new Ingredient([
        'name' => 'Olive Oil (Extra Virgin)',
        'density' => 1.0,
        'fat' => 10,
        'calories' => 50,
    ]);

    $grams = PureCookingFatNutrition::gramsFromMilliliters($oil, 30.0);
    $fat = PureCookingFatNutrition::fatGramsForMass($oil, $grams);
    $calories = PureCookingFatNutrition::caloriesForMass($oil, $grams);

    expect($grams)->toEqualWithDelta(27.6, 0.01)
        ->and($fat)->toEqualWithDelta(27.6, 0.05)
        ->and($calories)->toEqualWithDelta(244.0, 2.0);
});

test('volumetric plausibility scales absurd single-serve saute oil down to 5-10ml', function () {
    $oil = new Ingredient([
        'name' => 'Olive Oil',
        'density' => 0.31,
        'fat' => 100,
        'calories' => 884,
    ]);
    $meal = new Meal([
        'name' => 'Skillet Chicken',
        'is_bulk' => false,
        'servings_count' => null,
    ]);

    $absurdGrams = PureCookingFatNutrition::gramsFromMilliliters($oil, 30.0);
    $scaled = PureCookingFatNutrition::applyVolumetricPlausibility($meal, $oil, $absurdGrams);
    $ml = PureCookingFatNutrition::millilitersFromGrams($oil, $scaled);

    expect($ml)->toBeGreaterThanOrEqual(5.0)
        ->and($ml)->toBeLessThanOrEqual(10.0);
});

test('intentional calorie target preserves high oil mass for accurate macros', function () {
    $oil = new Ingredient(['name' => 'Olive Oil', 'density' => 0.92]);
    $meal = new Meal(['is_bulk' => false]);
    $grams = PureCookingFatNutrition::gramsFromMilliliters($oil, 30.0);

    expect(PureCookingFatNutrition::applyVolumetricPlausibility($meal, $oil, $grams, intentionalCalorieTarget: true))
        ->toEqualWithDelta(27.6, 0.01);
});

test('resolved grams prefer amount_grams over corrupt volume density undercount', function () {
    $oil = new Ingredient([
        'name' => 'Olive Oil',
        'density' => 0.31,
        'fat' => 100,
        'calories' => 884,
    ]);

    $grams = PureCookingFatNutrition::resolvedGramsForRow([
        'amount' => 30,
        'unit' => 'ml',
        'amount_grams' => 27.6,
    ], $oil);

    expect($grams)->toEqualWithDelta(27.6, 0.01);
});
