<?php

use App\Models\Ingredient;
use App\Support\KitchenPortionRounding;

test('snapOilGrams rounds small pours up to five grams instead of zeroing', function (): void {
    expect(KitchenPortionRounding::snapOilGrams(1.3))->toBe(5.0)
        ->and(KitchenPortionRounding::snapOilGrams(3.9))->toBe(5.0)
        ->and(KitchenPortionRounding::snapOilGrams(0.0))->toBe(0.0);
});

test('snapOilGrams rounds to five gram steps', function (): void {
    expect(KitchenPortionRounding::snapOilGrams(6.0))->toBe(5.0)
        ->and(KitchenPortionRounding::snapOilGrams(8.0))->toBe(10.0)
        ->and(KitchenPortionRounding::snapOilGrams(5.0))->toBe(5.0);
});

test('snapNutGrams uses five gram minimum when non-zero', function (): void {
    expect(KitchenPortionRounding::snapNutGrams(7.0))->toBe(5.0)
        ->and(KitchenPortionRounding::snapNutGrams(8.0))->toBe(10.0);
});

test('snapCheeseGrams uses five gram steps', function (): void {
    expect(KitchenPortionRounding::snapCheeseGrams(17.0))->toBe(15.0)
        ->and(KitchenPortionRounding::snapCheeseGrams(22.0))->toBe(20.0);
});

test('isOilIngredient detects sesame and olive oil not dressing bases', function (): void {
    $olive = new Ingredient(['name' => 'Olive Oil (Extra Virgin)']);
    $sesame = new Ingredient(['name' => 'Sesame Oil']);
    $dressing = new Ingredient(['name' => 'Classic Lemon Garlic Dressing (Base)']);

    expect(KitchenPortionRounding::isOilIngredient($olive))->toBeTrue()
        ->and(KitchenPortionRounding::isOilIngredient($sesame))->toBeTrue()
        ->and(KitchenPortionRounding::isOilIngredient($dressing))->toBeFalse();
});

test('snapGramsForIngredient rounds aromatics sauces vegetables and spices for the kitchen', function (): void {
    expect(KitchenPortionRounding::snapGramsForIngredient(new Ingredient(['name' => 'Ginger (Raw)']), 2.42))->toBe(5.0)
        ->and(KitchenPortionRounding::snapGramsForIngredient(new Ingredient(['name' => 'Rice Vinegar']), 8.09))->toBe(10.0)
        ->and(KitchenPortionRounding::snapGramsForIngredient(new Ingredient(['name' => 'Tamarind Paste']), 8.09))->toBe(10.0)
        ->and(KitchenPortionRounding::snapGramsForIngredient(new Ingredient(['name' => 'Sesame Oil']), 7.52))->toBe(10.0)
        ->and(KitchenPortionRounding::snapGramsForIngredient(new Ingredient(['name' => 'Bok Choy']), 78.0))->toBe(80.0)
        ->and(KitchenPortionRounding::snapGramsForIngredient(new Ingredient(['name' => 'Garlicky Green Beans (Base)']), 85.0))->toBe(85.0)
        ->and(KitchenPortionRounding::snapGramsForIngredient(new Ingredient(['name' => 'Black Pepper']), 0.1))->toBe(1.0)
        ->and(KitchenPortionRounding::snapGramsForIngredient(new Ingredient(['name' => 'Chicken Breast']), 158.0))->toBe(160.0);
});
