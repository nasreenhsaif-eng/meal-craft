<?php

use App\Services\NutrientDenseFermentedRecipeRefiner;

test('sauerkraut rocca salad keeps leafy greens packable for a side container', function (): void {
    $definitions = (new ReflectionClass(NutrientDenseFermentedRecipeRefiner::class))
        ->getMethod('recipeDefinitions')
        ->invoke(new NutrientDenseFermentedRecipeRefiner);

    $ingredients = $definitions[NutrientDenseFermentedRecipeRefiner::SAUERKRAUT_ROCCA_SALAD_NAME]['ingredients'];

    expect((float) $ingredients['Rocca'])->toBe(60.0)
        ->and((float) $ingredients['Rocca'])->toBeLessThanOrEqual(70.0)
        ->and((float) $ingredients['Avocado'])->toBe(35.0)
        ->and((float) $ingredients['Cherry Tomatoes'])->toBe(50.0);
});
