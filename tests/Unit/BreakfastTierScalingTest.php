<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\CustomerProfile;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\Nutrition\AdaptedMenuBuilder;
use App\Support\ChiaBreakfastMeals;
use App\Support\SavoryEggBreakfastMeals;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('breakfast meals scale to explicit tier targets', function (int $tier, float $breakfastTarget) {
    $base = Ingredient::factory()->create([
        'name' => 'Coconut Chia Pudding (Base)',
        'calories' => 265,
        'protein' => 3.9,
        'carbs' => 14.6,
        'fat' => 23,
    ]);

    $egg = Ingredient::factory()->create([
        'name' => 'Egg',
        'calories' => 155,
        'protein' => 12.6,
        'carbs' => 1.1,
        'fat' => 10.6,
    ]);

    $chia = Meal::factory()->create([
        'name' => ChiaBreakfastMeals::mealNames()[0],
        'meal_type' => MealType::Breakfast,
        'category' => RecipeCategory::Breakfast,
        'total_calories' => 198,
        'total_protein' => 8,
        'total_carbs' => 18,
        'total_fat' => 10,
    ]);
    $chia->ingredients()->attach($base->id, [
        'amount_grams' => 75,
        'amount' => 75,
        'unit' => 'g',
    ]);

    $omelet = Meal::factory()->create([
        'name' => SavoryEggBreakfastMeals::mealNames()[0],
        'meal_type' => MealType::Breakfast,
        'category' => RecipeCategory::Breakfast,
        'total_calories' => 305,
        'total_protein' => 15,
        'total_carbs' => 8,
        'total_fat' => 22,
    ]);
    $omelet->ingredients()->attach($egg->id, [
        'amount_grams' => 100,
        'amount' => 100,
        'unit' => 'g',
    ]);

    $profile = CustomerProfile::factory()->create([
        'daily_calorie_target' => $tier,
        'protein_percentage' => 40,
        'carb_percentage' => 30,
        'fat_percentage' => 30,
    ]);

    $options = ['plan_tier' => (float) $tier, 'craft_key' => 'full'];

    $adaptedChia = AdaptedMenuBuilder::adaptMealForProfile($profile, $chia->fresh(['ingredients']), $options);
    $adaptedEgg = AdaptedMenuBuilder::adaptMealForProfile($profile, $omelet->fresh(['ingredients']), $options);

    expect((float) $adaptedChia['adapted_nutrition']['calories'])->toEqualWithDelta($breakfastTarget, 1.0)
        ->and((float) $adaptedEgg['adapted_nutrition']['calories'])->toEqualWithDelta($breakfastTarget, 1.0);
})->with([
    [1000, 200.0],
    [1200, 200.0],
    [1500, 300.0],
    [1800, 400.0],
    [2000, 450.0],
]);
