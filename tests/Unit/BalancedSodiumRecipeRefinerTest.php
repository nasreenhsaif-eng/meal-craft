<?php

use App\Services\BalancedSodiumRecipeRefiner;

test('sodium refiner keeps cooked quinoa as a cookable base instead of dry grain', function (): void {
    $adjusted = app(BalancedSodiumRecipeRefiner::class)->adjustIngredientGrams([
        'Cooked Quinoa (Base)' => 84.0,
        'Chicken Breast' => 150.0,
        'Vegetable Broth (Base)' => 50.0,
        'Water (Filtered)' => 150.0,
    ]);

    expect($adjusted)->toHaveKey('Cooked Quinoa (Base)')
        ->and($adjusted)->not->toHaveKey('Quinoa (White)')
        ->and($adjusted['Cooked Quinoa (Base)'])->toBe(75.6)
        ->and($adjusted['Vegetable Broth (Base)'])->toBe(25.0)
        ->and($adjusted['Water (Filtered)'])->toBe(175.0);
});

test('sodium refiner keeps cooked couscous instead of swapping to dry couscous', function (): void {
    $adjusted = app(BalancedSodiumRecipeRefiner::class)->adjustIngredientGrams([
        'Cooked Couscous (Base)' => 90.0,
        'Beef Ground Lean' => 130.0,
    ]);

    expect($adjusted)->toHaveKey('Cooked Couscous (Base)')
        ->and($adjusted)->not->toHaveKey('Couscous')
        ->and($adjusted['Cooked Couscous (Base)'])->toBe(81.0);
});
