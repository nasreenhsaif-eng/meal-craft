<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\CustomerProfile;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\Nutrition\AdaptedMenuBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('chia breakfast meals scale to the plan-tier breakfast target', function () {
    $base = Ingredient::factory()->create([
        'name' => 'Coconut Chia Pudding (Base)',
        'calories' => 265,
        'protein' => 3.9,
        'carbs' => 14.6,
        'fat' => 23,
    ]);

    $meal = Meal::factory()->create([
        'name' => 'Blueberry Walnut Chia Pudding',
        'meal_type' => MealType::Breakfast,
        'category' => RecipeCategory::Breakfast,
        'total_calories' => 198,
        'total_protein' => 8,
        'total_carbs' => 18,
        'total_fat' => 10,
    ]);

    $meal->ingredients()->attach($base->id, [
        'amount_grams' => 53,
        'amount' => 53,
        'unit' => 'g',
    ]);

    $profile = CustomerProfile::factory()->create([
        'daily_calorie_target' => 1500,
        'protein_percentage' => 40,
        'carb_percentage' => 30,
        'fat_percentage' => 30,
    ]);

    $adapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $meal->fresh(['ingredients']), [
        'fixed_chia_breakfast' => true,
        'plan_tier' => 1500,
        'craft_key' => 'full',
    ]);

    expect($adapted)->not->toBeNull()
        ->and($adapted['portion_behavior'])->toBe('scalable')
        ->and($adapted['is_scaled'])->toBeTrue()
        ->and($adapted['fixed_chia_breakfast'])->toBeTrue()
        ->and((float) $adapted['adapted_nutrition']['calories'])->toEqualWithDelta(300.0, 1.0);
});
