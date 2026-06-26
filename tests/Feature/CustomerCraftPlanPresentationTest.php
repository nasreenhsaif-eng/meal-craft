<?php

use App\Enums\CustomerCraftMealSlot;
use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\CustomerCraftPlan;
use App\Models\CustomerCraftPlanDay;
use App\Models\CustomerCraftPlanDayMeal;
use App\Models\CustomerProfile;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use App\Services\CustomerCraftPlanPresentationService;
use App\Services\Nutrition\AdaptedMenuBuilder;
use App\Services\Nutrition\UserPlanCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('craft plan summary presents profile-scaled breakfast macros', function () {
    $user = User::factory()->create();
    $profile = CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 1500,
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

    $breakfast = Meal::factory()->create([
        'name' => 'Scaled Breakfast Summary',
        'meal_type' => MealType::Breakfast,
        'category' => RecipeCategory::Breakfast,
        'total_calories' => 250,
        'total_protein' => 20,
        'total_carbs' => 25,
        'total_fat' => 8,
        'nutrition_aggregates_synced' => true,
        'library_sort_order' => 1,
    ]);
    $breakfast->ingredients()->attach($ingredient->id, ['amount_grams' => 100]);

    $plan = CustomerCraftPlan::query()->create([
        'customer_profile_id' => $profile->id,
        'craft_key' => 'full',
        'week_duration' => 1,
        'selected_weekdays' => [1],
        'submitted_at' => now(),
    ]);

    $day = CustomerCraftPlanDay::query()->create([
        'customer_craft_plan_id' => $plan->id,
        'day_of_week' => 1,
        'include_soup' => false,
    ]);

    CustomerCraftPlanDayMeal::query()->create([
        'customer_craft_plan_day_id' => $day->id,
        'meal_id' => $breakfast->id,
        'slot' => CustomerCraftMealSlot::Breakfast,
        'position' => 1,
    ]);

    $nutritionPlan = UserPlanCalculator::calculateUserPlan($profile);
    $adaptedBreakfast = AdaptedMenuBuilder::adaptMealForProfile($profile, $breakfast, ['craft_key' => 'full']);
    $expectedCalories = (int) round((float) ($adaptedBreakfast['adapted_nutrition']['calories'] ?? 0));
    $expectedMultiplier = AdaptedMenuBuilder::mealScalingMultiplier($breakfast, 'breakfast', $nutritionPlan);

    $summary = app(CustomerCraftPlanPresentationService::class)->presentSummary($plan->fresh(['days.meals.meal.ingredients', 'customerProfile']), 1500);

    $presentedBreakfast = $summary['days'][0]['categories']['breakfasts'][0];

    expect($presentedBreakfast['macros']['calories'])->toBe($expectedCalories)
        ->and($presentedBreakfast['macros']['calories'])->not->toBe(250)
        ->and((float) ($presentedBreakfast['scalingMultiplier'] ?? 1))->toEqual($expectedMultiplier);
});

test('craft plan summary protein-balances non-vegan mains when paired with a vegan main', function () {
    $user = User::factory()->create();
    $profile = CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 1500,
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

    $veganMain = Meal::factory()->create([
        'name' => 'Summary Vegan Main',
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'diet_tags' => ['Vegan'],
        'total_calories' => 360,
        'total_protein' => 18,
        'total_carbs' => 45,
        'total_fat' => 12,
        'nutrition_aggregates_synced' => true,
        'library_sort_order' => 1,
    ]);
    $veganMain->ingredients()->attach($ingredient->id, ['amount_grams' => 200]);

    $chickenMain = Meal::factory()->create([
        'name' => 'Summary Chicken Main',
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 360,
        'total_protein' => 36,
        'total_carbs' => 30,
        'total_fat' => 12,
        'nutrition_aggregates_synced' => true,
        'library_sort_order' => 2,
    ]);
    $chickenMain->ingredients()->attach($ingredient->id, ['amount_grams' => 200]);

    $plan = CustomerCraftPlan::query()->create([
        'customer_profile_id' => $profile->id,
        'craft_key' => 'full',
        'week_duration' => 1,
        'selected_weekdays' => [1],
        'submitted_at' => now(),
    ]);

    $day = CustomerCraftPlanDay::query()->create([
        'customer_craft_plan_id' => $plan->id,
        'day_of_week' => 1,
        'include_soup' => false,
    ]);

    foreach ([$veganMain, $chickenMain] as $index => $meal) {
        CustomerCraftPlanDayMeal::query()->create([
            'customer_craft_plan_day_id' => $day->id,
            'meal_id' => $meal->id,
            'slot' => CustomerCraftMealSlot::Main,
            'position' => $index + 1,
        ]);
    }

    $summary = app(CustomerCraftPlanPresentationService::class)->presentSummary($plan->fresh(['days.meals.meal.ingredients', 'customerProfile']), 1500);

    $presentedMains = collect($summary['days'][0]['categories']['meals']);
    $vegan = $presentedMains->firstWhere('title', 'Summary Vegan Main');
    $chicken = $presentedMains->firstWhere('title', 'Summary Chicken Main');

    expect($vegan)->not->toBeNull()
        ->and($chicken)->not->toBeNull()
        ->and($vegan['proteinBalanced'] ?? false)->toBeFalse()
        ->and($chicken['proteinBalanced'] ?? false)->toBeTrue()
        ->and((float) $chicken['macros']['protein'])->toBeGreaterThan((float) $vegan['macros']['protein']);
});
