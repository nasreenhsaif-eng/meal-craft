<?php

use App\Models\Ingredient;
use App\Support\LiquidIngredientPresentation;

test('liquid ingredients display milliliters converted from grams', function () {
    $oil = new Ingredient([
        'name' => 'Olive Oil (Extra Virgin)',
        'usda_food_category' => 'Fats',
        'density' => 1.0,
    ]);

    expect(LiquidIngredientPresentation::isLiquidIngredient($oil))->toBeTrue()
        ->and(LiquidIngredientPresentation::formatLine(6.0, $oil))->toBe('5ml Olive Oil (Extra Virgin)');
});

test('liquid ingredients use density when converting grams to milliliters', function () {
    $oil = new Ingredient([
        'name' => 'Avocado Oil',
        'density' => 0.92,
    ]);

    expect(LiquidIngredientPresentation::formatLine(9.2, $oil))->toBe('10ml Avocado Oil');
});

test('liquid milliliters snap to kitchen spoon steps', function () {
    expect(LiquidIngredientPresentation::snapKitchenMilliliters(7.52))->toBe(10.0)
        ->and(LiquidIngredientPresentation::snapKitchenMilliliters(8.09))->toBe(10.0)
        ->and(LiquidIngredientPresentation::snapKitchenMilliliters(2.4))->toBe(2.0);
});

test('liquid ingredients convert volume recipe units to milliliters', function () {
    $juice = new Ingredient([
        'name' => 'Lemon Juice',
        'usda_food_category' => 'Liquids',
        'density' => 1.0,
    ]);

    expect(LiquidIngredientPresentation::formatLineFromAmountAndUnit(2.0, 'tbsp', $juice))
        ->toBe('30ml Lemon Juice');
});

test('non liquid ingredients are not classified as liquids', function () {
    $tomato = new Ingredient(['name' => 'Tomato (Raw)']);
    $peanutButter = new Ingredient(['name' => 'Peanut Butter']);

    expect(LiquidIngredientPresentation::isLiquidIngredient($tomato))->toBeFalse()
        ->and(LiquidIngredientPresentation::isLiquidIngredient($peanutButter))->toBeFalse();
});
