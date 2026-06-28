<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\CustomerProfile;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\Nutrition\AdaptedMenuBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('savory egg breakfast scales to tier egg count and breakfast calorie target', function () {
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
        'craft_key' => 'full',
    ]);

    expect($adapted)->not->toBeNull()
        ->and($adapted['savory_egg_count'])->toBe(5)
        ->and((float) $adapted['adapted_nutrition']['calories'])->toEqualWithDelta(450.0, 1.0);
});

test('small plan savory egg breakfast uses two eggs and hits breakfast target', function () {
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
        'craft_key' => 'full',
    ]);

    expect($adapted['savory_egg_count'])->toBe(2)
        ->and((float) $adapted['adapted_nutrition']['calories'])->toEqualWithDelta(200.0, 1.0);
});

test('savory egg breakfast at 1000 tier hits breakfast target with sides present', function () {
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
        ->and((float) $adapted['adapted_nutrition']['calories'])->toEqualWithDelta(200.0, 1.0);
});

test('savory egg breakfast at 2000 tier hits breakfast target', function () {
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

    expect($adapted['savory_egg_count'])->toBe(5)
        ->and((float) $adapted['adapted_nutrition']['calories'])->toEqualWithDelta(450.0, 1.0);
});

test('savory egg breakfast keeps realistic avocado minimum at small tiers and scales at higher tiers', function () {
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

    expect((float) $avocadoLine['adapted_amount_grams'])->toEqualWithDelta(50.0, 0.5);

    $profile->daily_calorie_target = 2000;
    $adaptedHigh = AdaptedMenuBuilder::adaptMealForProfile($profile, $meal->fresh(['ingredients']), [
        'plan_tier' => 2000,
        'craft_key' => 'full',
    ]);

    $avocadoHigh = collect($adaptedHigh['ingredients'])->firstWhere('name', 'Avocado');

    expect((float) $avocadoHigh['adapted_amount_grams'])->toBeGreaterThan(50.0)
        ->and((float) $avocadoHigh['adapted_amount_grams'])->toEqualWithDelta(112.5, 2.0);
});
