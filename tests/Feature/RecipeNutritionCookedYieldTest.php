<?php

use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\RecipeNutritionCalculator;
use App\Support\IngredientLibraryCategory;

test('meal nutrition scales dry french lentils against cooked macros via yield', function (): void {
    $lentils = Ingredient::factory()->create([
        'name' => 'French Lentils',
        'calories' => 116,
        'protein' => 9,
        'carbs' => 20,
        'fat' => 0.4,
        'usda_food_category' => 'Legumes',
    ]);

    $meal = Meal::factory()->create(['name' => 'Yield Lentil Bowl']);
    $meal->ingredients()->attach($lentils->id, [
        'amount_grams' => 60,
        'amount' => 60,
        'unit' => 'g',
    ]);

    $nutrition = RecipeNutritionCalculator::fromMeal($meal->fresh(['ingredients']));

    // 60g dry × 2.5 yield = 150g cooked-equivalent × 116 kcal/100g
    expect($nutrition['calories'])->toBe(174.0)
        ->and($nutrition['protein'])->toBe(13.5);
});

test('base recipe formulation uses finished_weight_grams as per-100g divisor', function (): void {
    $dryRice = Ingredient::factory()->create([
        'name' => 'Basmati White',
        'calories' => 356,
        'protein' => 7,
        'carbs' => 78,
        'fat' => 0.6,
        'usda_food_category' => 'Grains',
    ]);

    $base = Ingredient::factory()->create([
        'name' => 'Finished Yield Rice Base',
        'calories' => 0,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 0,
        'usda_food_category' => IngredientLibraryCategory::BaseIngredient,
        'finished_weight_grams' => 260,
    ]);
    $base->components()->attach($dryRice->id, ['amount_grams' => 100]);

    $per100 = RecipeNutritionCalculator::per100gNutritionForIngredient($base->fresh(['components']));

    // Batch from 100g dry rice = 356 kcal; finished cooked yield 260g → 356/260*100
    expect($per100['calories'])->toBe(136.9231);
});

test('meal using pre-cooked base pulls finished per-100g without dry yield rescale', function (): void {
    $base = Ingredient::factory()->create([
        'name' => 'Mint Coconut Chutney Dressing (Base)',
        'calories' => 148.61,
        'protein' => 1.02,
        'carbs' => 6.13,
        'fat' => 14.29,
        'usda_food_category' => IngredientLibraryCategory::BaseIngredient,
        'finished_weight_grams' => 97,
    ]);

    $meal = Meal::factory()->create(['name' => 'Chutney Yield Salad']);
    $meal->ingredients()->attach($base->id, [
        'amount_grams' => 20,
        'amount' => 20,
        'unit' => 'g',
    ]);

    $nutrition = RecipeNutritionCalculator::fromMeal($meal->fresh(['ingredients']));

    expect($nutrition['calories'])->toBe(29.72)
        ->and($nutrition['fat'])->toBe(2.86);
});
