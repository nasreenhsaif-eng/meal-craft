<?php

use App\Support\GhormehSabziStewBaseRecipe;

test('ghormeh sabzi stew base declares a ten portion sabzi batch', function () {
    expect(GhormehSabziStewBaseRecipe::PER_SERVING_GRAMS)->toBe(189.0)
        ->and(GhormehSabziStewBaseRecipe::SERVINGS)->toBe(10)
        ->and(GhormehSabziStewBaseRecipe::finishedWeightGrams())->toBe(1890.0)
        ->and(GhormehSabziStewBaseRecipe::recipeComponentsCsvCell())->toContain('Cooked Cannellini Beans (Base) (600g)')
        ->and(GhormehSabziStewBaseRecipe::recipeComponentsCsvCell())->toContain('Parsley (150g)')
        ->and(GhormehSabziStewBaseRecipe::recipeComponentsCsvCell())->not->toContain('Beef Chuck Roast');
});
