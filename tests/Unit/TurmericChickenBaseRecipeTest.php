<?php

use App\Support\TurmericChickenBaseRecipe;

test('the turmeric chicken base is a 1 kg raw chicken batch with rosemary-style oil', function () {
    expect(TurmericChickenBaseRecipe::COMPONENTS['Chicken Breast'])->toBe(1000.0)
        ->and(TurmericChickenBaseRecipe::COMPONENTS['Olive Oil (Extra Virgin)'])->toBe(30.0)
        ->and(TurmericChickenBaseRecipe::finishedWeightGrams())->toBe(913.0)
        ->and(TurmericChickenBaseRecipe::recipeComponentsCsvCell())->toContain('Chicken Breast (1000g)')
        ->and(TurmericChickenBaseRecipe::recipeComponentsCsvCell())->toContain('Olive Oil (Extra Virgin) (30g)');
});
