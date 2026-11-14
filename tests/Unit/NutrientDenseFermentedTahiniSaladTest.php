<?php

use App\Services\NutrientDenseFermentedRecipeRefiner;

test('tahini purslane pepper salad uses sesame seeds without pumpkin seeds', function (): void {
    $definitions = (new ReflectionClass(NutrientDenseFermentedRecipeRefiner::class))
        ->getMethod('recipeDefinitions')
        ->invoke(new NutrientDenseFermentedRecipeRefiner);

    $name = NutrientDenseFermentedRecipeRefiner::TAHINI_PURSLANE_PEPPER_SALAD_NAME;
    $ingredients = $definitions[$name]['ingredients'];

    expect($ingredients)->toHaveKey('Sesame Seeds')
        ->and($ingredients)->not->toHaveKey('Pumpkin Seeds')
        ->and((float) $ingredients['Sesame Seeds'])->toBe(10.0)
        ->and($definitions[$name]['highlight'])
        ->toContain('sesame seeds')
        ->and($definitions[$name]['highlight'])
        ->not->toContain('pumpkin');
});
