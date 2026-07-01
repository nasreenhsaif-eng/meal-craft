<?php

use App\Models\Ingredient;
use App\Support\KitchenPortionRounding;

test('snapOilGrams returns zero below kitchen threshold', function (): void {
    expect(KitchenPortionRounding::snapOilGrams(1.3))->toBe(0.0)
        ->and(KitchenPortionRounding::snapOilGrams(3.9))->toBe(0.0);
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

test('isOilIngredient detects olive oil not dressing bases', function (): void {
    $oil = new Ingredient(['name' => 'Olive Oil (Extra Virgin)']);
    $dressing = new Ingredient(['name' => 'Classic Lemon Garlic Dressing (Base)']);

    expect(KitchenPortionRounding::isOilIngredient($oil))->toBeTrue()
        ->and(KitchenPortionRounding::isOilIngredient($dressing))->toBeFalse();
});
