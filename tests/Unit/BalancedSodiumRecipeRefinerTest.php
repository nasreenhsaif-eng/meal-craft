<?php

use App\Services\BalancedCanonicalMealRecipeRefiner;
use App\Services\BalancedSodiumRecipeRefiner;
use App\Support\StandardMeatPortion;

test('sodium adjust does not shrink rosemary garlic chicken base', function () {
    $refiner = app(BalancedSodiumRecipeRefiner::class);

    $adjusted = $refiner->adjustIngredientGrams([
        'Rosemary Garlic Chicken (Base)' => StandardMeatPortion::GRAMS,
        'Sweet Potato' => 85.0,
        'Spinach (Fresh)' => 55.0,
        'Mushrooms' => 45.0,
        'Olive Oil (Extra Virgin)' => 4.0,
        'Black Pepper' => 0.5,
        'Sea Salt' => 1.0,
    ], BalancedCanonicalMealRecipeRefiner::ROSEMARY_GARLIC_CHICKEN_PLATE_NAME);

    expect($adjusted['Rosemary Garlic Chicken (Base)'])->toBe(StandardMeatPortion::GRAMS)
        ->and($adjusted)->not->toHaveKey('Sea Salt');
});

test('sodium adjust restores collapsed primary chicken to the standard portion', function () {
    $refiner = app(BalancedSodiumRecipeRefiner::class);

    $adjusted = $refiner->adjustIngredientGrams([
        'Rosemary Garlic Chicken (Base)' => 2.0,
        'Sweet Potato' => 100.0,
        'Spinach (Fresh)' => 100.0,
        'Mushrooms' => 45.0,
    ], BalancedCanonicalMealRecipeRefiner::ROSEMARY_GARLIC_CHICKEN_PLATE_NAME);

    expect($adjusted['Rosemary Garlic Chicken (Base)'])->toBe(StandardMeatPortion::GRAMS);
});

test('sodium adjust is idempotent for dressings and chicken', function () {
    $refiner = app(BalancedSodiumRecipeRefiner::class);
    $mealName = BalancedCanonicalMealRecipeRefiner::ROSEMARY_GARLIC_CHICKEN_PLATE_NAME;

    $first = $refiner->adjustIngredientGrams([
        'Rosemary Garlic Chicken (Base)' => StandardMeatPortion::GRAMS,
        'Red Pepper Dressing (Base)' => 20.0,
        'Vegetable Stock' => 40.0,
    ], $mealName);

    $second = $refiner->adjustIngredientGrams($first, $mealName);

    expect($second['Rosemary Garlic Chicken (Base)'])->toBe(StandardMeatPortion::GRAMS)
        ->and($second['Red Pepper Dressing (Base)'])->toBe($first['Red Pepper Dressing (Base)'])
        ->and($second['Vegetable Stock'])->toBe($first['Vegetable Stock'])
        ->and($second['Water (Filtered)'] ?? 0.0)->toBe($first['Water (Filtered)'] ?? 0.0);
});

test('rosemary garlic chicken base is classified as primary meat', function () {
    expect(StandardMeatPortion::isPrimaryMeatIngredient(
        'Rosemary Garlic Chicken (Base)',
        BalancedCanonicalMealRecipeRefiner::ROSEMARY_GARLIC_CHICKEN_PLATE_NAME,
    ))->toBeTrue();
});
