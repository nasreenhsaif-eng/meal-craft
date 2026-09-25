<?php

use App\Support\RosemaryGarlicChickenBaseRecipe;

test('the rosemary garlic chicken base is a 1 kg raw chicken batch', function () {
    expect(RosemaryGarlicChickenBaseRecipe::COMPONENTS)->toBe([
        'Chicken Breast' => 1000.0,
        'Olive Oil (Extra Virgin)' => 30.0,
        'Lemon Juice' => 67.0,
        'Dijon Mustard' => 56.0,
        'Garlic (Raw)' => 44.0,
        'Rosemary (Fresh)' => 28.0,
        'Sea Salt' => 11.0,
    ])
        ->and(RosemaryGarlicChickenBaseRecipe::cookedGramsForRawChicken(155))->toBe(114.4)
        ->and(RosemaryGarlicChickenBaseRecipe::recipeComponentsCsvCell())->toContain('Chicken Breast (1000g)')
        ->and(RosemaryGarlicChickenBaseRecipe::recipeComponentsCsvCell())->toContain('Olive Oil (Extra Virgin) (30g)')
        ->and(RosemaryGarlicChickenBaseRecipe::recipeComponentsCsvCell())->not->toContain('Black Pepper');
});
