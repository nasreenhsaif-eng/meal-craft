<?php

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
