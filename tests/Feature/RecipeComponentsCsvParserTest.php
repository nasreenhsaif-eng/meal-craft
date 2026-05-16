<?php

use App\Models\Ingredient;
use App\Support\RecipeComponentsCsvParser;

test('recipe components csv parser parses comma and pipe separated id amount pairs', function () {
    $sugar = Ingredient::factory()->create([
        'is_verified' => true,
        'calories' => 400,
        'protein' => 0,
        'carbs' => 100,
        'fat' => 0,
        'density' => 1,
    ]);
    $oil = Ingredient::factory()->create([
        'is_verified' => true,
        'calories' => 884,
        'density' => 0.92,
    ]);

    $rows = RecipeComponentsCsvParser::parseToComponentRows("{$sugar->id}:100,{$oil->id}:50g|{$sugar->id}:25 ml");

    expect($rows)->toHaveCount(3)
        ->and($rows[0])->toMatchArray([
            'ingredient_id' => $sugar->id,
            'amount_grams' => 100.0,
        ])
        ->and($rows[1]['ingredient_id'])->toBe($oil->id)
        ->and($rows[1]['amount_grams'])->toBe(50.0)
        ->and($rows[2]['ingredient_id'])->toBe($sugar->id)
        ->and($rows[2]['amount_grams'])->toBe(25.0);
});

test('recipe components csv parser resolves meal library style name segments', function () {
    $mango = Ingredient::factory()->create([
        'is_verified' => true,
        'name' => 'Mango',
        'calories' => 60,
        'density' => 1,
    ]);

    $rows = RecipeComponentsCsvParser::parseToComponentRows('Mango (2000g)');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['ingredient_id'])->toBe($mango->id)
        ->and($rows[0]['amount_grams'])->toBe(2000.0);
});

test('recipe components csv parser rejects invalid segments', function () {
    Ingredient::factory()->create(['is_verified' => true]);

    expect(fn () => RecipeComponentsCsvParser::parseToComponentRows('abc:100'))
        ->toThrow(InvalidArgumentException::class);
});

test('recipe components csv parser resolves comma-containing ingredient names with pipe separation', function () {
    $carrots = Ingredient::factory()->create([
        'is_verified' => true,
        'name' => 'Carrots, raw',
        'calories' => 40,
        'density' => 1,
    ]);
    $onion = Ingredient::factory()->create([
        'is_verified' => true,
        'name' => 'Onion',
        'calories' => 40,
        'density' => 1,
    ]);

    $rows = RecipeComponentsCsvParser::parseToComponentRows('Carrots, raw (100g) | Onion (50g)');

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['ingredient_id'])->toBe($carrots->id)
        ->and($rows[0]['amount_grams'])->toBe(100.0)
        ->and($rows[1]['ingredient_id'])->toBe($onion->id)
        ->and($rows[1]['amount_grams'])->toBe(50.0);
});

test('recipe components csv parser reports row context for bare ingredient names', function () {
    Ingredient::factory()->create(['is_verified' => true, 'name' => 'Carrots, raw']);

    try {
        RecipeComponentsCsvParser::parseToComponentRows('Carrots', 5, 'Veg Base');
        expect(false)->toBeTrue('expected exception');
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage())
            ->toContain('CSV row 5')
            ->toContain('Veg Base')
            ->toContain('Ingredient Name (Weightg)')
            ->toContain('Carrots');
    }
});
