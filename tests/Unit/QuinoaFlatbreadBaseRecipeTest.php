<?php

use App\Support\QuinoaFlatbreadBaseRecipe;

test('quinoa flatbread base is stored as one bread serving', function () {
    expect(QuinoaFlatbreadBaseRecipe::GRAMS_PER_BREAD)->toBe(97.5)
        ->and(QuinoaFlatbreadBaseRecipe::BATCH_SERVINGS)->toBe(10)
        ->and(QuinoaFlatbreadBaseRecipe::finishedWeightGrams())->toBe(97.5)
        ->and(QuinoaFlatbreadBaseRecipe::gramsForBreadCount(1))->toBe(97.5)
        ->and(QuinoaFlatbreadBaseRecipe::SERVING_COMPONENTS['Quinoa Flour'])->toBe(25.0)
        ->and(QuinoaFlatbreadBaseRecipe::batchComponents()['Quinoa Flour'])->toBe(250.0)
        ->and(QuinoaFlatbreadBaseRecipe::batchComponents()['Water (Filtered)'])->toBe(650.0)
        ->and(QuinoaFlatbreadBaseRecipe::recipeComponentsCsvCell())->toContain('Quinoa Flour (25g)')
        ->and(QuinoaFlatbreadBaseRecipe::nutritionPerServing(['calories' => 100.0])['calories'])->toBe(97.5);
});
