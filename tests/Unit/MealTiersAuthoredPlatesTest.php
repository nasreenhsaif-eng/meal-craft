<?php

use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\BalancedCanonicalMealRecipeRefiner;
use App\Support\MealTiersAuthoredPlates;
use App\Support\MealTiersIngredientStructurer;
use App\Support\RosemaryGarlicChickenBaseRecipe;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the rosemary garlic chicken 500 plate uses the cooked base and a half tablespoon of oil', function () {
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
        ->and($grams)->not->toHaveKey('Chicken Breast');
});

test('the rosemary garlic chicken plate uses kitchen 5 gram steps and half tablespoon oil on every tier', function () {
    $name = BalancedCanonicalMealRecipeRefiner::ROSEMARY_GARLIC_CHICKEN_PLATE_NAME;

    expect(MealTiersAuthoredPlates::gramsAtTierForMealName($name, 400))->toBe([
        RosemaryGarlicChickenBaseRecipe::NAME => 100.0,
        'Sweet Potato' => 130.0,
        'Spinach (Fresh)' => 70.0,
        'Mushrooms' => 50.0,
        'Olive Oil (Extra Virgin)' => MealTiersAuthoredPlates::HalfTablespoonOilGrams,
        'Black Pepper' => 1.0,
    ])
        ->and(MealTiersAuthoredPlates::gramsAtTierForMealName($name, 550)['Olive Oil (Extra Virgin)'])
        ->toBe(MealTiersAuthoredPlates::HalfTablespoonOilGrams)
        ->and(MealTiersAuthoredPlates::gramsAtTierForMealName($name, 800)['Olive Oil (Extra Virgin)'])
        ->toBe(MealTiersAuthoredPlates::HalfTablespoonOilGrams)
        ->and(MealTiersAuthoredPlates::gramsAtTierForMealName($name, 800)[RosemaryGarlicChickenBaseRecipe::NAME])
        ->toBe(200.0);

    foreach ([400, 500, 550, 600, 700, 800] as $tier) {
        $grams = MealTiersAuthoredPlates::gramsAtTierForMealName($name, $tier);

        foreach ($grams as $ingredientName => $amount) {
            if ($ingredientName === 'Black Pepper') {
                expect($amount)->toBe(1.0);

                continue;
            }

            expect(((int) $amount) % 5)->toBe(0);
        }
    }
});

test('the rosemary garlic chicken plate authors kitchen gram amounts on every calorie tab', function () {
    $base = Ingredient::factory()->create(['name' => RosemaryGarlicChickenBaseRecipe::NAME, 'is_verified' => true]);
    $potato = Ingredient::factory()->create(['name' => 'Sweet Potato', 'is_verified' => true]);
    $spinach = Ingredient::factory()->create(['name' => 'Spinach (Fresh)', 'is_verified' => true]);
    $mushrooms = Ingredient::factory()->create(['name' => 'Mushrooms', 'is_verified' => true]);
    $oil = Ingredient::factory()->create(['name' => 'Olive Oil (Extra Virgin)', 'is_verified' => true]);
    $pepper = Ingredient::factory()->create(['name' => 'Black Pepper', 'is_verified' => true]);

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
        ->and($grams[500][$potato->id])->toBe(180.0)
        ->and($grams[500][$oil->id])->toBe(MealTiersAuthoredPlates::HalfTablespoonOilGrams)
        ->and($grams[400][$base->id])->toBe(100.0)
        ->and($grams[400][$potato->id])->toBe(130.0)
        ->and($grams[400][$oil->id])->toBe(MealTiersAuthoredPlates::HalfTablespoonOilGrams)
        ->and($grams[550][$oil->id])->toBe(MealTiersAuthoredPlates::HalfTablespoonOilGrams)
        ->and($grams[550][$base->id])->toBe(145.0)
        ->and($grams[600][$base->id])->toBe(165.0)
        ->and($grams[700][$base->id])->toBe(180.0)
        ->and($grams[800][$base->id])->toBe(200.0)
        ->and($grams[800][$potato->id])->toBe(345.0)
        ->and($grams[800][$oil->id])->toBe(MealTiersAuthoredPlates::HalfTablespoonOilGrams);
});
