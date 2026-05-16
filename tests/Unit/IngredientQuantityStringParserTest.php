<?php

use App\Support\IngredientQuantityStringParser;

test('parses colon segments with optional g suffix and pipe separation', function () {
    $parsed = IngredientQuantityStringParser::parse('Chicken Thighs:825g | Rice:200');
    expect($parsed)->toHaveCount(2)
        ->and($parsed[0]['name'])->toBe('Chicken Thighs')
        ->and($parsed[0]['amount'])->toBe(825.0)
        ->and($parsed[0]['unit'])->toBe('g')
        ->and($parsed[1]['name'])->toBe('Rice')
        ->and($parsed[1]['amount'])->toBe(200.0)
        ->and($parsed[1]['unit'])->toBe('g');
});

test('parses space form when unit suffix is present', function () {
    $parsed = IngredientQuantityStringParser::parse('Olive oil 15ml');
    expect($parsed)->toHaveCount(1)
        ->and($parsed[0]['name'])->toBe('Olive oil')
        ->and($parsed[0]['amount'])->toBe(15.0)
        ->and($parsed[0]['unit'])->toBe('ml');
});

test('parses salmon parenthesis segment without undefined capture groups', function () {
    $parsed = IngredientQuantityStringParser::parse('Salmon (115g)');

    expect($parsed)->toHaveCount(1)
        ->and($parsed[0]['name'])->toBe('Salmon')
        ->and($parsed[0]['amount'])->toBe(115.0)
        ->and($parsed[0]['unit'])->toBe('g');
});

test('parses parenthesis form with amount and unit inside parens', function () {
    $parsed = IngredientQuantityStringParser::parse('Chicken Broth (710ml) | Almonds (100g)');
    expect($parsed)->toHaveCount(2)
        ->and($parsed[0]['name'])->toBe('Chicken Broth')
        ->and($parsed[0]['amount'])->toBe(710.0)
        ->and($parsed[0]['unit'])->toBe('ml')
        ->and($parsed[1]['name'])->toBe('Almonds')
        ->and($parsed[1]['amount'])->toBe(100.0)
        ->and($parsed[1]['unit'])->toBe('g');
});

test('parses parenthesis form without space before opening parenthesis', function () {
    $parsed = IngredientQuantityStringParser::parse('Chicken Broth(710ml)');
    expect($parsed)->toHaveCount(1)
        ->and($parsed[0]['name'])->toBe('Chicken Broth')
        ->and($parsed[0]['amount'])->toBe(710.0)
        ->and($parsed[0]['unit'])->toBe('ml');
});

test('splits segments on newlines as well as pipes', function () {
    $parsed = IngredientQuantityStringParser::parse("Rice (200g)\nOlive oil 15ml");
    expect($parsed)->toHaveCount(2)
        ->and($parsed[0]['name'])->toBe('Rice')
        ->and($parsed[1]['name'])->toBe('Olive oil');
});

test('parses decimal amounts in parenthesis form', function () {
    $parsed = IngredientQuantityStringParser::parse('Cinnamon (2.5g)');
    expect($parsed)->toHaveCount(1)
        ->and($parsed[0]['amount'])->toBe(2.5)
        ->and($parsed[0]['unit'])->toBe('g');
});

test('normalizes liter aliases to ltr', function () {
    expect(IngredientQuantityStringParser::normalizeUnit('L'))->toBe('ltr')
        ->and(IngredientQuantityStringParser::normalizeUnit('liter'))->toBe('ltr');
});

test('splits escaped backslash pipe delimiters and trims segment whitespace', function () {
    $parsed = IngredientQuantityStringParser::parse('Salmon (120g) \\|  Quinoa (100g) ');

    expect($parsed)->toHaveCount(2)
        ->and($parsed[0]['name'])->toBe('Salmon')
        ->and($parsed[0]['amount'])->toBe(120.0)
        ->and($parsed[1]['name'])->toBe('Quinoa')
        ->and($parsed[1]['amount'])->toBe(100.0);
});

test('splits unicode pipe variant delimiters', function () {
    $parsed = IngredientQuantityStringParser::parse('Rice (200g) │ Olive oil 15ml');

    expect($parsed)->toHaveCount(2)
        ->and($parsed[0]['name'])->toBe('Rice')
        ->and($parsed[1]['name'])->toBe('Olive oil');
});

test('sanitize cell normalizes escaped pipes before splitting', function () {
    expect(IngredientQuantityStringParser::sanitizeCell('A \\| B'))->toBe('A | B');
});

test('splits comma separated parenthesis form ingredients from spreadsheet cells', function () {
    $cell = 'Salmon (115g), Potato (80g), Leeks (60g), Olive Oil (Extra Virgin) (15g), Capers (4g)';

    $parsed = IngredientQuantityStringParser::parse($cell);

    expect($parsed)->toHaveCount(5)
        ->and($parsed[0]['name'])->toBe('Salmon')
        ->and($parsed[0]['amount'])->toBe(115.0)
        ->and($parsed[2]['name'])->toBe('Leeks')
        ->and($parsed[3]['name'])->toBe('Olive Oil (Extra Virgin)')
        ->and($parsed[3]['amount'])->toBe(15.0)
        ->and($parsed[4]['name'])->toBe('Capers');
});

test('ingredient names from cell expands comma separated list without library match', function () {
    $cell = 'Salmon (115g), Potato (80g), Leeks (60g)';

    $names = IngredientQuantityStringParser::ingredientNamesFromCell($cell);

    expect($names)->toBe(['Salmon', 'Potato', 'Leeks']);
});

test('cell looks like multi ingredient list when multiple weight groups are present without comma', function () {
    $cell = "Salmon (115g)\tPotato (80g)";

    expect(IngredientQuantityStringParser::cellLooksLikeCommaSeparatedIngredientList($cell))->toBeTrue()
        ->and(IngredientQuantityStringParser::splitSegments($cell))->toHaveCount(2);
});

test('parses parenthesis amounts with Ng unit typo as grams', function () {
    $parsed = IngredientQuantityStringParser::parse('Black Pepper (1Ng)');

    expect($parsed)->toHaveCount(1)
        ->and($parsed[0]['amount'])->toBe(1.0)
        ->and($parsed[0]['unit'])->toBe('g');
});

test('parses full spreadsheet style comma separated ingredient row', function () {
    $cell = 'Salmon (115g), Potato (80g), Leeks (60g), Olive Oil (Extra Virgin) (15g), Vegetable Broth (Base) (15g), Lemon Juice (15g), Capers (4g), Dill (Fresh) (3g), Preserved Lemon (4g), Garlic (Raw) (2g), Rosemary (Fresh) (1g), Black Pepper (1g), Sea Salt';

    $parsed = IngredientQuantityStringParser::parse($cell);

    expect($parsed)->toHaveCount(12)
        ->and(collect($parsed)->pluck('name')->all())->toBe([
            'Salmon',
            'Potato',
            'Leeks',
            'Olive Oil (Extra Virgin)',
            'Vegetable Broth (Base)',
            'Lemon Juice',
            'Capers',
            'Dill (Fresh)',
            'Preserved Lemon',
            'Garlic (Raw)',
            'Rosemary (Fresh)',
            'Black Pepper',
        ]);
});
