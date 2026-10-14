<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\CustomerCraftKitchenSheetService;
use App\Support\ChickenBreastYield;
use App\Support\IngredientLibraryCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('kitchen instructions are per meal with cooked protein grams and breakfast egg counts', function () {
    $egg = Ingredient::factory()->create([
        'name' => 'Egg',
        'usda_food_category' => 'Proteins',
        'calories' => 155,
        'protein' => 12.6,
        'carbs' => 1.1,
        'fat' => 10.6,
    ]);

    $spinach = Ingredient::factory()->create([
        'name' => 'Spinach',
        'usda_food_category' => 'Vegetables',
        'calories' => 23,
        'protein' => 2.9,
        'carbs' => 3.6,
        'fat' => 0.4,
    ]);

    $chicken = Ingredient::factory()->create([
        'name' => ChickenBreastYield::RAW_INGREDIENT_NAME,
        'usda_food_category' => 'Proteins',
        'calories' => 120,
        'protein' => 22,
        'carbs' => 0,
        'fat' => 3,
    ]);

    $rice = Ingredient::factory()->create([
        'name' => 'Basmati White',
        'usda_food_category' => 'Grains',
        'calories' => 356,
        'protein' => 7,
        'carbs' => 78,
        'fat' => 1,
    ]);

    $sauce = Ingredient::factory()->create([
        'name' => 'Marinara Sauce (Base)',
        'usda_food_category' => IngredientLibraryCategory::BaseIngredient,
        'calories' => 50,
        'protein' => 1,
        'carbs' => 8,
        'fat' => 1,
    ]);

    $beef = Ingredient::factory()->create([
        'name' => 'Beef Ground Lean',
        'usda_food_category' => 'Proteins',
        'calories' => 176,
        'protein' => 20,
        'carbs' => 0,
        'fat' => 10,
    ]);

    $breakfast = Meal::factory()->create([
        'name' => 'Gouda & Spinach Scramble',
        'meal_type' => MealType::Breakfast,
        'category' => RecipeCategory::Breakfast,
        'total_calories' => 300,
    ]);

    $mainOne = Meal::factory()->create([
        'name' => 'Chicken Plate',
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 450,
    ]);

    $mainTwo = Meal::factory()->create([
        'name' => 'Beef Plate',
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 450,
    ]);

    $instructions = CustomerCraftKitchenSheetService::kitchenInstructionsFromAdaptedMeals([
        [
            'id' => $breakfast->id,
            'slot' => 'breakfast',
            'kitchen_section' => 'breakfast',
            'savory_egg_count' => 3,
            'ingredients' => [
                ['id' => $egg->id, 'name' => 'Egg', 'adapted_amount_grams' => 150],
                ['id' => $spinach->id, 'name' => 'Spinach', 'adapted_amount_grams' => 100],
            ],
        ],
        [
            'id' => $mainOne->id,
            'slot' => 'main',
            'kitchen_section' => 'meal 1',
            'ingredients' => [
                ['id' => $chicken->id, 'name' => 'Chicken Breast', 'adapted_amount_grams' => 160],
                ['id' => $rice->id, 'name' => 'Basmati White', 'adapted_amount_grams' => 50],
                ['id' => $sauce->id, 'name' => 'Marinara Sauce (Base)', 'adapted_amount_grams' => 50],
            ],
        ],
        [
            'id' => $mainTwo->id,
            'slot' => 'main',
            'kitchen_section' => 'meal 2',
            'ingredients' => [
                ['id' => $beef->id, 'name' => 'Beef Ground Lean', 'adapted_amount_grams' => 200],
            ],
        ],
    ]);

    expect($instructions)->toContain('breakfast: 3 eggs, vegetables: 100g')
        ->and($instructions)->toContain('meal 1: chicken breast 120g, carbs: 130g, sauce: 50g')
        ->and($instructions)->toContain('meal 2: beef ground lean 150g')
        ->and($instructions)->not->toContain('egg: 150');
});

test('kitchen instructions fall back to egg grams when savory count is missing', function () {
    $egg = Ingredient::factory()->create([
        'name' => 'Eggs (Large)',
        'usda_food_category' => 'Proteins',
        'calories' => 155,
        'protein' => 12.6,
        'carbs' => 1.1,
        'fat' => 10.6,
    ]);

    $breakfast = Meal::factory()->create([
        'name' => 'Skillet Eggs',
        'meal_type' => MealType::Breakfast,
        'category' => RecipeCategory::Breakfast,
        'total_calories' => 280,
    ]);

    $instructions = CustomerCraftKitchenSheetService::kitchenInstructionsFromAdaptedMeals([
        [
            'id' => $breakfast->id,
            'slot' => 'breakfast',
            'kitchen_section' => 'breakfast',
            'ingredients' => [
                [
                    'id' => $egg->id,
                    'name' => 'Eggs (Large)',
                    'adapted_amount_grams' => 200,
                ],
            ],
        ],
    ]);

    expect($instructions)->toBe('breakfast: 4 eggs');
});
