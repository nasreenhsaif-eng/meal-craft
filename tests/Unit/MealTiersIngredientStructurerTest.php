<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Support\MealTiersIngredientStructurer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('chicken mains scale primary protein grams to the calorie tab', function () {
    $chicken = Ingredient::factory()->create([
        'name' => 'Chicken Breast',
        'calories' => 165,
        'protein' => 31,
        'carbs' => 0,
        'fat' => 3.6,
        'usda_food_category' => 'Proteins',
    ]);
    $rice = Ingredient::factory()->create([
        'name' => 'Brown Rice',
        'calories' => 110,
        'protein' => 2.5,
        'carbs' => 23,
        'fat' => 0.9,
        'usda_food_category' => 'Grains',
    ]);
    $oil = Ingredient::factory()->create([
        'name' => 'Olive Oil',
        'calories' => 884,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 100,
        'usda_food_category' => 'Fats',
    ]);
    $salt = Ingredient::factory()->create([
        'name' => 'Sea Salt',
        'calories' => 0,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 0,
        'usda_food_category' => 'Spices and Herbs',
    ]);

    $meal = Meal::factory()->create([
        'name' => 'Turmeric Chicken Bowl',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
    ]);
    $meal->ingredients()->attach([
        $chicken->id => ['amount_grams' => 150, 'amount' => 150, 'unit' => 'g'],
        $rice->id => ['amount_grams' => 100, 'amount' => 100, 'unit' => 'g'],
        $oil->id => ['amount_grams' => 14, 'amount' => 14, 'unit' => 'g'],
        $salt->id => ['amount_grams' => 2, 'amount' => 2, 'unit' => 'g'],
    ]);
    $meal->load('ingredients');

    $grams = MealTiersIngredientStructurer::gramsByTier($meal);

    expect(array_keys($grams))->toBe([400, 500, 550, 600, 700, 800])
        ->and($grams[400][$chicken->id])->toBe(135.0)
        ->and($grams[500][$chicken->id])->toBe(175.0)
        ->and($grams[800][$chicken->id])->toBe(300.0)
        ->and($grams[400][$oil->id])->toBe(5.0)
        ->and($grams[500][$oil->id])->toBe(5.0)
        ->and($grams[800][$oil->id])->toBe(5.0);
});
