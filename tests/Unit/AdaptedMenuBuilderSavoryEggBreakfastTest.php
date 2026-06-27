<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\CustomerProfile;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\Nutrition\AdaptedMenuBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('savory egg breakfast scales to tier egg count and adjusts sides', function () {
    $egg = Ingredient::factory()->create([
        'name' => 'Egg',
        'calories' => 155,
        'protein' => 12.6,
        'carbs' => 1.1,
        'fat' => 10.6,
    ]);

    $spinach = Ingredient::factory()->create([
        'name' => 'Spinach (Fresh)',
        'calories' => 23,
        'protein' => 2.9,
        'carbs' => 3.6,
        'fat' => 0.4,
    ]);

    $meal = Meal::factory()->create([
        'name' => 'Hummus Egg Stack',
        'meal_type' => MealType::Breakfast,
        'category' => RecipeCategory::Breakfast,
        'total_calories' => 320,
        'total_protein' => 20,
        'total_carbs' => 18,
        'total_fat' => 18,
    ]);

    $meal->ingredients()->attach([
        $egg->id => ['amount_grams' => 100, 'amount' => 100, 'unit' => 'g'],
        $spinach->id => ['amount_grams' => 45, 'amount' => 45, 'unit' => 'g'],
    ]);

    $profile = CustomerProfile::factory()->create([
        'daily_calorie_target' => 2000,
        'protein_percentage' => 40,
        'carb_percentage' => 30,
        'fat_percentage' => 30,
    ]);

    $adapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $meal->fresh(['ingredients']), [
        'plan_tier' => 2000,
    ]);

    expect($adapted)->not->toBeNull()
        ->and($adapted['savory_egg_count'])->toBe(5);

    $eggLine = collect($adapted['ingredients'])->firstWhere('name', 'Egg');

    expect($eggLine)->not->toBeNull()
        ->and((float) $eggLine['adapted_amount_grams'])->toBe(250.0);

    $spinachLine = collect($adapted['ingredients'])->firstWhere('name', 'Spinach (Fresh)');

    expect($spinachLine)->not->toBeNull()
        ->and((float) $spinachLine['adapted_amount_grams'])->toBe(112.5);
});

test('small plan savory egg breakfast uses two eggs minimum', function () {
    $egg = Ingredient::factory()->create([
        'name' => 'Egg',
        'calories' => 155,
        'protein' => 12.6,
        'carbs' => 1.1,
        'fat' => 10.6,
    ]);

    $meal = Meal::factory()->create([
        'name' => 'Sweet Potato Egg Hash',
        'meal_type' => MealType::Breakfast,
        'category' => RecipeCategory::Breakfast,
        'total_calories' => 300,
        'total_protein' => 18,
        'total_carbs' => 20,
        'total_fat' => 14,
    ]);

    $meal->ingredients()->attach([
        $egg->id => ['amount_grams' => 100, 'amount' => 100, 'unit' => 'g'],
    ]);

    $profile = CustomerProfile::factory()->create([
        'daily_calorie_target' => 1000,
        'protein_percentage' => 40,
        'carb_percentage' => 30,
        'fat_percentage' => 30,
    ]);

    $adapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $meal->fresh(['ingredients']), [
        'plan_tier' => 1000,
    ]);

    expect($adapted['savory_egg_count'])->toBe(2);

    $eggLine = collect($adapted['ingredients'])->firstWhere('name', 'Egg');

    expect((float) $eggLine['adapted_amount_grams'])->toBe(100.0);
});

test('savory egg breakfast keeps full recipe sides at two eggs', function () {
    $egg = Ingredient::factory()->create([
        'name' => 'Egg',
        'calories' => 155,
        'protein' => 12.6,
        'carbs' => 1.1,
        'fat' => 10.6,
    ]);

    $tomato = Ingredient::factory()->create([
        'name' => 'Tomato (Raw)',
        'calories' => 18,
        'protein' => 0.9,
        'carbs' => 3.9,
        'fat' => 0.2,
    ]);

    $meal = Meal::factory()->create([
        'name' => 'Mediterranean Omelet',
        'meal_type' => MealType::Breakfast,
        'category' => RecipeCategory::Breakfast,
        'total_calories' => 305,
        'total_protein' => 15,
        'total_carbs' => 8,
        'total_fat' => 22,
    ]);

    $meal->ingredients()->attach([
        $egg->id => ['amount_grams' => 100, 'amount' => 100, 'unit' => 'g'],
        $tomato->id => ['amount_grams' => 40, 'amount' => 40, 'unit' => 'g'],
    ]);

    $profile = CustomerProfile::factory()->create([
        'daily_calorie_target' => 1000,
        'protein_percentage' => 40,
        'carb_percentage' => 30,
        'fat_percentage' => 30,
    ]);

    $adapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $meal->fresh(['ingredients']), [
        'plan_tier' => 1000,
        'craft_key' => 'full',
        'fixed_chia_breakfast' => true,
    ]);

    $tomatoLine = collect($adapted['ingredients'])->firstWhere('name', 'Tomato (Raw)');

    expect($adapted['savory_egg_count'])->toBe(2)
        ->and($tomatoLine)->not->toBeNull()
        ->and((float) $tomatoLine['adapted_amount_grams'])->toBe(40.0);
});

test('savory egg breakfast scales sides with egg count at higher tiers', function () {
    $egg = Ingredient::factory()->create([
        'name' => 'Egg',
        'calories' => 155,
        'protein' => 12.6,
        'carbs' => 1.1,
        'fat' => 10.6,
    ]);

    $tomato = Ingredient::factory()->create([
        'name' => 'Tomato (Raw)',
        'calories' => 18,
        'protein' => 0.9,
        'carbs' => 3.9,
        'fat' => 0.2,
    ]);

    $meal = Meal::factory()->create([
        'name' => 'Mediterranean Omelet',
        'meal_type' => MealType::Breakfast,
        'category' => RecipeCategory::Breakfast,
        'total_calories' => 305,
        'total_protein' => 15,
        'total_carbs' => 8,
        'total_fat' => 22,
    ]);

    $meal->ingredients()->attach([
        $egg->id => ['amount_grams' => 100, 'amount' => 100, 'unit' => 'g'],
        $tomato->id => ['amount_grams' => 40, 'amount' => 40, 'unit' => 'g'],
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
    ]);

    $tomatoLine = collect($adapted['ingredients'])->firstWhere('name', 'Tomato (Raw)');

    expect($adapted['savory_egg_count'])->toBe(5)
        ->and($tomatoLine)->not->toBeNull()
        ->and((float) $tomatoLine['adapted_amount_grams'])->toBe(100.0);
});

test('savory egg breakfast keeps realistic avocado and oil at small tiers', function () {
    $egg = Ingredient::factory()->create([
        'name' => 'Egg',
        'calories' => 155,
        'protein' => 12.6,
        'carbs' => 1.1,
        'fat' => 10.6,
    ]);

    $avocado = Ingredient::factory()->create([
        'name' => 'Avocado',
        'calories' => 160,
        'protein' => 2,
        'carbs' => 8.5,
        'fat' => 14.7,
    ]);

    $oliveOil = Ingredient::factory()->create([
        'name' => 'Olive Oil (Extra Virgin)',
        'calories' => 884,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 100,
    ]);

    $meal = Meal::factory()->create([
        'name' => 'Mediterranean Omelet',
        'meal_type' => MealType::Breakfast,
        'category' => RecipeCategory::Breakfast,
        'total_calories' => 305,
        'total_protein' => 15,
        'total_carbs' => 8,
        'total_fat' => 22,
    ]);

    $meal->ingredients()->attach([
        $egg->id => ['amount_grams' => 100, 'amount' => 100, 'unit' => 'g'],
        $avocado->id => ['amount_grams' => 20, 'amount' => 20, 'unit' => 'g'],
        $oliveOil->id => ['amount_grams' => 6, 'amount' => 6, 'unit' => 'g'],
    ]);

    $profile = CustomerProfile::factory()->create([
        'daily_calorie_target' => 1000,
        'protein_percentage' => 40,
        'carb_percentage' => 30,
        'fat_percentage' => 30,
    ]);

    $adapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $meal->fresh(['ingredients']), [
        'plan_tier' => 1000,
        'craft_key' => 'full',
        'fixed_chia_breakfast' => true,
    ]);

    $avocadoLine = collect($adapted['ingredients'])->firstWhere('name', 'Avocado');
    $oilLine = collect($adapted['ingredients'])->firstWhere('name', 'Olive Oil (Extra Virgin)');

    expect((float) $avocadoLine['adapted_amount_grams'])->toBe(50.0)
        ->and((float) $oilLine['adapted_amount_grams'])->toBe(6.0);
});
