<?php

use App\Services\RecipeNutritionCalculator;

test('sickle cell program highlight triggers on iron and vitamin C pairing', function () {
    expect(RecipeNutritionCalculator::sickleCellProgramMealHighlight([
        'iron' => 5.0,
        'vitamin_c' => 30.0,
        'b9_folate' => 0,
        'b12' => 0,
        'magnesium' => 0,
        'zinc' => 0,
        'vitamin_e' => 0,
    ]))->toBeTrue();
});

test('sickle cell program highlight triggers on zinc and vitamin E pairing', function () {
    expect(RecipeNutritionCalculator::sickleCellProgramMealHighlight([
        'iron' => 0,
        'vitamin_c' => 0,
        'b9_folate' => 0,
        'b12' => 0,
        'magnesium' => 0,
        'zinc' => 3.0,
        'vitamin_e' => 2.0,
    ]))->toBeTrue();
});

test('sickle cell program highlight is false for sparse nutrition', function () {
    expect(RecipeNutritionCalculator::sickleCellProgramMealHighlight([
        'iron' => 1.0,
        'vitamin_c' => 5.0,
        'b9_folate' => 10,
        'b12' => 0,
        'magnesium' => 20,
        'zinc' => 0.5,
        'vitamin_e' => 0.1,
    ]))->toBeFalse();
});
