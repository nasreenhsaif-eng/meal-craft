<?php

use App\Support\BeefShawarmaBaseRecipe;

test('beef shawarma base declares a ten portion slow-roasted chuck batch', function () {
    expect(BeefShawarmaBaseRecipe::PER_SERVING_GRAMS)->toBe(112.0)
        ->and(BeefShawarmaBaseRecipe::SERVINGS)->toBe(10)
        ->and(BeefShawarmaBaseRecipe::finishedWeightGrams())->toBe(1120.0)
        ->and(BeefShawarmaBaseRecipe::recipeComponentsCsvCell())->toContain('Beef Chuck Roast (1500g)')
        ->and(BeefShawarmaBaseRecipe::recipeComponentsCsvCell())->toContain('Shawarma Spice Blend (Base) (30g)')
        ->and(BeefShawarmaBaseRecipe::recipeComponentsCsvCell())->not->toContain('Creamy Cumin Hummus (Base)');
});
