<?php

use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\RecipeNutritionCalculator;
use App\Support\KitchenPortionRounding;
use App\Support\PureCookingFatNutrition;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('meal nutrition never undercounts fat from 30ml olive oil volume', function () {
    $oil = Ingredient::factory()->create([
        'name' => 'Olive Oil',
        'density' => 0.31,
        'fat' => 100,
        'calories' => 884,
        'protein' => 0,
        'carbs' => 0,
    ]);
    $chicken = Ingredient::factory()->create([
        'name' => 'Chicken Breast',
        'calories' => 120,
        'protein' => 22,
        'carbs' => 0,
        'fat' => 2.5,
        'density' => 1.0,
    ]);

    $meal = Meal::factory()->create(['name' => 'Oil Fat Floor Skillet', 'is_bulk' => false]);
    $meal->ingredients()->attach([
        $chicken->id => ['amount_grams' => 120, 'amount' => 120, 'unit' => 'g'],
        $oil->id => ['amount_grams' => 27.6, 'amount' => 30, 'unit' => 'ml'],
    ]);

    $nutrition = RecipeNutritionCalculator::fromMeal($meal->fresh(['ingredients']));

    expect($nutrition['fat'])->toBeGreaterThanOrEqual(27.0)
        ->and($nutrition['calories'])->toBeGreaterThanOrEqual(240.0);
});

test('30ml oil alone contributes approximately 27g fat and 240 calories', function () {
    $oil = Ingredient::factory()->create([
        'name' => 'Avocado Oil',
        'density' => 2.72,
        'fat' => 5,
        'calories' => 40,
        'protein' => 0,
        'carbs' => 0,
    ]);

    $nutrition = RecipeNutritionCalculator::fromRows([
        [
            'ingredient_id' => $oil->id,
            'amount' => 30,
            'unit' => 'ml',
        ],
    ]);

    expect($nutrition['fat'])->toEqualWithDelta(27.6, 0.2)
        ->and($nutrition['calories'])->toEqualWithDelta(244.0, 3.0);
});

test('pure fat floor lifts dish fat when stored oil macros are truncated', function () {
    $oil = Ingredient::factory()->create([
        'name' => 'Coconut Oil',
        'density' => 0.92,
        'fat' => 100,
        'calories' => 862,
        'protein' => 0,
        'carbs' => 0,
    ]);

    $nutrition = RecipeNutritionCalculator::fromRows([
        [
            'ingredient_id' => $oil->id,
            'amount_grams' => 27.6,
        ],
    ]);

    expect($nutrition['fat'])->toEqualWithDelta(27.6, 0.1)
        ->and($nutrition['calories'])->toBeGreaterThanOrEqual(230.0);
});

test('ghee is treated as pure cooking fat for snap and nutrition mass', function () {
    $ghee = Ingredient::factory()->create([
        'name' => 'Ghee',
        'density' => 1.0,
        'fat' => 99.5,
        'calories' => 876,
        'protein' => 0.3,
        'carbs' => 0,
    ]);

    expect(KitchenPortionRounding::isLiquidFatIngredient($ghee))->toBeTrue()
        ->and(PureCookingFatNutrition::isPureCookingFat($ghee))->toBeTrue();

    $nutrition = RecipeNutritionCalculator::fromRows([
        [
            'ingredient_id' => $ghee->id,
            'amount' => 15,
            'unit' => 'ml',
        ],
    ]);

    // 15ml × 0.91 ≈ 13.65g × 0.995 fat
    expect($nutrition['fat'])->toBeGreaterThan(13.0);
});
