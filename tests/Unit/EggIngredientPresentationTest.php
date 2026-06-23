<?php

use App\Support\EggIngredientPresentation;

test('formats whole egg counts with grams', function () {
    expect(EggIngredientPresentation::formatLine(100, '100'))
        ->toBe('2 large eggs (100g)');

    expect(EggIngredientPresentation::formatLine(55, '55'))
        ->toBe('1 large egg (55g)');

    expect(EggIngredientPresentation::formatLine(110, '110'))
        ->toBe('2 large eggs (110g)');
});

test('formats half egg for medium per-serving amounts', function () {
    expect(EggIngredientPresentation::formatLine(25, '25'))
        ->toBe('1/2 large egg (25g)');
});

test('falls back to grams for very small baking amounts', function () {
    expect(EggIngredientPresentation::formatLine(12.4, '12.4'))
        ->toBe('12.4g Egg');
});
