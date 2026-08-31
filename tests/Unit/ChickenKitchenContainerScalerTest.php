<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Support\ChickenKitchenContainerScaler;
use App\Support\ChickenKitchenPlateTargets;
use App\Support\MealTiersIngredientStructurer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('chicken plates use the shared cooked protein curve and half tablespoon prep oil', function () {
    $chicken = Ingredient::factory()->create([
        'name' => 'Tandoori Chicken (Base)',
        'calories' => 173,
        'protein' => 21,
        'carbs' => 4,
        'fat' => 8,
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
    $spinach = Ingredient::factory()->create([
        'name' => 'Spinach (Fresh)',
        'calories' => 23,
        'protein' => 2.9,
        'carbs' => 3.6,
        'fat' => 0.4,
        'usda_food_category' => 'Vegetables',
    ]);
    $oil = Ingredient::factory()->create([
        'name' => 'Olive Oil (Extra Virgin)',
        'calories' => 884,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 100,
        'usda_food_category' => 'Fats',
    ]);

    $meal = Meal::factory()->create([
        'name' => 'Tandoori Chicken Plate',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
    ]);
    $meal->ingredients()->attach([
        $chicken->id => ['amount_grams' => 150, 'amount' => 150, 'unit' => 'g'],
        $rice->id => ['amount_grams' => 80, 'amount' => 80, 'unit' => 'g'],
        $spinach->id => ['amount_grams' => 60, 'amount' => 60, 'unit' => 'g'],
        $oil->id => ['amount_grams' => 14, 'amount' => 14, 'unit' => 'g'],
    ]);
    $meal->load('ingredients');

    $grams = MealTiersIngredientStructurer::gramsByTier($meal);

    expect($grams[500][$chicken->id])->toBe(130.0)
        ->and($grams[400][$chicken->id])->toBe(100.0)
        ->and($grams[800][$chicken->id])->toBe(225.0)
        ->and($grams[500][$oil->id])->toBe(ChickenKitchenPlateTargets::platePrepOilGrams())
        ->and($grams[800][$oil->id])->toBe(ChickenKitchenPlateTargets::platePrepOilGrams());
});

test('chicken salads keep shared protein grams and side dressing near 20 grams', function () {
    $chicken = Ingredient::factory()->create([
        'name' => 'Turmeric Chicken (Base)',
        'calories' => 166,
        'protein' => 28,
        'carbs' => 5,
        'fat' => 3,
        'usda_food_category' => 'Proteins',
    ]);
    $kale = Ingredient::factory()->create([
        'name' => 'Kale',
        'calories' => 35,
        'protein' => 2.9,
        'carbs' => 4.4,
        'fat' => 1.5,
        'usda_food_category' => 'Vegetables',
    ]);
    $tomato = Ingredient::factory()->create([
        'name' => 'Cherry Tomatoes',
        'calories' => 18,
        'protein' => 0.9,
        'carbs' => 3.9,
        'fat' => 0.2,
        'usda_food_category' => 'Vegetables',
    ]);
    $dressing = Ingredient::factory()->create([
        'name' => 'Classic Lemon Garlic Dressing (Base)',
        'calories' => 400,
        'protein' => 1,
        'carbs' => 8,
        'fat' => 40,
        'usda_food_category' => 'Fats',
    ]);

    $meal = Meal::factory()->create([
        'name' => 'Turmeric Chicken Kale Salad',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
    ]);
    $meal->ingredients()->attach([
        $chicken->id => ['amount_grams' => 150, 'amount' => 150, 'unit' => 'g'],
        $kale->id => ['amount_grams' => 80, 'amount' => 80, 'unit' => 'g'],
        $tomato->id => ['amount_grams' => 40, 'amount' => 40, 'unit' => 'g'],
        $dressing->id => ['amount_grams' => 40, 'amount' => 40, 'unit' => 'g'],
    ]);
    $meal->load('ingredients');

    $grams = ChickenKitchenContainerScaler::gramsForTier($meal, [
        $chicken->id => 150.0,
        $kale->id => 80.0,
        $tomato->id => 40.0,
        $dressing->id => 40.0,
    ], 500);

    expect($grams[$chicken->id])->toBe(130.0)
        ->and($grams[$dressing->id])->toEqualWithDelta(20.0, 2.5)
        ->and($grams[$kale->id] + $grams[$tomato->id])->toBeGreaterThan(0);
});
