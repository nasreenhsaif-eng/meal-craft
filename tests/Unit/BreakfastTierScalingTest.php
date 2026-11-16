<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\CustomerProfile;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\Nutrition\AdaptedMenuBuilder;
use App\Services\Nutrition\CraftCaloriePlanner;
use App\Services\Nutrition\UserPlanCalculator;
use App\Support\SavoryEggBreakfastMeals;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('savory egg breakfasts follow Full Craft library breakfast tabs and egg counts', function (int $tier, float $breakfastTarget, int $eggCount) {
    $egg = Ingredient::factory()->create([
        'name' => 'Egg',
        'calories' => 155,
        'protein' => 12.6,
        'carbs' => 1.1,
        'fat' => 10.6,
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
    $plan = CraftCaloriePlanner::applyCraftToPlan(
        UserPlanCalculator::calculateUserPlan($profile, $options),
        CraftCaloriePlanner::CRAFT_FULL,
    );

    $adaptedEgg = AdaptedMenuBuilder::adaptMealForProfile($profile, $omelet->fresh(['ingredients']), $options);

    expect($plan['scalable_slot_targets']['breakfast']['calories'])->toBe($breakfastTarget)
        ->and($adaptedEgg['savory_egg_count'])->toBe($eggCount)
        ->and((float) collect($adaptedEgg['ingredients'])->firstWhere('name', 'Egg')['adapted_amount_grams'])
        ->toEqualWithDelta($eggCount * 50.0, 0.5);
})->with([
    [1250, 300.0, 3],
    [1500, 300.0, 3],
    [1800, 400.0, 4],
    [2000, 500.0, 4],
]);
