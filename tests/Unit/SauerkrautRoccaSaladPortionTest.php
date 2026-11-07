<?php

use App\Services\NutrientDenseFermentedRecipeRefiner;
use App\Support\SideSaladPackaging;

test('sauerkraut rocca salad keeps leafy greens packable for a side container', function (): void {
    $definitions = (new ReflectionClass(NutrientDenseFermentedRecipeRefiner::class))
        ->getMethod('recipeDefinitions')
        ->invoke(new NutrientDenseFermentedRecipeRefiner);

    $ingredients = $definitions[NutrientDenseFermentedRecipeRefiner::SAUERKRAUT_ROCCA_SALAD_NAME]['ingredients'];

    expect((float) $ingredients['Rocca'])->toBe(80.0)
        ->and((float) $ingredients['Rocca'])->toBeLessThanOrEqual(SideSaladPackaging::maxFluffyLeafGrams())
        ->and((float) $ingredients['Avocado'])->toBe(25.0)
        ->and((float) $ingredients['Cherry Tomatoes'])->toBe(45.0)
        ->and((float) $ingredients['Sauerkraut (Base)'])->toBe(40.0)
        ->and((float) $ingredients['Almond whole'])->toBe(6.0)
        ->and((float) $ingredients['Cilantro Lime Dressing (Base)'])->toBe(15.0)
        ->and(SideSaladPackaging::violationMessages($ingredients))->toBe([]);
});
