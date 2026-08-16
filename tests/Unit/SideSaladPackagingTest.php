<?php

use App\Services\NutrientDenseFermentedRecipeRefiner;
use App\Services\SaladDressingMealRefiner;
use App\Support\MealLibraryRefinerOverrides;
use App\Support\SideSaladPackaging;

test('side salad packaging caps match 500ml salad and 20ml dressing cups', function (): void {
    expect(SideSaladPackaging::maxFluffyLeafGrams())->toBe(60.0)
        ->and(SideSaladPackaging::maxDenseLeafGrams())->toBe(70.0)
        ->and(SideSaladPackaging::maxCombinedLeafGrams())->toBe(70.0)
        ->and(SideSaladPackaging::maxDressingGrams())->toBe(20.0);
});

test('fermented side salads fit packaging caps', function (): void {
    $definitions = (new ReflectionClass(NutrientDenseFermentedRecipeRefiner::class))
        ->getMethod('recipeDefinitions')
        ->invoke(new NutrientDenseFermentedRecipeRefiner);

    foreach ([
        'Kimchi Purslane Side Salad',
        NutrientDenseFermentedRecipeRefiner::TAHINI_PURSLANE_PEPPER_SALAD_NAME,
        NutrientDenseFermentedRecipeRefiner::SAUERKRAUT_ROCCA_SALAD_NAME,
    ] as $name) {
        expect(SideSaladPackaging::violationMessages($definitions[$name]['ingredients']))->toBe([]);
    }
});

test('oversize leafy or dressing portions are flagged for packaging', function (): void {
    expect(SideSaladPackaging::violationMessages([
        'Rocca' => 85,
        'Classic Lemon Garlic Dressing (Base)' => 25,
    ]))->not->toBeEmpty();
});

test('key salad overrides keep dressing at or under 20g', function (): void {
    $overrides = MealLibraryRefinerOverrides::all();

    foreach ([
        'Thai Rainbow Peanut Salad',
        'Roasted Eggplant Rocca Salad',
        'Chicken Thai Mango Salad',
        'Vegan Harissa Roasted Cauliflower & Chickpea Salad w Tahini Dressing',
        'Mediterranean Crunch Salad',
    ] as $name) {
        expect($overrides)->toHaveKey($name);
        expect(SideSaladPackaging::violationMessages($overrides[$name]['ingredients']))->toBe([]);
    }
});

test('salad dressing refiner peanut and tahini cups stay at 20g', function (): void {
    $definitions = (new ReflectionClass(SaladDressingMealRefiner::class))
        ->getMethod('recipeDefinitions')
        ->invoke(new SaladDressingMealRefiner);

    $thai = array_merge(
        $definitions['Thai Rainbow Peanut Salad']['salad_ingredients'],
        $definitions['Thai Rainbow Peanut Salad']['dressing_ingredients'],
    );
    $harissa = array_merge(
        $definitions['Vegan Harissa Roasted Cauliflower & Chickpea Salad w Tahini Dressing']['salad_ingredients'],
        $definitions['Vegan Harissa Roasted Cauliflower & Chickpea Salad w Tahini Dressing']['dressing_ingredients'],
    );

    expect((float) $thai[SaladDressingMealRefiner::PEANUT_BUTTER_DRESSING])->toBe(20.0)
        ->and((float) $harissa['Lemon-Tahini Dressing (Base)'])->toBe(20.0)
        ->and(SideSaladPackaging::violationMessages($thai))->toBe([])
        ->and(SideSaladPackaging::violationMessages($harissa))->toBe([]);
});
