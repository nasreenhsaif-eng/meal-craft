<?php

use App\Support\OkraBeefCurryBaseRecipe;

test('okra beef curry base declares a ten portion stew batch', function () {
    expect(OkraBeefCurryBaseRecipe::PER_SERVING_GRAMS)->toBe(332.0)
        ->and(OkraBeefCurryBaseRecipe::SERVINGS)->toBe(10)
        ->and(OkraBeefCurryBaseRecipe::finishedWeightGrams())->toBe(3320.0)
        ->and(OkraBeefCurryBaseRecipe::recipeComponentsCsvCell())->toContain('Beef Chuck Roast (1500g)')
        ->and(OkraBeefCurryBaseRecipe::recipeComponentsCsvCell())->toContain('Homemade Tomato Sauce (Base) (600g)')
        ->and(OkraBeefCurryBaseRecipe::recipeComponentsCsvCell())->toContain('Okra (1200g)')
        ->and(OkraBeefCurryBaseRecipe::recipeComponentsCsvCell())->not->toContain('Marinara Sauce (Base)')
        ->and(OkraBeefCurryBaseRecipe::recipeComponentsCsvCell())->not->toContain('Tomato Sauce (600g)')
        ->and(OkraBeefCurryBaseRecipe::recipeComponentsCsvCell())->not->toContain('Steamed Basmati Rice (Base)');
});
