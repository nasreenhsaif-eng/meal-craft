<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\CustomerProfile;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use App\Services\Nutrition\AdaptedMenuBuilder;
use App\Services\Nutrition\UserPlanCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('adaptMainMealsForProfile boosts non-vegan mains when a vegan choice lowers combined protein', function () {
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
        'name' => 'Vegan Test Stew',
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'diet_tags' => ['Vegan'],
        'total_calories' => 360,
        'total_protein' => 18,
        'total_carbs' => 45,
        'total_fat' => 12,
        'library_sort_order' => 1,
    ]);
    $veganMain->ingredients()->attach($ingredient->id, ['amount_grams' => 200]);

    $chickenMain = Meal::factory()->create([
        'name' => 'Chicken Test Plate',
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'diet_tags' => ['High protein'],
        'total_calories' => 360,
        'total_protein' => 36,
        'total_carbs' => 30,
        'total_fat' => 12,
        'library_sort_order' => 2,
    ]);
    $chickenMain->ingredients()->attach($ingredient->id, ['amount_grams' => 200]);

    $plan = UserPlanCalculator::calculateUserPlan($profile);
    $proteinTargetEach = (float) $plan['scalable_slot_targets']['main_each']['macros']['protein_g'];

    $adapted = AdaptedMenuBuilder::adaptMainMealsForProfile($profile, [$veganMain, $chickenMain]);

    expect($adapted)->toHaveCount(2);

    $veganAdapted = collect($adapted)->firstWhere('name', 'Vegan Test Stew');
    $chickenAdapted = collect($adapted)->firstWhere('name', 'Chicken Test Plate');

    $combinedProtein = (float) $veganAdapted['adapted_nutrition']['protein']
        + (float) $chickenAdapted['adapted_nutrition']['protein'];

    expect($veganAdapted['is_vegan'])->toBeTrue()
        ->and($veganAdapted['protein_balanced'] ?? false)->toBeFalse()
        ->and($chickenAdapted['protein_balanced'] ?? false)->toBeTrue()
        ->and($chickenAdapted['scaling_multiplier'])->toBeGreaterThan(
            AdaptedMenuBuilder::mealScalingMultiplier($chickenMain, 'main', $plan),
        )
        ->and((float) $chickenAdapted['adapted_nutrition']['calories'])
        ->toBeLessThanOrEqual((float) $plan['scalable_slot_targets']['main_each']['calories'] + 1)
        ->and($combinedProtein)->toBeGreaterThan(
            (float) $veganAdapted['adapted_nutrition']['protein'] + (float) $chickenMain->total_protein,
        );
});

test('adaptMainMealsForProfile leaves protein unchanged when both mains already meet target', function () {
    $user = User::factory()->create();
    $profile = CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 1500,
        'protein_percentage' => 40,
        'carb_percentage' => 30,
        'fat_percentage' => 30,
    ]);

    $ingredient = Ingredient::factory()->create([
        'calories' => 100,
        'protein' => 20,
        'carbs' => 5,
        'fat' => 2,
    ]);

    $mainA = Meal::factory()->create([
        'name' => 'Protein Main A',
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 360,
        'total_protein' => 40,
        'total_carbs' => 25,
        'total_fat' => 10,
        'library_sort_order' => 1,
    ]);
    $mainA->ingredients()->attach($ingredient->id, ['amount_grams' => 180]);

    $mainB = Meal::factory()->create([
        'name' => 'Protein Main B',
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 360,
        'total_protein' => 40,
        'total_carbs' => 25,
        'total_fat' => 10,
        'library_sort_order' => 2,
    ]);
    $mainB->ingredients()->attach($ingredient->id, ['amount_grams' => 180]);

    $plan = UserPlanCalculator::calculateUserPlan($profile);
    $expectedMultiplierA = AdaptedMenuBuilder::mealScalingMultiplier($mainA, 'main', $plan);
    $expectedMultiplierB = AdaptedMenuBuilder::mealScalingMultiplier($mainB, 'main', $plan);

    $adapted = AdaptedMenuBuilder::adaptMainMealsForProfile($profile, [$mainA, $mainB]);

    $adaptedA = collect($adapted)->firstWhere('name', 'Protein Main A');
    $adaptedB = collect($adapted)->firstWhere('name', 'Protein Main B');

    expect($adaptedA['scaling_multiplier'])->toEqual($expectedMultiplierA)
        ->and($adaptedB['scaling_multiplier'])->toEqual($expectedMultiplierB)
        ->and($adaptedA['protein_balanced'] ?? false)->toBeFalse()
        ->and($adaptedB['protein_balanced'] ?? false)->toBeFalse();
});
