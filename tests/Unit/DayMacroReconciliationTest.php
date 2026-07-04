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

test('day macro reconciliation totals only count selected fixed carousel meals', function () {
    $user = User::factory()->create();
    $profile = CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 1500,
    ]);

    $dayMenu = [
        'breakfasts' => [['id' => 1, 'calories' => 300, 'macros' => ['protein_g' => 20, 'carbs_g' => 10, 'fat_g' => 15]]],
        'meals' => [
            ['id' => 10, 'calories' => 450, 'macros' => ['protein_g' => 40, 'carbs_g' => 30, 'fat_g' => 20]],
            ['id' => 11, 'calories' => 450, 'macros' => ['protein_g' => 40, 'carbs_g' => 30, 'fat_g' => 20]],
        ],
        'sideSalads' => [
            ['id' => 20, 'calories' => 150, 'macros' => ['protein_g' => 4, 'carbs_g' => 10, 'fat_g' => 8]],
            ['id' => 21, 'calories' => 350, 'macros' => ['protein_g' => 6, 'carbs_g' => 20, 'fat_g' => 12]],
        ],
        'desserts' => [
            ['id' => 30, 'calories' => 150, 'macros' => ['protein_g' => 5, 'carbs_g' => 15, 'fat_g' => 6]],
            ['id' => 31, 'calories' => 350, 'macros' => ['protein_g' => 8, 'carbs_g' => 25, 'fat_g' => 10]],
        ],
        'soup' => [],
    ];

    $options = [
        'selected_breakfast_meal_ids' => [1],
        'selected_main_meal_ids' => [10, 11],
        'selected_side_salad_meal_ids' => [20],
        'selected_dessert_meal_ids' => [30],
    ];

    $method = (new ReflectionClass(DayMacroReconciliation::class))->getMethod('dayMenuForMacroTotals');
    $method->setAccessible(true);
    /** @var array<string, mixed> $filtered */
    $filtered = $method->invoke(null, $dayMenu, $options);

    $allTotals = DayMacroReconciliation::sumDayMacros($dayMenu);
    $selectedTotals = DayMacroReconciliation::sumDayMacros($filtered);

    expect($allTotals['calories'])->toBeGreaterThan($selectedTotals['calories'])
        ->and($selectedTotals['calories'])->toBe(1500.0);
});

test('day macro reconciliation boosts carbs when protein is on target but day calories are short', function () {
    $user = User::factory()->create();
    $profile = CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 1500,
        'diet_protocol' => 'nutrient_dense',
        'protein_percentage' => 32,
        'carb_percentage' => 28,
        'fat_percentage' => 40,
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
        'name' => 'Salmon (Raw)',
        'calories' => 208,
        'protein' => 20,
        'carbs' => 0,
        'fat' => 13,
        'usda_food_category' => 'Proteins',
    ]);

    $breakfast = Meal::factory()->create([
        'name' => 'Mediterranean Omelet',
        'meal_type' => MealType::Breakfast,
        'category' => RecipeCategory::Breakfast,
        'total_calories' => 444,
        'total_protein' => 35,
        'total_carbs' => 8,
        'total_fat' => 30,
    ]);
    $breakfast->ingredients()->attach($proteinIngredient->id, ['amount_grams' => 200]);

    $mainA = Meal::factory()->create([
        'name' => 'Salmon Plate',
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 360,
        'total_protein' => 42,
        'total_carbs' => 18,
        'total_fat' => 12,
    ]);
    $mainA->ingredients()->attach($proteinIngredient->id, ['amount_grams' => 150]);

    $mainB = Meal::factory()->create([
        'name' => 'Salmon Quinoa Bowl',
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 360,
        'total_protein' => 42,
        'total_carbs' => 35,
        'total_fat' => 11,
    ]);
    $mainB->ingredients()->attach($proteinIngredient->id, ['amount_grams' => 120]);
    $mainB->ingredients()->attach($carbIngredient->id, ['amount_grams' => 80]);

    $dessert = Meal::factory()->create([
        'name' => 'Chia Dessert',
        'meal_type' => MealType::Dessert,
        'category' => RecipeCategory::Dessert,
        'total_calories' => 201,
        'total_protein' => 17,
        'total_carbs' => 17,
        'total_fat' => 8,
    ]);
    $dessert->ingredients()->attach($carbIngredient->id, ['amount_grams' => 60]);

    $options = [
        'craft_key' => 'full',
        'day_of_week' => 1,
        'selected_fixed_slots' => ['dessert', 'side_salad'],
        'dessert_calories' => 201,
        'side_salad_calories' => 117,
        'selected_main_meal_ids' => [$mainA->id, $mainB->id],
    ];

    $dayMenu = [
        'breakfasts' => [AdaptedMenuBuilder::adaptMealForProfile($profile, $breakfast, $options)],
        'meals' => AdaptedMenuBuilder::adaptMainMealsForProfile($profile, [$mainA, $mainB], $options),
        'sideSalads' => [AdaptedMenuBuilder::adaptMealForProfile($profile, Meal::factory()->create([
            'meal_type' => MealType::Salad,
            'category' => RecipeCategory::SideSalad,
            'total_calories' => 117,
            'total_protein' => 4,
            'total_carbs' => 10,
            'total_fat' => 6,
        ]), $options)],
        'desserts' => [AdaptedMenuBuilder::adaptMealForProfile($profile, $dessert, $options)],
        'soup' => [],
    ];

    $before = DayMacroReconciliation::sumDayMacros($dayMenu);
    $reconciled = DayMacroReconciliation::reconcile($profile, $dayMenu, [$mainA, $mainB], $options);
    $after = DayMacroReconciliation::sumDayMacros($reconciled['dayMenu']);

    if ($before['calories'] < 1450 && $before['protein_g'] >= 110) {
        expect($after['carbs_g'])->toBeGreaterThan($before['carbs_g'])
            ->and($after['calories'])->toBeGreaterThan($before['calories']);
    } else {
        expect($after['calories'])->toBeGreaterThanOrEqual($before['calories']);
    }
});
