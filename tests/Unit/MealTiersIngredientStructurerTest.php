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
        ->and($grams[500][$chicken->id])->toBe(170.0)
        ->and($grams[800][$chicken->id])->toBe(300.0)
        ->and($grams[400][$oil->id])->toBe(5.0)
        ->and($grams[500][$oil->id])->toBe(5.0)
        ->and($grams[800][$oil->id])->toBe(5.0);
});

test('plant based mains scale toward each calorie tab from the baseline plate', function () {
    $chickpeas = Ingredient::factory()->create([
        'name' => 'Cooked Chickpeas (Base)',
        'calories' => 100,
        'protein' => 8,
        'carbs' => 15,
        'fat' => 2,
        'usda_food_category' => 'Legumes',
    ]);
    $cauliflower = Ingredient::factory()->create([
        'name' => 'Cauliflower',
        'calories' => 25,
        'protein' => 2,
        'carbs' => 5,
        'fat' => 0,
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
        'name' => 'Vegan Harissa Roasted Cauliflower & Chickpea Salad w Tahini Dressing',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
    ]);
    $meal->ingredients()->attach([
        $chickpeas->id => ['amount_grams' => 100, 'amount' => 100, 'unit' => 'g'],
        $cauliflower->id => ['amount_grams' => 200, 'amount' => 200, 'unit' => 'g'],
        $oil->id => ['amount_grams' => 5, 'amount' => 5, 'unit' => 'g'],
    ]);
    $meal->load('ingredients');

    // Baseline = 100 + 50 + 44.2 = 194.2 kcal
    $grams = MealTiersIngredientStructurer::gramsByTier($meal);

    expect(array_keys($grams))->toBe([400, 500, 550, 600, 700, 800])
        ->and($grams[400][$chickpeas->id])->toBeGreaterThan(100)
        ->and($grams[800][$chickpeas->id])->toBeGreaterThan($grams[400][$chickpeas->id])
        ->and(fmod($grams[400][$chickpeas->id], 5.0))->toEqual(0.0)
        ->and(fmod($grams[800][$chickpeas->id], 5.0))->toEqual(0.0)
        ->and(fmod($grams[400][$cauliflower->id], 5.0))->toEqual(0.0)
        ->and($grams[400][$oil->id])->toBe(5.0)
        ->and($grams[800][$oil->id])->toBe(5.0);
});
