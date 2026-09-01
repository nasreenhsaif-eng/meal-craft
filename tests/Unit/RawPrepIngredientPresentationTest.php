<?php

use App\Models\Ingredient;
use App\Support\RawPrepIngredientPresentation;

test('formats salmon with raw before cooking suffix', function () {
    $salmon = Ingredient::factory()->make(['name' => 'Salmon']);

    expect(RawPrepIngredientPresentation::formatLine(171.16, '171.16', $salmon))
        ->toBe('171.16g Salmon (raw, before cooking)');
});

test('normalizes salmon raw ingredient name in display line', function () {
    $salmon = Ingredient::factory()->make(['name' => 'Salmon (Raw)']);

    expect(RawPrepIngredientPresentation::formatLine(125, '125', $salmon))
        ->toBe('125g Salmon (raw, before cooking)');
});

test('formats chicken breast with raw before cooking suffix', function () {
    $chicken = Ingredient::factory()->make(['name' => 'Chicken Breast']);

    expect(RawPrepIngredientPresentation::formatLine(120, '120', $chicken))
        ->toBe('120g Chicken Breast (raw, before cooking)');
});

test('formats canned sardines with drained canned suffix', function () {
    $sardines = Ingredient::factory()->make([
        'name' => 'Sardines (Canned)',
        'usda_food_category' => 'Proteins',
        'calories' => 208,
    ]);

    expect(RawPrepIngredientPresentation::isCannedPrepIngredient($sardines))->toBeTrue()
        ->and(RawPrepIngredientPresentation::formatCannedLine(100, '100', $sardines))
        ->toBe('100g Sardines (drained canned)');
});

test('formats hamour and shrimp with salmon-style display names', function () {
    $hamour = Ingredient::factory()->make([
        'name' => 'Hamour Fillet',
        'usda_food_category' => 'Proteins',
        'calories' => 92,
    ]);
    $shrimp = Ingredient::factory()->make([
        'name' => 'Shrimp (Raw)',
        'usda_food_category' => 'Proteins',
        'calories' => 85,
    ]);

    expect(RawPrepIngredientPresentation::formatLine(165, '165', $hamour))
        ->toBe('165g Hamour (raw, before cooking)')
        ->and(RawPrepIngredientPresentation::formatLine(165, '165', $shrimp))
        ->toBe('165g Shrimp (raw, before cooking)');
});

test('formats pre-cooked rice bases as cooked plated portions', function () {
    $rice = Ingredient::factory()->make([
        'name' => 'Steamed Basmati Rice (Base)',
        'usda_food_category' => 'Base Ingredient',
        'calories' => 118,
    ]);

    expect(RawPrepIngredientPresentation::formatBaseLine(75, '75', $rice))
        ->toBe('75g Steamed Basmati Rice (cooked plated portion)');
});

test('exposes a prep-weight legend for every recipe', function () {
    expect(RawPrepIngredientPresentation::ingredientsPrepNote())
        ->toContain('raw before cooking')
        ->toContain('canned fish is drained weight')
        ->toContain('dry weight')
        ->toContain('(Base) items are cooked plated portions');
});
