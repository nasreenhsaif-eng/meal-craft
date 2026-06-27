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
