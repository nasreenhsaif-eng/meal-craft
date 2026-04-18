<?php

use App\Enums\RecipeAmountUnit;
use App\Services\RecipeIngredientUnitConverter;

test('grams and kilograms ignore density', function () {
    expect(RecipeIngredientUnitConverter::toGrams(100, RecipeAmountUnit::Grams, 0.5))->toBe(100.0)
        ->and(RecipeIngredientUnitConverter::toGrams(2, RecipeAmountUnit::Kilograms, 0.5))->toBe(2000.0);
});

test('milliliters and liters multiply by density g per ml', function () {
    expect(RecipeIngredientUnitConverter::toGrams(200, RecipeAmountUnit::Milliliters, 0.5))->toBe(100.0)
        ->and(RecipeIngredientUnitConverter::toGrams(1, RecipeAmountUnit::Liters, 1.2))->toBe(1200.0);
});

test('cup uses 240 ml equivalent before density', function () {
    expect(RecipeIngredientUnitConverter::toGrams(1, RecipeAmountUnit::Cup, 1.0))->toBe(240.0)
        ->and(RecipeIngredientUnitConverter::toGrams(1, RecipeAmountUnit::Cup, 0.5))->toBe(120.0);
});

test('tablespoon and teaspoon use kitchen ml factors', function () {
    expect(RecipeIngredientUnitConverter::toGrams(1, RecipeAmountUnit::Tablespoon, 1.0))->toBe(15.0)
        ->and(RecipeIngredientUnitConverter::toGrams(1, RecipeAmountUnit::Teaspoon, 1.0))->toBe(5.0);
});
