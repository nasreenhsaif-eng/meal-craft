<?php

use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\RecipeNutritionCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('recipe fat is round of unrounded sum not sum of rounded lines', function () {
    $a = Ingredient::factory()->create([
        'name' => 'Macro A',
        'calories' => 100,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 10.94,
        'density' => 1,
    ]);
    $b = Ingredient::factory()->create([
        'name' => 'Macro B',
        'calories' => 100,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 10.94,
        'density' => 1,
    ]);
    $c = Ingredient::factory()->create([
        'name' => 'Macro C',
        'calories' => 100,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 9.02,
        'density' => 1,
    ]);

    $nutrition = RecipeNutritionCalculator::fromRows([
        ['ingredient_id' => $a->id, 'amount_grams' => 100],
        ['ingredient_id' => $b->id, 'amount_grams' => 100],
        ['ingredient_id' => $c->id, 'amount_grams' => 100],
    ]);

    // 10.94 + 10.94 + 9.02 = 30.9 — must not inflate to 33 via intermediate rounding.
    expect($nutrition['fat'])->toBe(30.9)
        ->and($nutrition['calories'])->toBe(300.0);
});

test('meal nutrition applies single-stage rounding to display macros', function () {
    $ing = Ingredient::factory()->create([
        'name' => 'Lean Protein',
        'calories' => 120.4,
        'protein' => 22.26,
        'carbs' => 0.14,
        'fat' => 2.55,
        'density' => 1,
    ]);

    $meal = Meal::factory()->create(['name' => 'Rounding Plate', 'is_bulk' => false]);
    $meal->ingredients()->attach($ing->id, [
        'amount_grams' => 100,
        'amount' => 100,
        'unit' => 'g',
    ]);

    $nutrition = RecipeNutritionCalculator::fromMeal($meal->fresh(['ingredients']));

    expect($nutrition['calories'])->toBe(120.0)
        ->and($nutrition['protein'])->toBe(22.3)
        ->and($nutrition['carbs'])->toBe(0.1)
        ->and($nutrition['fat'])->toBe(2.6);
});
