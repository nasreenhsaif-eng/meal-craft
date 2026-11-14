<?php

use App\Models\Ingredient;
use App\Support\LiquidIngredientPresentation;

test('cooking oils display tablespoons converted from grams', function () {
    $oil = new Ingredient([
        'name' => 'Olive Oil (Extra Virgin)',
        'usda_food_category' => 'Fats',
        'density' => 0.92,
    ]);

    // 13.8 g ≈ 15 ml ≈ 1 tbsp at 0.92 g/ml
    expect(LiquidIngredientPresentation::isCookingOilIngredient($oil))->toBeTrue()
        ->and(LiquidIngredientPresentation::formatLine(13.8, $oil))->toBe('1 tbsp Olive Oil (Extra Virgin)')
        ->and(LiquidIngredientPresentation::formatLine(6.0, $oil))->toBe('½ tbsp Olive Oil (Extra Virgin)');
});

test('non-oil liquids still display milliliters', function () {
    $juice = new Ingredient([
        'name' => 'Lemon Juice',
        'usda_food_category' => 'Liquids',
        'density' => 1.0,
    ]);

    expect(LiquidIngredientPresentation::isCookingOilIngredient($juice))->toBeFalse()
        ->and(LiquidIngredientPresentation::formatLine(30.0, $juice))->toBe('30ml Lemon Juice');
});

test('liquid ingredients use density when converting grams to milliliters', function () {
    $oil = new Ingredient([
        'name' => 'Avocado Oil',
        'density' => 0.92,
    ]);

    expect(LiquidIngredientPresentation::formatLine(27.6, $oil))->toBe('2 tbsp Avocado Oil');
});

test('liquid milliliters snap to kitchen spoon steps', function () {
    expect(LiquidIngredientPresentation::snapKitchenMilliliters(7.52))->toBe(10.0)
        ->and(LiquidIngredientPresentation::snapKitchenMilliliters(8.09))->toBe(10.0)
        ->and(LiquidIngredientPresentation::snapKitchenMilliliters(2.4))->toBe(2.0);
});

test('tablespoons snap to half-spoon kitchen steps', function () {
    expect(LiquidIngredientPresentation::snapKitchenTablespoons(0.2))->toBe(0.5)
        ->and(LiquidIngredientPresentation::snapKitchenTablespoons(0.74))->toBe(0.5)
        ->and(LiquidIngredientPresentation::snapKitchenTablespoons(0.8))->toBe(1.0)
        ->and(LiquidIngredientPresentation::formatTablespoonLabel(1.5))->toBe('1½ tbsp');
});

test('liquid ingredients convert volume recipe units for display', function () {
    $juice = new Ingredient([
        'name' => 'Lemon Juice',
        'usda_food_category' => 'Liquids',
        'density' => 1.0,
    ]);
    $oil = new Ingredient([
        'name' => 'Olive Oil',
        'density' => 0.92,
    ]);

    expect(LiquidIngredientPresentation::formatLineFromAmountAndUnit(2.0, 'tbsp', $juice))
        ->toBe('30ml Lemon Juice')
        ->and(LiquidIngredientPresentation::formatLineFromAmountAndUnit(1.0, 'tbsp', $oil))
        ->toBe('1 tbsp Olive Oil');
});

test('non liquid ingredients are not classified as liquids', function () {
    $tomato = new Ingredient(['name' => 'Tomato (Raw)']);
    $peanutButter = new Ingredient(['name' => 'Peanut Butter']);

    expect(LiquidIngredientPresentation::isLiquidIngredient($tomato))->toBeFalse()
        ->and(LiquidIngredientPresentation::isLiquidIngredient($peanutButter))->toBeFalse();
});
