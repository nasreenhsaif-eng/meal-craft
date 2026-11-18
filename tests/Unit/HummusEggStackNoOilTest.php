<?php

use App\Services\BalancedEggBreakfastRecipeRefiner;
use App\Services\NutrientDenseEggBreakfastRecipeRefiner;
use App\Support\MealLibraryRefinerOverrides;

test('hummus egg stack has no olive oil in egg breakfast refiners', function (): void {
    foreach ([
        NutrientDenseEggBreakfastRecipeRefiner::class,
        BalancedEggBreakfastRecipeRefiner::class,
    ] as $refinerClass) {
        $definitions = (new ReflectionClass($refinerClass))
            ->getMethod('recipeDefinitions')
            ->invoke(new $refinerClass);

        expect($definitions['Hummus Egg Stack']['ingredients'])->not->toHaveKey('Olive Oil')
            ->and($definitions['Hummus Egg Stack']['ingredients'])->not->toHaveKey('Olive Oil (Extra Virgin)')
            ->and($definitions['Hummus Egg Stack']['highlight'])->toContain('Soft-boiled eggs')
            ->and($definitions['Hummus Egg Stack']['highlight'])->not->toContain('sautéed');
    }
});

test('hummus egg stack library override has no olive oil', function (): void {
    $override = MealLibraryRefinerOverrides::all()['Hummus Egg Stack'] ?? null;

    expect($override)->not->toBeNull()
        ->and($override['ingredients'])->not->toHaveKey('Olive Oil')
        ->and($override['instructions'])->not->toContain('olive oil');
});
