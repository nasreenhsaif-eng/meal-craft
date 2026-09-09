<?php

use App\Support\EggIngredientPresentation;

test('formats whole egg counts as raw egg pieces', function () {
    expect(EggIngredientPresentation::formatLine(100, '100'))
        ->toBe('2 eggs raw');

    expect(EggIngredientPresentation::formatLine(55, '55'))
        ->toBe('1 egg raw');

    expect(EggIngredientPresentation::formatLine(110, '110'))
        ->toBe('2 eggs raw');
});

test('formats half egg for medium per-serving amounts', function () {
    expect(EggIngredientPresentation::formatLine(25, '25'))
        ->toBe('1/2 egg raw');
});

test('falls back to grams for very small baking amounts', function () {
    expect(EggIngredientPresentation::formatLine(12.4, '12.4'))
        ->toBe('1/4 egg raw');

    expect(EggIngredientPresentation::formatLine(5, '5'))
        ->toBe('5g Egg raw');
});
