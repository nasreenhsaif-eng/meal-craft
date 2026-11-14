<?php

use App\Models\Meal;
use App\Support\MainMealVegetablePortionFloor;

test('named plate broccoli on tamarind meal has 60g canonical floor', function () {
    $meal = new Meal([
        'name' => 'Tamarind Honey & Sesame Chicken w Garlicky Green Beans',
        'instructions' => 'Steam broccoli until tender.',
    ]);

    expect(MainMealVegetablePortionFloor::minimumGrams($meal, 'Broccoli'))->toBe(60.0)
        ->and(MainMealVegetablePortionFloor::isNamedPlateComponent($meal, 'Broccoli'))->toBeTrue();
});

test('apply floors raises sub-minimum named plate vegetables to canonical grams', function () {
    $meal = new Meal([
        'name' => 'Tamarind Honey & Sesame Chicken w Garlicky Green Beans',
        'instructions' => 'Serve with steamed broccoli.',
    ]);

    $adjusted = MainMealVegetablePortionFloor::applyFloors($meal, [
        'Broccoli' => 10.0,
        'Chicken Breast' => 150.0,
    ]);

    expect($adjusted['Broccoli'])->toBe(60.0)
        ->and($adjusted['Chicken Breast'])->toBe(150.0);
});

test('default 40g floor applies to named plate vegetables without meal-specific canonical', function () {
    $meal = new Meal([
        'name' => 'Sample Main With Broccoli',
        'instructions' => 'Plate steamed broccoli alongside the protein.',
    ]);

    expect(MainMealVegetablePortionFloor::minimumGrams($meal, 'Broccoli'))->toBe(40.0);
});

test('non plate vegetables and hidden ingredients have no floor', function () {
    $meal = new Meal([
        'name' => 'Tamarind Honey & Sesame Chicken w Garlicky Green Beans',
        'instructions' => 'Steam broccoli until tender.',
    ]);

    expect(MainMealVegetablePortionFloor::minimumGrams($meal, 'Garlic (Raw)'))->toBeNull()
        ->and(MainMealVegetablePortionFloor::minimumGrams($meal, 'Spinach (Fresh)'))->toBeNull();
});
