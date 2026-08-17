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

    $veganIngredient = Ingredient::factory()->create([
        'name' => 'Zucchini',
        'calories' => 17,
        'protein' => 1.2,
        'carbs' => 3.1,
        'fat' => 0.3,
        'usda_food_category' => 'Vegetables',
    ]);

    $chickenIngredient = Ingredient::factory()->create([
        'name' => 'Chicken Breast',
        'calories' => 100,
        'protein' => 10,
        'carbs' => 10,
        'fat' => 5,
        'usda_food_category' => 'Proteins',
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
    $veganMain->ingredients()->attach($veganIngredient->id, ['amount_grams' => 200]);

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
    $chickenMain->ingredients()->attach($chickenIngredient->id, ['amount_grams' => 200]);

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

test('craft plan summary trims day surplus when sunday has a heavy chia dessert', function () {
    $user = User::factory()->create();
    $profile = CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 1500,
        'protein_percentage' => 35,
        'carb_percentage' => 35,
        'fat_percentage' => 30,
    ]);

    $carbIngredient = Ingredient::factory()->create([
        'name' => 'Cooked Quinoa (Base)',
        'calories' => 120,
        'protein' => 4,
        'carbs' => 21,
        'fat' => 2,
        'usda_food_category' => 'Grains',
    ]);

    $proteinIngredient = Ingredient::factory()->create([
        'name' => 'Chicken Breast',
        'calories' => 120,
        'protein' => 12,
        'carbs' => 8,
        'fat' => 6,
        'usda_food_category' => 'Proteins',
    ]);

    $fatIngredient = Ingredient::factory()->create([
        'name' => 'Olive Oil',
        'calories' => 884,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 100,
        'usda_food_category' => 'Fats',
    ]);

    $breakfast = Meal::factory()->create([
        'name' => 'Sunday Scramble',
        'meal_type' => MealType::Breakfast,
        'category' => RecipeCategory::Breakfast,
        'total_calories' => 310,
        'total_protein' => 22,
        'total_carbs' => 3,
        'total_fat' => 23,
        'nutrition_aggregates_synced' => true,
    ]);
    $breakfast->ingredients()->attach($proteinIngredient->id, ['amount_grams' => 180]);
    $breakfast->ingredients()->attach($fatIngredient->id, ['amount_grams' => 12]);

    $mainA = Meal::factory()->create([
        'name' => 'Sunday Salmon',
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 450,
        'total_protein' => 40,
        'total_carbs' => 25,
        'total_fat' => 22,
        'nutrition_aggregates_synced' => true,
    ]);
    $mainA->ingredients()->attach($proteinIngredient->id, ['amount_grams' => 220]);
    $mainA->ingredients()->attach($carbIngredient->id, ['amount_grams' => 70]);
    $mainA->ingredients()->attach($fatIngredient->id, ['amount_grams' => 10]);

    $mainB = Meal::factory()->create([
        'name' => 'Sunday Liver Bowl',
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 480,
        'total_protein' => 48,
        'total_carbs' => 40,
        'total_fat' => 14,
        'nutrition_aggregates_synced' => true,
    ]);
    $mainB->ingredients()->attach($proteinIngredient->id, ['amount_grams' => 250]);
    $mainB->ingredients()->attach($carbIngredient->id, ['amount_grams' => 110]);
    $mainB->ingredients()->attach($fatIngredient->id, ['amount_grams' => 6]);

    $salad = Meal::factory()->create([
        'name' => 'Sunday Side Salad',
        'meal_type' => MealType::Salad,
        'category' => RecipeCategory::SideSalad,
        'total_calories' => 142,
        'total_protein' => 7,
        'total_carbs' => 14,
        'total_fat' => 8,
        'nutrition_aggregates_synced' => true,
    ]);
    $salad->ingredients()->attach($carbIngredient->id, ['amount_grams' => 40]);
    $salad->ingredients()->attach($fatIngredient->id, ['amount_grams' => 5]);

    $dessert = Meal::factory()->create([
        'name' => 'Blueberry Walnut Greek Yogurt Chia Pudding',
        'meal_type' => MealType::Dessert,
        'category' => RecipeCategory::Dessert,
        'total_calories' => 254,
        'total_protein' => 17,
        'total_carbs' => 27,
        'total_fat' => 10,
        'nutrition_aggregates_synced' => true,
    ]);
    $dessert->ingredients()->attach($carbIngredient->id, ['amount_grams' => 70]);
    $dessert->ingredients()->attach($proteinIngredient->id, ['amount_grams' => 60]);
    $dessert->ingredients()->attach($fatIngredient->id, ['amount_grams' => 8]);

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

    foreach (
        [
            [$breakfast, CustomerCraftMealSlot::Breakfast, 1],
            [$mainA, CustomerCraftMealSlot::Main, 1],
            [$mainB, CustomerCraftMealSlot::Main, 2],
            [$salad, CustomerCraftMealSlot::SideSalad, 1],
            [$dessert, CustomerCraftMealSlot::Dessert, 1],
        ] as [$meal, $slot, $position]
    ) {
        CustomerCraftPlanDayMeal::query()->create([
            'customer_craft_plan_day_id' => $day->id,
            'meal_id' => $meal->id,
            'slot' => $slot,
            'position' => $position,
        ]);
    }

    $summary = app(CustomerCraftPlanPresentationService::class)->presentSummary(
        $plan->fresh(['days.meals.meal.ingredients', 'customerProfile']),
        1500,
    );

    $sunday = $summary['days'][0];
    $totalCalories = 0.0;
    $mainCalories = 0.0;

    foreach (['breakfasts', 'meals', 'sideSalads', 'desserts', 'soup'] as $bucket) {
        foreach ($sunday['categories'][$bucket] ?? [] as $mealRow) {
            $calories = (float) ($mealRow['macros']['calories'] ?? 0);
            $totalCalories += $calories;

            if ($bucket === 'meals') {
                $mainCalories += $calories;
            }
        }
    }

    // Heavy fixed dessert (~254 kcal) used to leave Sunday ~1649 with untrimmed mains.
    // Day reconciliation must pull the day toward the craft calorie target.
    expect($totalCalories)->toBeGreaterThan(0)
        ->and($totalCalories)->toBeLessThan(1600)
        ->and($mainCalories)->toBeLessThan(950);
});
