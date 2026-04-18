<?php

use App\Models\Ingredient;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

test('ingredient highlights detect high-density nutrients', function () {
    $ingredient = Ingredient::query()->create([
        'name' => 'Spinach',
        'calories' => 23,
        'protein' => 2.9,
        'carbs' => 3.6,
        'fat' => 0.4,
        'b9_folate' => 194,
        'b12' => 0,
        'magnesium' => 79,
        'iron' => 2.7,
        'micronutrients' => [
            'zinc' => 0.5,
        ],
        'is_verified' => true,
    ]);

    expect($ingredient->highlights)->toBeArray()
        ->and($ingredient->highlights['folate'])->toBeTrue()
        ->and($ingredient->highlights['b12'])->toBeFalse()
        ->and($ingredient->highlights['magnesium'])->toBeFalse()
        ->and($ingredient->highlights['iron'])->toBeFalse()
        ->and($ingredient->highlights['zinc'])->toBeFalse();
});

test('ingredient is a powerfood when it has at least two highlights', function () {
    $ingredient = Ingredient::query()->create([
        'name' => 'Oysters',
        'calories' => 68,
        'protein' => 7,
        'carbs' => 4,
        'fat' => 2,
        'b9_folate' => 120,
        'b12' => 16,
        'iron' => 6,
        'magnesium' => 110,
        'micronutrients' => [
            'zinc' => 40,
        ],
        'is_verified' => true,
    ]);

    $highlights = collect($ingredient->highlights)->filter()->count();

    expect($highlights)->toBeGreaterThanOrEqual(2);
});

