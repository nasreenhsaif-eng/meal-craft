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
