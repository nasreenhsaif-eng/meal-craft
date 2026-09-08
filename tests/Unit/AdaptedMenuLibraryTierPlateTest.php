<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\CustomerProfile;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use App\Services\Nutrition\AdaptedMenuBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('tiers library mains serve the authored calorie tab without scaling', function () {
    $user = User::factory()->create();
    $profile = CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 1500,
        'protein_percentage' => 35,
        'carb_percentage' => 35,
        'fat_percentage' => 30,
    ]);

    $chicken = Ingredient::factory()->create([
        'name' => 'Chicken Breast',
        'calories' => 165,
        'protein' => 31,
        'carbs' => 0,
        'fat' => 3.6,
        'usda_food_category' => 'Proteins',
    ]);

    $meal = Meal::factory()->tiers()->create([
        'name' => 'Library Chicken Plate',
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 500,
        'total_protein' => 45,
        'total_carbs' => 30,
        'total_fat' => 18,
    ]);
    $meal->ingredients()->attach($chicken->id, ['amount_grams' => 200]);

    $tier = $meal->calorieTiers()->create([
        'calorie_tier' => 400,
        'designed_calories' => 400,
        'total_calories' => 400,
        'total_protein' => 40,
        'total_carbs' => 20,
        'total_fat' => 12,
        'nutrition' => ['calories' => 400, 'protein' => 40, 'carbs' => 20, 'fat' => 12],
    ]);
    $tier->ingredients()->attach($chicken->id, ['amount_grams' => 165]);

    $adapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $meal->fresh(['ingredients', 'calorieTiers.ingredients']), [
        'craft_key' => 'full',
        'schedule_slot' => 'main',
    ]);

    $chickenLine = collect($adapted['ingredients'])->firstWhere('name', 'Chicken Breast');

    expect($adapted['is_scaled'])->toBeFalse()
        ->and($adapted['library_calorie_tier'])->toBe(400)
        ->and($adapted['portion_behavior'])->toBe('fixed_portion')
        ->and((float) ($chickenLine['adapted_amount_grams'] ?? 0))->toBe(165.0);
});

test('full craft 2000 breakfast uses the 500 calorie tab', function () {
    $user = User::factory()->create();
    $profile = CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 2000,
        'protein_percentage' => 35,
        'carb_percentage' => 35,
        'fat_percentage' => 30,
    ]);

    $egg = Ingredient::factory()->create([
        'name' => 'Egg',
        'calories' => 143,
        'protein' => 13,
        'carbs' => 1,
        'fat' => 10,
        'usda_food_category' => 'Proteins',
    ]);

    $meal = Meal::factory()->tiers()->create([
        'name' => 'Library Omelet',
        'meal_type' => MealType::Breakfast,
        'category' => RecipeCategory::Breakfast,
        'total_calories' => 400,
        'total_protein' => 24,
        'total_carbs' => 8,
        'total_fat' => 28,
    ]);
    $meal->ingredients()->attach($egg->id, ['amount_grams' => 150]);

    $tier = $meal->calorieTiers()->create([
        'calorie_tier' => 500,
        'designed_calories' => 500,
        'total_calories' => 500,
        'total_protein' => 32,
        'total_carbs' => 10,
        'total_fat' => 36,
        'nutrition' => ['calories' => 500, 'protein' => 32, 'carbs' => 10, 'fat' => 36],
    ]);
    $tier->ingredients()->attach($egg->id, ['amount_grams' => 200]);

    $adapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $meal->fresh(['ingredients', 'calorieTiers.ingredients']), [
        'craft_key' => 'full',
        'schedule_slot' => 'breakfast',
    ]);

    $eggLine = collect($adapted['ingredients'])->firstWhere('name', 'Egg');

    expect($adapted['library_calorie_tier'])->toBe(500)
        ->and($adapted['is_scaled'])->toBeFalse()
        ->and((float) ($eggLine['adapted_amount_grams'] ?? 0))->toBe(200.0);
});

test('full craft 2000 mains use the 600 calorie tab', function () {
    $user = User::factory()->create();
    $profile = CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 2000,
        'protein_percentage' => 35,
        'carb_percentage' => 35,
        'fat_percentage' => 30,
    ]);

    $chicken = Ingredient::factory()->create([
        'name' => 'Chicken Breast',
        'calories' => 165,
        'protein' => 31,
        'carbs' => 0,
        'fat' => 3.6,
        'usda_food_category' => 'Proteins',
    ]);

    $meal = Meal::factory()->tiers()->create([
        'name' => 'Library Chicken Plate',
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 500,
    ]);
    $meal->ingredients()->attach($chicken->id, ['amount_grams' => 180]);

    $tier = $meal->calorieTiers()->create([
        'calorie_tier' => 600,
        'designed_calories' => 600,
        'total_calories' => 600,
        'total_protein' => 54,
        'total_carbs' => 30,
        'total_fat' => 22,
        'nutrition' => ['calories' => 600, 'protein' => 54, 'carbs' => 30, 'fat' => 22],
    ]);
    $tier->ingredients()->attach($chicken->id, ['amount_grams' => 220]);

    $adapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $meal->fresh(['ingredients', 'calorieTiers.ingredients']), [
        'craft_key' => 'full',
        'schedule_slot' => 'main',
    ]);

    $chickenLine = collect($adapted['ingredients'])->firstWhere('name', 'Chicken Breast');

    expect($adapted['library_calorie_tier'])->toBe(600)
        ->and($adapted['is_scaled'])->toBeFalse()
        ->and((float) ($chickenLine['adapted_amount_grams'] ?? 0))->toBe(220.0);
});

test('tiers library sides keep the authored one-size recipe', function () {
    $user = User::factory()->create();
    $profile = CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 1800,
        'protein_percentage' => 35,
        'carb_percentage' => 35,
        'fat_percentage' => 30,
    ]);

    $greens = Ingredient::factory()->create([
        'name' => 'Rocca',
        'calories' => 25,
        'protein' => 2.6,
        'carbs' => 3.6,
        'fat' => 0.7,
        'usda_food_category' => 'Vegetables',
    ]);

    $salad = Meal::factory()->tiers()->create([
        'name' => 'Library Side Salad',
        'meal_type' => MealType::Salad,
        'category' => RecipeCategory::SideSalad,
        'total_calories' => 127,
    ]);
    $salad->ingredients()->attach($greens->id, ['amount_grams' => 80]);

    $adapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $salad->fresh(['ingredients']), [
        'craft_key' => 'full',
        'schedule_slot' => 'side_salad',
    ]);

    $greensLine = collect($adapted['ingredients'])->firstWhere('name', 'Rocca');

    expect($adapted['is_scaled'])->toBeFalse()
        ->and($adapted['portion_behavior'])->toBe('fixed_portion')
        ->and((float) ($greensLine['adapted_amount_grams'] ?? 0))->toBe(80.0);
});
