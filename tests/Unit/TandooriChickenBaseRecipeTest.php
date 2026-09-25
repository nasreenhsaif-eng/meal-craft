<?php

use App\Support\TandooriChickenBaseRecipe;

test('the tandoori chicken base is a 1 kg raw chicken batch with rosemary-style oil', function () {
    expect(TandooriChickenBaseRecipe::COMPONENTS['Chicken Breast'])->toBe(1000.0)
        ->and(TandooriChickenBaseRecipe::COMPONENTS['Olive Oil (Extra Virgin)'])->toBe(30.0)
        ->and(TandooriChickenBaseRecipe::finishedWeightGrams())->toBe(1003.0)
        ->and(TandooriChickenBaseRecipe::recipeComponentsCsvCell())->toContain('Chicken Breast (1000g)')
        ->and(TandooriChickenBaseRecipe::recipeComponentsCsvCell())->toContain('Olive Oil (Extra Virgin) (30g)');
});
