<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\CustomerProfile;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use App\Services\Nutrition\AdaptedMenuBuilder;
use App\Services\Nutrition\DayMacroReconciliation;
use App\Services\Nutrition\UserPlanCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('day macro reconciliation boosts primary mains toward daily protein target', function () {
    $user = User::factory()->create();
    $profile = CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 2000,
        'protein_percentage' => 40,
        'carb_percentage' => 30,
        'fat_percentage' => 30,
    ]);

    $ingredient = Ingredient::factory()->create([
        'name' => 'Chicken Breast',
        'calories' => 120,
        'protein' => 12,
        'carbs' => 8,
        'fat' => 6,
        'usda_food_category' => 'Proteins',
    ]);

    $breakfast = Meal::factory()->create([
        'name' => 'Savory Egg Breakfast',
        'meal_type' => MealType::Breakfast,
        'category' => RecipeCategory::Breakfast,
        'total_calories' => 450,
        'total_protein' => 30,
        'total_carbs' => 20,
        'total_fat' => 28,
    ]);
    $breakfast->ingredients()->attach($ingredient->id, ['amount_grams' => 300]);

    $mainA = Meal::factory()->create([
        'name' => 'Chicken Plate A',
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 360,
        'total_protein' => 28,
        'total_carbs' => 30,
        'total_fat' => 14,
    ]);
    $mainA->ingredients()->attach($ingredient->id, ['amount_grams' => 220]);

    $mainB = Meal::factory()->create([
        'name' => 'Chicken Plate B',
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 360,
        'total_protein' => 28,
        'total_carbs' => 30,
        'total_fat' => 14,
    ]);
    $mainB->ingredients()->attach($ingredient->id, ['amount_grams' => 220]);

    $dessert = Meal::factory()->create([
        'name' => 'Light Dessert',
        'meal_type' => MealType::Dessert,
        'category' => RecipeCategory::Dessert,
        'total_calories' => 300,
        'total_protein' => 7,
        'total_carbs' => 40,
        'total_fat' => 12,
    ]);
    $dessert->ingredients()->attach($ingredient->id, ['amount_grams' => 120]);

    $options = [
        'craft_key' => 'full',
        'day_of_week' => 1,
        'selected_fixed_slots' => ['dessert', 'side_salad'],
        'dessert_calories' => 300,
        'side_salad_calories' => 150,
        'selected_main_meal_ids' => [$mainA->id, $mainB->id],
    ];

    $plan = UserPlanCalculator::calculateUserPlan($profile, $options);
    $targetProtein = (float) $plan['daily_macros']['protein_g'];

    $dayMenu = [
        'breakfasts' => [AdaptedMenuBuilder::adaptMealForProfile($profile, $breakfast, $options)],
        'meals' => AdaptedMenuBuilder::adaptMainMealsForProfile($profile, [$mainA, $mainB], $options),
        'sideSalads' => [AdaptedMenuBuilder::adaptMealForProfile($profile, Meal::factory()->create([
            'meal_type' => MealType::Salad,
            'category' => RecipeCategory::SideSalad,
            'total_calories' => 150,
            'total_protein' => 4,
            'total_carbs' => 12,
            'total_fat' => 8,
        ]), $options)],
        'desserts' => [AdaptedMenuBuilder::adaptMealForProfile($profile, $dessert, $options)],
        'soup' => [],
    ];

    $before = DayMacroReconciliation::sumDayMacros($dayMenu);
    $reconciled = DayMacroReconciliation::reconcile($profile, $dayMenu, [$mainA, $mainB], $options);
    $after = DayMacroReconciliation::sumDayMacros($reconciled['dayMenu']);

    expect($before['protein_g'])->toBeLessThan($targetProtein - 5)
        ->and($after['protein_g'])->toBeGreaterThan($before['protein_g']);
});

test('user plan includes day macro tolerance config', function () {
    $user = User::factory()->create();
    $profile = CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 2000,
    ]);

    $plan = UserPlanCalculator::calculateUserPlan($profile);

    expect($plan['day_macro_tolerance'])->toMatchArray([
        'protein_g' => 15.0,
        'carbs_g' => 20.0,
        'fat_g' => 15.0,
    ]);
});
