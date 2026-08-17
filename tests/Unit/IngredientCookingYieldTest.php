<?php

use App\Models\Ingredient;
use App\Support\ChickenBreastYield;
use App\Support\IngredientCookingYield;
use App\Support\IngredientLibraryCategory;

test('chicken breast nutrition mass stays raw while cooked yield shrinks plated weight', function (): void {
    $chicken = new Ingredient([
        'name' => ChickenBreastYield::RAW_INGREDIENT_NAME,
        'calories' => 120,
        'usda_food_category' => 'Proteins',
    ]);

    expect(IngredientCookingYield::nutritionMassGrams($chicken, 100))->toBe(100.0)
        ->and(IngredientCookingYield::estimatedCookedGrams($chicken, 100))->toBe(75.0)
        ->and(IngredientCookingYield::amountStateLabel($chicken))->toBe('raw, before cooking');
});

test('dry french lentils with cooked macros scale nutrition mass by yield', function (): void {
    $lentils = new Ingredient([
        'name' => 'French Lentils',
        'calories' => 116,
        'usda_food_category' => 'Legumes',
    ]);

    expect(IngredientCookingYield::nutritionMassGrams($lentils, 60))->toBe(150.0)
        ->and(IngredientCookingYield::estimatedCookedGrams($lentils, 60))->toBe(150.0)
        ->and(IngredientCookingYield::amountStateLabel($lentils))->toBe('dry weight');
});

test('dry basmati with dry macros keeps nutrition mass and expands plated yield', function (): void {
    $rice = new Ingredient([
        'name' => 'Basmati White',
        'calories' => 356,
        'usda_food_category' => 'Grains',
    ]);

    expect(IngredientCookingYield::nutritionMassGrams($rice, 50))->toBe(50.0)
        ->and(IngredientCookingYield::estimatedCookedGrams($rice, 50))->toBe(130.0)
        ->and(IngredientCookingYield::amountStateLabel($rice))->toBe('dry weight');
});

test('prepared base ingredients use finished grams without further yield conversion', function (): void {
    $base = new Ingredient([
        'name' => 'Steamed Basmati Rice (Base)',
        'calories' => 118,
        'usda_food_category' => IngredientLibraryCategory::BaseIngredient,
    ]);

    expect(IngredientCookingYield::isFinishedBaseComponent($base))->toBeTrue()
        ->and(IngredientCookingYield::nutritionMassGrams($base, 90))->toBe(90.0)
        ->and(IngredientCookingYield::estimatedCookedGrams($base, 90))->toBe(90.0)
        ->and(IngredientCookingYield::amountStateLabel($base))->toBe('pre-cooked base');
});

test('meal yield summary combines raw shrink and base finished grams', function (): void {
    $chicken = new Ingredient([
        'name' => ChickenBreastYield::RAW_INGREDIENT_NAME,
        'calories' => 120,
        'usda_food_category' => 'Proteins',
    ]);
    $base = new Ingredient([
        'name' => 'Yield Note Rice Base',
        'calories' => 118,
        'usda_food_category' => IngredientLibraryCategory::BaseIngredient,
    ]);

    $chicken->setRelation('pivot', (object) ['amount_grams' => 100]);
    $base->setRelation('pivot', (object) ['amount_grams' => 80]);

    $summary = IngredientCookingYield::mealYieldSummary([$chicken, $base]);

    expect($summary['estimated_cooked_grams'])->toBe(155.0)
        ->and($summary['raw_or_dry_input_grams'])->toBe(100.0)
        ->and($summary['finished_base_grams'])->toBe(80.0)
        ->and($summary['note'])->toContain('155');
});
