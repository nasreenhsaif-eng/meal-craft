<?php

use App\Support\HomemadeTomatoSauceBaseRecipe;

test('homemade tomato sauce base declares a ten portion stew sauce batch', function () {
    expect(HomemadeTomatoSauceBaseRecipe::PER_SERVING_GRAMS)->toBe(60.0)
        ->and(HomemadeTomatoSauceBaseRecipe::SERVINGS)->toBe(10)
        ->and(HomemadeTomatoSauceBaseRecipe::finishedWeightGrams())->toBe(600.0)
        ->and(HomemadeTomatoSauceBaseRecipe::recipeComponentsCsvCell())->toContain('Tomato (Raw) (500g)')
        ->and(HomemadeTomatoSauceBaseRecipe::recipeComponentsCsvCell())->toContain('Tomato Paste (40g)')
        ->and(HomemadeTomatoSauceBaseRecipe::recipeComponentsCsvCell())->not->toContain('Marinara Sauce (Base)')
        ->and(HomemadeTomatoSauceBaseRecipe::recipeComponentsCsvCell())->not->toContain('Carrots');
});
