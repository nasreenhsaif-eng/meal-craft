<?php

use App\Support\ShawarmaSpiceBlendBaseRecipe;

test('shawarma spice blend base declares a from-scratch levant spice batch', function () {
    expect(ShawarmaSpiceBlendBaseRecipe::finishedWeightGrams())->toBe(48.0)
        ->and(ShawarmaSpiceBlendBaseRecipe::recipeComponentsCsvCell())->toContain('cumin powder (12g)')
        ->and(ShawarmaSpiceBlendBaseRecipe::recipeComponentsCsvCell())->toContain('coriander powder (12g)')
        ->and(ShawarmaSpiceBlendBaseRecipe::recipeComponentsCsvCell())->toContain('Paprika (8g)')
        ->and(ShawarmaSpiceBlendBaseRecipe::recipeComponentsCsvCell())->not->toContain('Beef Chuck Roast');
});
