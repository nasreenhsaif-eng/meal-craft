<?php

use App\Enums\MealScalingRole as MealScalingRoleEnum;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Support\MealScalingRole;

test('classifies primary protein carb herb vegetable fat and sauce ingredients', function () {
    $meal = Meal::factory()->make(['name' => 'Rosemary Chicken Plate']);

    $protein = Ingredient::factory()->make(['name' => 'Chicken Breast', 'usda_food_category' => 'Proteins']);
    $carb = Ingredient::factory()->make(['name' => 'Cooked Quinoa (Base)', 'usda_food_category' => 'Grains']);
    $herb = Ingredient::factory()->make(['name' => 'Fresh Rosemary', 'usda_food_category' => 'Herbs']);
    $vegetable = Ingredient::factory()->make(['name' => 'Zucchini', 'usda_food_category' => 'Vegetables']);
    $fat = Ingredient::factory()->make(['name' => 'Olive Oil', 'usda_food_category' => 'Pantry']);
    $sauce = Ingredient::factory()->make(['name' => 'Marinara Sauce (Base)', 'usda_food_category' => 'Base Ingredient']);

    expect(MealScalingRole::roleForIngredient($protein, $meal))->toBe(MealScalingRoleEnum::Protein)
        ->and(MealScalingRole::roleForIngredient($carb, $meal))->toBe(MealScalingRoleEnum::Carb)
        ->and(MealScalingRole::roleForIngredient($herb, $meal))->toBe(MealScalingRoleEnum::HerbSpice)
        ->and(MealScalingRole::roleForIngredient($vegetable, $meal))->toBe(MealScalingRoleEnum::Vegetable)
        ->and(MealScalingRole::roleForIngredient($fat, $meal))->toBe(MealScalingRoleEnum::Fat)
        ->and(MealScalingRole::roleForIngredient($sauce, $meal))->toBe(MealScalingRoleEnum::Sauce);
});

test('classifies primary meat base recipes as protein', function () {
    $meal = Meal::factory()->make(['name' => 'Tandoori Chicken Bowl']);
    $base = Ingredient::factory()->make(['name' => 'Tandoori Chicken (Base)', 'usda_food_category' => 'Base Ingredient']);

    expect(MealScalingRole::roleForIngredient($base, $meal))->toBe(MealScalingRoleEnum::Protein);
});

test('vegetable role is fixed at baseline for scaling policy', function () {
    expect(MealScalingRoleEnum::Vegetable->isFixedAtBaseline())->toBeTrue()
        ->and(MealScalingRoleEnum::Fat->isTrimEligible())->toBeTrue()
        ->and(MealScalingRoleEnum::Protein->isTrimEligible())->toBeFalse();
});
