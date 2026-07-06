<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\CustomerProfile;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\Nutrition\AdaptedMenuBuilder;
use App\Support\IngredientLibraryCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('chia dessert meals stay at fixed kitchen portion without scaling', function () {
    $base = Ingredient::factory()->create([
        'name' => 'Coconut Chia Pudding (Base)',
        'calories' => 265,
        'protein' => 3.9,
        'carbs' => 14.6,
        'fat' => 23,
    ]);

    $meal = Meal::factory()->create([
        'name' => 'Blueberry Walnut Chia Pudding',
        'meal_type' => MealType::Dessert,
        'category' => RecipeCategory::Dessert,
        'total_calories' => 318,
        'total_protein' => 12,
        'total_carbs' => 22,
        'total_fat' => 28,
    ]);

    $meal->ingredients()->attach($base->id, [
        'amount_grams' => 120,
        'amount' => 120,
        'unit' => 'g',
    ]);

    $profile = CustomerProfile::factory()->create([
        'daily_calorie_target' => 1500,
        'protein_percentage' => 40,
        'carb_percentage' => 30,
        'fat_percentage' => 30,
    ]);

    $adapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $meal->fresh(['ingredients']), [
        'plan_tier' => 1500,
        'craft_key' => 'full',
        'dessert_calories' => 318,
    ]);

    $ingredientLine = collect($adapted['ingredients'])->firstWhere('name', 'Coconut Chia Pudding (Base)');

    expect($adapted)->not->toBeNull()
        ->and($adapted['slot'])->toBe('dessert')
        ->and($adapted['portion_behavior'])->toBe('fixed_portion')
        ->and($adapted['is_scaled'])->toBeFalse()
        ->and($adapted['scaling_multiplier'])->toBe(1.0)
        ->and((float) $adapted['adapted_nutrition']['calories'])->toBeGreaterThanOrEqual(300.0)
        ->and((float) $ingredientLine['adapted_amount_grams'])->toBe(120.0);
});

test('chia dessert enforces 120g base even when library pivot is stale', function () {
    $base = Ingredient::factory()->create([
        'name' => 'Coconut Chia Pudding (Base)',
        'calories' => 265,
        'protein' => 3.9,
        'carbs' => 14.6,
        'fat' => 23,
    ]);

    $mango = Ingredient::factory()->create([
        'name' => 'Mango',
        'calories' => 60,
        'protein' => 0.8,
        'carbs' => 15,
        'fat' => 0.4,
    ]);

    $meal = Meal::factory()->create([
        'name' => 'Mango Pumpkin Seed Chia Pudding',
        'meal_type' => MealType::Dessert,
        'category' => RecipeCategory::Dessert,
        'total_calories' => 318,
        'total_protein' => 12,
        'total_carbs' => 22,
        'total_fat' => 28,
    ]);

    $meal->ingredients()->attach([
        $base->id => ['amount_grams' => 99.48, 'amount' => 99.48, 'unit' => 'g'],
        $mango->id => ['amount_grams' => 17.42, 'amount' => 17.42, 'unit' => 'g'],
    ]);

    $profile = CustomerProfile::factory()->create([
        'daily_calorie_target' => 2000,
        'protein_percentage' => 40,
        'carb_percentage' => 30,
        'fat_percentage' => 30,
    ]);

    $adapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $meal->fresh(['ingredients']), [
        'plan_tier' => 2000,
        'craft_key' => 'full',
        'dessert_calories' => 150,
        'day_of_week' => 2,
    ]);

    $baseLine = collect($adapted['ingredients'])->firstWhere('name', 'Coconut Chia Pudding (Base)');

    expect((float) $baseLine['adapted_amount_grams'])->toBe(120.0)
        ->and((float) $adapted['adapted_nutrition']['calories'])->toBeGreaterThanOrEqual(300.0);
});

test('greek yogurt chia dessert meals enforce 150g base at fixed portion', function () {
    $base = Ingredient::factory()->create([
        'name' => 'Greek Yogurt Chia Pudding (Base)',
        'calories' => 126,
        'protein' => 10.3,
        'carbs' => 13.1,
        'fat' => 4.1,
    ]);

    $meal = Meal::factory()->create([
        'name' => 'Blueberry Walnut Greek Yogurt Chia Pudding',
        'meal_type' => MealType::Dessert,
        'category' => RecipeCategory::Dessert,
        'total_calories' => 318,
        'total_protein' => 22,
        'total_carbs' => 31,
        'total_fat' => 13,
    ]);

    $meal->ingredients()->attach($base->id, [
        'amount_grams' => 120,
        'amount' => 120,
        'unit' => 'g',
    ]);

    $profile = CustomerProfile::factory()->create([
        'daily_calorie_target' => 2000,
        'protein_percentage' => 40,
        'carb_percentage' => 30,
        'fat_percentage' => 30,
    ]);

    $adapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $meal->fresh(['ingredients']), [
        'plan_tier' => 2000,
        'craft_key' => 'full',
        'dessert_calories' => 318,
    ]);

    $baseLine = collect($adapted['ingredients'])->firstWhere('name', 'Greek Yogurt Chia Pudding (Base)');

    expect((float) $baseLine['adapted_amount_grams'])->toBe(150.0);
});

test('greek yogurt chia base with yogurt not dressing keeps adapted dessert calories realistic', function () {
    $chia = Ingredient::factory()->create([
        'name' => 'Chia Seeds',
        'calories' => 486,
        'protein' => 16.5,
        'carbs' => 42.1,
        'fat' => 30.7,
        'is_verified' => true,
    ]);
    $yogurt = Ingredient::factory()->create([
        'name' => 'Greek Yogurt',
        'calories' => 59,
        'protein' => 10.2,
        'carbs' => 3.6,
        'fat' => 0.4,
        'is_verified' => true,
    ]);
    $honey = Ingredient::factory()->create([
        'name' => 'Honey (Raw)',
        'calories' => 304,
        'protein' => 0.3,
        'carbs' => 82.4,
        'fat' => 0,
        'is_verified' => true,
    ]);
    $salt = Ingredient::factory()->create([
        'name' => 'Sea Salt',
        'calories' => 0,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 0,
        'is_verified' => true,
    ]);

    $base = Ingredient::factory()->create([
        'name' => 'Greek Yogurt Chia Pudding (Base)',
        'usda_food_category' => IngredientLibraryCategory::BaseIngredient,
        'calories' => 89.25,
        'protein' => 10.28,
        'carbs' => 7.89,
        'fat' => 2.06,
        'is_verified' => true,
        'finished_weight_grams' => 545,
    ]);
    $base->components()->attach($chia->id, ['amount_grams' => 30]);
    $base->components()->attach($yogurt->id, ['amount_grams' => 500]);
    $base->components()->attach($honey->id, ['amount_grams' => 15]);
    $base->components()->attach($salt->id, ['amount_grams' => 1]);

    $meal = Meal::factory()->create([
        'name' => 'Mango Pumpkin Seed Greek Yogurt Chia Pudding',
        'meal_type' => MealType::Dessert,
        'category' => RecipeCategory::Dessert,
        'total_calories' => 249,
        'total_protein' => 18,
        'total_carbs' => 22,
        'total_fat' => 10,
    ]);

    $meal->ingredients()->attach($base->id, [
        'amount_grams' => 150,
        'amount' => 150,
        'unit' => 'g',
    ]);

    $profile = CustomerProfile::factory()->create([
        'daily_calorie_target' => 1500,
        'protein_percentage' => 32,
        'carb_percentage' => 28,
        'fat_percentage' => 40,
        'diet_protocol' => 'nutrient_dense',
    ]);

    $adapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $meal->fresh(['ingredients']), [
        'plan_tier' => 1500,
        'craft_key' => 'full',
        'schedule_slot' => 'dessert',
    ]);

    expect($adapted)->not->toBeNull()
        ->and((float) $adapted['adapted_nutrition']['calories'])->toBeLessThan(320.0)
        ->and((float) $adapted['adapted_nutrition']['calories'])->not->toBeGreaterThan(500.0);
});
