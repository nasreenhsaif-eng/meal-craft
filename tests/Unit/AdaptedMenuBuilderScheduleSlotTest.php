<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\CustomerProfile;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\Nutrition\AdaptedMenuBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('schedule_slot main scales salad-library chicken mains toward main calorie target', function () {
    $profile = CustomerProfile::factory()->create([
        'daily_calorie_target' => 2000,
        'protein_percentage' => 40,
        'carb_percentage' => 30,
        'fat_percentage' => 30,
    ]);

    $ingredient = Ingredient::factory()->create([
        'calories' => 100,
        'protein' => 10,
        'carbs' => 10,
        'fat' => 5,
    ]);

    $meal = Meal::factory()->create([
        'name' => 'Scheduled Main Chicken Salad',
        'meal_type' => MealType::Salad,
        'category' => RecipeCategory::SideSalad,
        'total_calories' => 300,
        'total_protein' => 25,
        'total_carbs' => 20,
        'total_fat' => 10,
    ]);
    $meal->ingredients()->attach($ingredient->id, ['amount_grams' => 100]);

    $libraryAdapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $meal->fresh(['ingredients']), [
        'plan_tier' => 2000,
    ]);

    $scheduledMainAdapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $meal->fresh(['ingredients']), [
        'plan_tier' => 2000,
        'schedule_slot' => 'main',
    ]);

    expect($libraryAdapted)->not->toBeNull()
        ->and($libraryAdapted['is_scaled'])->toBeFalse()
        ->and($libraryAdapted['slot'])->toBe('side_salad')
        ->and($scheduledMainAdapted)->not->toBeNull()
        ->and($scheduledMainAdapted['is_scaled'])->toBeTrue()
        ->and($scheduledMainAdapted['slot'])->toBe('main');
});
