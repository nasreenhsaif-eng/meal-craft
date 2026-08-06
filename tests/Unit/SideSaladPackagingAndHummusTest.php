<?php

use App\Services\BalancedEggBreakfastRecipeRefiner;
use App\Services\SaladDressingMealRefiner;
use App\Support\SideSaladPackaging;

test('side salad packaging caps match 500ml salad and 20ml dressing cups', function (): void {
    expect(SideSaladPackaging::maxFluffyLeafGrams())->toBe(60.0)
        ->and(SideSaladPackaging::maxDenseLeafGrams())->toBe(70.0)
        ->and(SideSaladPackaging::maxCombinedLeafGrams())->toBe(70.0)
        ->and(SideSaladPackaging::maxDressingGrams())->toBe(20.0);
});

test('oversize leafy or dressing portions are flagged for packaging', function (): void {
    expect(SideSaladPackaging::violationMessages([
        'Rocca' => 85,
        'Classic Lemon Garlic Dressing (Base)' => 25,
    ]))->not->toBeEmpty();
});

test('hummus egg stack has no olive oil for soft-boiled preparation', function (): void {
    $definitions = (new ReflectionClass(BalancedEggBreakfastRecipeRefiner::class))
        ->getMethod('recipeDefinitions')
        ->invoke(new BalancedEggBreakfastRecipeRefiner);

    expect($definitions['Hummus Egg Stack']['ingredients'])->not->toHaveKey('Olive Oil')
        ->and($definitions['Hummus Egg Stack']['highlight'])->toContain('Soft-boiled eggs')
        ->and($definitions['Hummus Egg Stack']['highlight'])->not->toContain('sautéed');
});

test('salad dressing cups stay at or under 20g in key salads', function (): void {
    $definitions = (new ReflectionClass(SaladDressingMealRefiner::class))
        ->getMethod('recipeDefinitions')
        ->invoke(new SaladDressingMealRefiner);

    foreach ([
        'Thai Rainbow Peanut Salad',
        'Chicken Thai Mango Salad',
        'Vegan Harissa Roasted Cauliflower & Chickpea Salad w Tahini Dressing',
        'Classic Garden Salad',
        'Mediterranean Crunch Salad',
        'Tomato Parsely Salad w Sumac Za’ater Dressing',
    ] as $name) {
        $map = array_merge(
            $definitions[$name]['salad_ingredients'],
            $definitions[$name]['dressing_ingredients'],
        );

        expect(SideSaladPackaging::violationMessages($map))->toBe([]);
    }
});
