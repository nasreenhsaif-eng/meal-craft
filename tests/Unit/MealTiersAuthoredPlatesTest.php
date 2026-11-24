<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\BalancedCanonicalMealRecipeRefiner;
use App\Support\ChickenKitchenPlateTargets;
use App\Support\MealTiersAuthoredPlates;
use App\Support\MealTiersIngredientStructurer;
use App\Support\RosemaryGarlicChickenBaseRecipe;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the rosemary garlic chicken 500 baseline uses the cooked base and a half tablespoon of oil', function () {
    $grams = MealTiersAuthoredPlates::gramsAt500ForMealName(
        BalancedCanonicalMealRecipeRefiner::ROSEMARY_GARLIC_CHICKEN_PLATE_NAME,
    );

    expect($grams)->toBe([
        RosemaryGarlicChickenBaseRecipe::NAME => 130.0,
        'Sweet Potato' => 180.0,
        'Spinach (Fresh)' => 25.0,
        'Mushrooms' => 35.0,
        'Olive Oil (Extra Virgin)' => MealTiersAuthoredPlates::HalfTablespoonOilGrams,
        'Black Pepper' => 1.0,
    ])
        ->and($grams)->not->toHaveKey('Chicken Breast')
        ->and(MealTiersAuthoredPlates::gramsAtTierForMealName(
            BalancedCanonicalMealRecipeRefiner::ROSEMARY_GARLIC_CHICKEN_PLATE_NAME,
            800,
        ))->toBeNull();
});

test('the rosemary garlic chicken plate scales into the 1000 ml container on every calorie tab', function () {
    $base = Ingredient::factory()->create([
        'name' => RosemaryGarlicChickenBaseRecipe::NAME,
        'calories' => 173,
        'protein' => 21,
        'carbs' => 4,
        'fat' => 8,
        'is_verified' => true,
    ]);
    $potato = Ingredient::factory()->create(['name' => 'Sweet Potato', 'calories' => 86, 'protein' => 1.6, 'carbs' => 20, 'fat' => 0.1, 'is_verified' => true]);
    $spinach = Ingredient::factory()->create(['name' => 'Spinach (Fresh)', 'calories' => 23, 'protein' => 2.9, 'carbs' => 3.6, 'fat' => 0.4, 'is_verified' => true]);
    $mushrooms = Ingredient::factory()->create(['name' => 'Mushrooms', 'calories' => 22, 'protein' => 3.1, 'carbs' => 3.3, 'fat' => 0.3, 'is_verified' => true]);
    $oil = Ingredient::factory()->create(['name' => 'Olive Oil (Extra Virgin)', 'calories' => 884, 'protein' => 0, 'carbs' => 0, 'fat' => 100, 'is_verified' => true]);
    $pepper = Ingredient::factory()->create(['name' => 'Black Pepper', 'calories' => 251, 'protein' => 10, 'carbs' => 64, 'fat' => 3.3, 'is_verified' => true]);

    $meal = Meal::factory()->create([
        'name' => BalancedCanonicalMealRecipeRefiner::ROSEMARY_GARLIC_CHICKEN_PLATE_NAME,
    ]);
    $meal->ingredients()->attach([
        $base->id => ['amount_grams' => 130, 'amount' => 130, 'unit' => 'g'],
        $potato->id => ['amount_grams' => 180, 'amount' => 180, 'unit' => 'g'],
        $spinach->id => ['amount_grams' => 25, 'amount' => 25, 'unit' => 'g'],
        $mushrooms->id => ['amount_grams' => 35, 'amount' => 35, 'unit' => 'g'],
        $oil->id => ['amount_grams' => 5, 'amount' => 5, 'unit' => 'g'],
        $pepper->id => ['amount_grams' => 1, 'amount' => 1, 'unit' => 'g'],
    ]);
    $meal->load('ingredients');

    $grams = MealTiersIngredientStructurer::gramsByTier($meal);

    expect($grams[500][$base->id])->toBe(130.0)
        ->and($grams[400][$base->id])->toBe(100.0)
        ->and($grams[800][$base->id])->toBe(225.0)
        ->and($grams[500][$oil->id])->toBe(MealTiersAuthoredPlates::HalfTablespoonOilGrams)
        ->and($grams[800][$oil->id])->toBe(MealTiersAuthoredPlates::HalfTablespoonOilGrams)
        ->and(ChickenKitchenPlateTargets::containerMl())->toBe(1000.0);
});

test('butternut squash frittata breakfast tabs keep vegetable body two teaspoons of oil and tab egg counts', function () {
    $eggs = Ingredient::factory()->create(['name' => 'Eggs (Large)', 'calories' => 143, 'protein' => 13, 'carbs' => 1, 'fat' => 10, 'usda_food_category' => 'Proteins', 'is_verified' => true]);
    $squash = Ingredient::factory()->create(['name' => 'Butternut Squash', 'calories' => 45, 'protein' => 1, 'carbs' => 12, 'fat' => 0.1, 'usda_food_category' => 'Vegetables', 'is_verified' => true]);
    $oil = Ingredient::factory()->create(['name' => 'Olive Oil', 'calories' => 884, 'protein' => 0, 'carbs' => 0, 'fat' => 100, 'usda_food_category' => 'Fats', 'is_verified' => true]);
    $onion = Ingredient::factory()->create(['name' => 'Red Onion', 'calories' => 40, 'protein' => 1, 'carbs' => 9, 'fat' => 0.1, 'usda_food_category' => 'Vegetables', 'is_verified' => true]);
    $flour = Ingredient::factory()->create(['name' => 'Chickpea Flour', 'calories' => 387, 'protein' => 22, 'carbs' => 58, 'fat' => 7, 'usda_food_category' => 'Grains', 'is_verified' => true]);
    $cheese = Ingredient::factory()->create(['name' => 'Gruyere Cheese', 'calories' => 413, 'protein' => 30, 'carbs' => 0.4, 'fat' => 32, 'usda_food_category' => 'Dairy', 'is_verified' => true]);
    $yogurt = Ingredient::factory()->create(['name' => 'Greek Yogurt', 'calories' => 59, 'protein' => 10, 'carbs' => 4, 'fat' => 0.4, 'usda_food_category' => 'Dairy', 'is_verified' => true]);
    $marinara = Ingredient::factory()->create(['name' => 'Marinara Sauce (Base)', 'calories' => 30, 'protein' => 1, 'carbs' => 5, 'fat' => 1, 'usda_food_category' => 'Base Ingredient', 'is_verified' => true]);
    $dill = Ingredient::factory()->create(['name' => 'Dill (Fresh)', 'calories' => 43, 'protein' => 3, 'carbs' => 7, 'fat' => 1, 'usda_food_category' => 'Spices', 'is_verified' => true]);
    $paprika = Ingredient::factory()->create(['name' => 'Paprika', 'calories' => 282, 'protein' => 14, 'carbs' => 54, 'fat' => 13, 'usda_food_category' => 'Spices', 'is_verified' => true]);

    $meal = Meal::factory()->create([
        'name' => MealTiersAuthoredPlates::ButternutSquashFrittataName,
        'category' => RecipeCategory::Breakfast,
        'meal_type' => MealType::Breakfast,
    ]);
    $meal->ingredients()->attach([
        $eggs->id => ['amount_grams' => 200, 'amount' => 200, 'unit' => 'g'],
        $squash->id => ['amount_grams' => 100, 'amount' => 100, 'unit' => 'g'],
        $oil->id => ['amount_grams' => 10, 'amount' => 10, 'unit' => 'g'],
        $onion->id => ['amount_grams' => 35, 'amount' => 35, 'unit' => 'g'],
        $flour->id => ['amount_grams' => 15, 'amount' => 15, 'unit' => 'g'],
        $cheese->id => ['amount_grams' => 35, 'amount' => 35, 'unit' => 'g'],
        $yogurt->id => ['amount_grams' => 40, 'amount' => 40, 'unit' => 'g'],
        $marinara->id => ['amount_grams' => 80, 'amount' => 80, 'unit' => 'g'],
        $dill->id => ['amount_grams' => 8, 'amount' => 8, 'unit' => 'g'],
        $paprika->id => ['amount_grams' => 1, 'amount' => 1, 'unit' => 'g'],
    ]);
    $meal->load('ingredients');

    $grams = MealTiersIngredientStructurer::gramsByTier($meal);

    expect(array_keys($grams))->toBe([300, 400, 500])
        ->and($grams[300][$squash->id])->toBe(90.0)
        ->and($grams[400][$squash->id])->toBe(100.0)
        ->and($grams[500][$squash->id])->toBe(100.0)
        ->and($grams[300][$oil->id])->toBe(10.0)
        ->and($grams[400][$oil->id])->toBe(10.0)
        ->and($grams[500][$oil->id])->toBe(10.0)
        ->and($grams[300][$eggs->id])->toBe(100.0)
        ->and($grams[400][$eggs->id])->toBe(150.0)
        ->and($grams[500][$eggs->id])->toBe(200.0)
        ->and(MealTiersAuthoredPlates::scalesFrom500($meal))->toBeFalse();
});
