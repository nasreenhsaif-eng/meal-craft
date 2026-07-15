<?php

use App\Enums\MealScalingRole as MealScalingRoleEnum;
use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\CustomerProfile;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use App\Services\Nutrition\AdaptedMenuBuilder;
use App\Services\Nutrition\DayMacroReconciliation;
use App\Services\Nutrition\MacroFirstMainMealScaler;
use App\Services\Nutrition\UserPlanCalculator;
use App\Support\MealScalingRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Dense, non-primary protein so mains can absorb fixed overshoot without the 150g meat floor.
 *
 * @return array{protein: Ingredient, carb: Ingredient, fat: Ingredient, veg: Ingredient, spice: Ingredient}
 */
function kitchenRealIngredients(): array
{
    return [
        'protein' => Ingredient::factory()->create([
            'name' => 'Whey Protein Isolate',
            'calories' => 110,
            'protein' => 25,
            'carbs' => 1,
            'fat' => 0.5,
            'usda_food_category' => 'Proteins',
        ]),
        'carb' => Ingredient::factory()->create([
            'name' => 'Cooked Quinoa (Base)',
            'calories' => 120,
            'protein' => 4,
            'carbs' => 21,
            'fat' => 2,
            'usda_food_category' => 'Grains',
        ]),
        'fat' => Ingredient::factory()->create([
            'name' => 'Olive Oil',
            'calories' => 884,
            'protein' => 0,
            'carbs' => 0,
            'fat' => 100,
            'usda_food_category' => 'Fats',
        ]),
        'veg' => Ingredient::factory()->create([
            'name' => 'Broccoli Raw',
            'calories' => 34,
            'protein' => 2.8,
            'carbs' => 7,
            'fat' => 0.4,
            'usda_food_category' => 'Vegetables',
        ]),
        'spice' => Ingredient::factory()->create([
            'name' => 'Cumin Ground',
            'calories' => 375,
            'protein' => 18,
            'carbs' => 44,
            'fat' => 22,
            'usda_food_category' => 'Spices and Herbs',
        ]),
    ];
}

/**
 * @param  array{protein: Ingredient, carb: Ingredient, fat: Ingredient, veg: Ingredient, spice: Ingredient}  $ingredients
 * @return array{breakfast: Meal, mainA: Meal, mainB: Meal, salad: Meal, dessert: Meal}
 */
function kitchenRealDayMeals(array $ingredients): array
{
    $breakfast = Meal::factory()->create([
        'name' => 'Protein Breakfast Bowl',
        'meal_type' => MealType::Breakfast,
        'category' => RecipeCategory::Breakfast,
        'total_calories' => 300,
        'total_protein' => 30,
        'total_carbs' => 18,
        'total_fat' => 10,
    ]);
    $breakfast->ingredients()->attach($ingredients['protein']->id, ['amount_grams' => 80]);
    $breakfast->ingredients()->attach($ingredients['carb']->id, ['amount_grams' => 60]);
    $breakfast->ingredients()->attach($ingredients['fat']->id, ['amount_grams' => 6]);

    $mainA = Meal::factory()->create([
        'name' => 'Quinoa Protein Bowl A',
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 520,
        'total_protein' => 48,
        'total_carbs' => 55,
        'total_fat' => 14,
    ]);
    $mainA->ingredients()->attach($ingredients['protein']->id, ['amount_grams' => 140]);
    $mainA->ingredients()->attach($ingredients['carb']->id, ['amount_grams' => 220]);
    $mainA->ingredients()->attach($ingredients['fat']->id, ['amount_grams' => 8]);
    $mainA->ingredients()->attach($ingredients['veg']->id, ['amount_grams' => 70]);
    $mainA->ingredients()->attach($ingredients['spice']->id, ['amount_grams' => 4]);

    $mainB = Meal::factory()->create([
        'name' => 'Quinoa Protein Bowl B',
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 520,
        'total_protein' => 46,
        'total_carbs' => 55,
        'total_fat' => 13,
    ]);
    $mainB->ingredients()->attach($ingredients['protein']->id, ['amount_grams' => 130]);
    $mainB->ingredients()->attach($ingredients['carb']->id, ['amount_grams' => 230]);
    $mainB->ingredients()->attach($ingredients['fat']->id, ['amount_grams' => 7]);
    $mainB->ingredients()->attach($ingredients['veg']->id, ['amount_grams' => 70]);
    $mainB->ingredients()->attach($ingredients['spice']->id, ['amount_grams' => 4]);

    $salad = Meal::factory()->create([
        'name' => 'Side Salad',
        'meal_type' => MealType::Salad,
        'category' => RecipeCategory::SideSalad,
        'total_calories' => 140,
        'total_protein' => 3,
        'total_carbs' => 10,
        'total_fat' => 8,
    ]);
    $salad->ingredients()->attach($ingredients['veg']->id, ['amount_grams' => 120]);
    $salad->ingredients()->attach($ingredients['fat']->id, ['amount_grams' => 8]);

    $dessert = Meal::factory()->create([
        'name' => 'Blueberry Walnut Greek Yogurt Chia Pudding',
        'meal_type' => MealType::Dessert,
        'category' => RecipeCategory::Dessert,
        'total_calories' => 254,
        'total_protein' => 12,
        'total_carbs' => 30,
        'total_fat' => 10,
    ]);
    $dessert->ingredients()->attach($ingredients['carb']->id, ['amount_grams' => 90]);
    $dessert->ingredients()->attach($ingredients['protein']->id, ['amount_grams' => 40]);
    $dessert->ingredients()->attach($ingredients['fat']->id, ['amount_grams' => 8]);

    return compact('breakfast', 'mainA', 'mainB', 'salad', 'dessert');
}

test('breakfast stays tier-fixed when dessert calories change', function (int $tier) {
    $profile = new CustomerProfile([
        'id' => 1,
        'daily_calorie_target' => $tier,
        'protein_percentage' => 35.0,
        'carb_percentage' => 35.0,
        'fat_percentage' => 30.0,
    ]);

    $light = UserPlanCalculator::calculateUserPlan($profile, [
        'plan_tier' => (float) $tier,
        'selected_fixed_slots' => ['side_salad', 'dessert'],
        'side_salad_calories' => 150.0,
        'dessert_calories' => 150.0,
    ]);

    $heavy = UserPlanCalculator::calculateUserPlan($profile, [
        'plan_tier' => (float) $tier,
        'selected_fixed_slots' => ['side_salad', 'dessert'],
        'side_salad_calories' => 150.0,
        'dessert_calories' => 254.0,
    ]);

    $expectedBreakfast = UserPlanCalculator::tierSlotCalories((float) $tier)['breakfast'];

    expect($light['scalable_slot_targets']['breakfast']['calories'])->toBe($expectedBreakfast)
        ->and($heavy['scalable_slot_targets']['breakfast']['calories'])->toBe($expectedBreakfast)
        ->and($heavy['scalable_slot_targets']['main_each']['calories'])
        ->toBeLessThan($light['scalable_slot_targets']['main_each']['calories']);
})->with([1000, 1200, 1500, 1800, 2000]);

test('kitchen-safe surplus trim preserves olive oil and vegetables while cutting starch', function () {
    $ingredients = kitchenRealIngredients();

    $main = Meal::factory()->create([
        'name' => 'Protein Quinoa Plate',
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 500,
        'total_protein' => 45,
        'total_carbs' => 40,
        'total_fat' => 18,
    ]);
    $main->ingredients()->attach($ingredients['protein']->id, ['amount_grams' => 140]);
    $main->ingredients()->attach($ingredients['carb']->id, ['amount_grams' => 160]);
    $main->ingredients()->attach($ingredients['fat']->id, ['amount_grams' => 14]);
    $main->ingredients()->attach($ingredients['veg']->id, ['amount_grams' => 80]);
    $main->ingredients()->attach($ingredients['spice']->id, ['amount_grams' => 4]);

    $main->loadMissing('ingredients');
    $baselineGrams = AdaptedMenuBuilder::baselineGramsByIngredientId($main);
    $currentCalories = 0.0;
    foreach ($main->ingredients as $ingredient) {
        $grams = (float) ($baselineGrams[$ingredient->id] ?? 0);
        $currentCalories += ((float) $ingredient->calories) * $grams / 100;
    }

    $targetCalories = max(50.0, $currentCalories - 120.0);
    $trimmed = MacroFirstMainMealScaler::trimStarchRolesToCalorieTarget(
        $main,
        $baselineGrams,
        $targetCalories,
        daySurplusFloor: true,
    );

    expect($trimmed[$ingredients['fat']->id])->toEqualWithDelta($baselineGrams[$ingredients['fat']->id], 0.01)
        ->and($trimmed[$ingredients['veg']->id])->toEqualWithDelta($baselineGrams[$ingredients['veg']->id], 0.01)
        ->and($trimmed[$ingredients['carb']->id])->toBeLessThan($baselineGrams[$ingredients['carb']->id])
        ->and($trimmed[$ingredients['spice']->id])->toBeLessThan($baselineGrams[$ingredients['spice']->id]);
});

test('day reconciliation trims surplus even when protein is temporarily short', function () {
    $user = User::factory()->create();
    $profile = CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 1500,
        'protein_percentage' => 45,
        'carb_percentage' => 35,
        'fat_percentage' => 20,
    ]);

    $ingredients = kitchenRealIngredients();

    $breakfast = Meal::factory()->create([
        'name' => 'Light Breakfast',
        'meal_type' => MealType::Breakfast,
        'category' => RecipeCategory::Breakfast,
        'total_calories' => 200,
        'total_protein' => 12,
        'total_carbs' => 20,
        'total_fat' => 6,
    ]);
    $breakfast->ingredients()->attach($ingredients['protein']->id, ['amount_grams' => 40]);
    $breakfast->ingredients()->attach($ingredients['carb']->id, ['amount_grams' => 60]);

    $mainA = Meal::factory()->create([
        'name' => 'Starchy Main A',
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 520,
        'total_protein' => 28,
        'total_carbs' => 70,
        'total_fat' => 10,
    ]);
    $mainA->ingredients()->attach($ingredients['protein']->id, ['amount_grams' => 70]);
    $mainA->ingredients()->attach($ingredients['carb']->id, ['amount_grams' => 280]);
    $mainA->ingredients()->attach($ingredients['fat']->id, ['amount_grams' => 6]);

    $mainB = Meal::factory()->create([
        'name' => 'Starchy Main B',
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 520,
        'total_protein' => 28,
        'total_carbs' => 70,
        'total_fat' => 10,
    ]);
    $mainB->ingredients()->attach($ingredients['protein']->id, ['amount_grams' => 70]);
    $mainB->ingredients()->attach($ingredients['carb']->id, ['amount_grams' => 280]);
    $mainB->ingredients()->attach($ingredients['fat']->id, ['amount_grams' => 6]);

    $salad = Meal::factory()->create([
        'name' => 'Side Salad',
        'meal_type' => MealType::Salad,
        'category' => RecipeCategory::SideSalad,
        'total_calories' => 150,
        'total_protein' => 3,
        'total_carbs' => 10,
        'total_fat' => 9,
    ]);
    $salad->ingredients()->attach($ingredients['veg']->id, ['amount_grams' => 100]);
    $salad->ingredients()->attach($ingredients['fat']->id, ['amount_grams' => 9]);

    $dessert = Meal::factory()->create([
        'name' => 'Heavy Chia',
        'meal_type' => MealType::Dessert,
        'category' => RecipeCategory::Dessert,
        'total_calories' => 254,
        'total_protein' => 8,
        'total_carbs' => 30,
        'total_fat' => 10,
    ]);
    $dessert->ingredients()->attach($ingredients['carb']->id, ['amount_grams' => 90]);
    $dessert->ingredients()->attach($ingredients['fat']->id, ['amount_grams' => 10]);

    $options = [
        'craft_key' => 'full',
        'plan_tier' => 1500.0,
        'day_of_week' => 2,
        'selected_fixed_slots' => ['dessert', 'side_salad'],
        'dessert_calories' => 254.0,
        'side_salad_calories' => 150.0,
        'selected_main_meal_ids' => [$mainA->id, $mainB->id],
        'selected_breakfast_meal_ids' => [$breakfast->id],
        'selected_side_salad_meal_ids' => [$salad->id],
        'selected_dessert_meal_ids' => [$dessert->id],
    ];

    $dayMenu = [
        'breakfasts' => [AdaptedMenuBuilder::adaptMealForProfile($profile, $breakfast, $options)],
        'meals' => AdaptedMenuBuilder::adaptMainMealsForProfile($profile, [$mainA, $mainB], $options),
        'sideSalads' => [AdaptedMenuBuilder::adaptMealForProfile($profile, $salad, $options)],
        'desserts' => [AdaptedMenuBuilder::adaptMealForProfile($profile, $dessert, $options)],
        'soup' => [],
    ];

    $plan = UserPlanCalculator::calculateUserPlan($profile, $options);
    foreach ($dayMenu['meals'] as $index => $adaptedMain) {
        $grams = [];
        foreach ($adaptedMain['ingredients'] as $row) {
            $grams[(int) $row['id']] = (float) $row['adapted_amount_grams'];
        }
        $grams[$ingredients['carb']->id] = ($grams[$ingredients['carb']->id] ?? 0) + 120;
        $dayMenu['meals'][$index] = AdaptedMenuBuilder::serializeScaledMealFromGrams(
            [$mainA, $mainB][$index],
            'main',
            $plan,
            $grams,
            proteinBalanced: false,
        );
    }

    $before = DayMacroReconciliation::sumDayMacros($dayMenu);
    $proteinTarget = (float) $plan['daily_macros']['protein_g'];

    expect($before['calories'])->toBeGreaterThan(1550)
        ->and($before['protein_g'])->toBeLessThan($proteinTarget - UserPlanCalculator::dayMacroTolerance()['protein_g']);

    $reconciled = DayMacroReconciliation::reconcile($profile, $dayMenu, [$mainA, $mainB], $options);
    $after = DayMacroReconciliation::sumDayMacros($reconciled['dayMenu']);

    expect($after['calories'])->toBeLessThan($before['calories']);
});

test('reconcile with heavy dessert lands near tier calories and protein across plan tiers', function (int $tier) {
    $user = User::factory()->create();
    $profile = CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => $tier,
        'protein_percentage' => 35,
        'carb_percentage' => 35,
        'fat_percentage' => 30,
    ]);

    $ingredients = kitchenRealIngredients();
    $meals = kitchenRealDayMeals($ingredients);

    $options = [
        'craft_key' => 'full',
        'plan_tier' => (float) $tier,
        'day_of_week' => 3,
        'selected_fixed_slots' => ['dessert', 'side_salad'],
        'dessert_calories' => 254.0,
        'side_salad_calories' => 140.0,
        'selected_main_meal_ids' => [$meals['mainA']->id, $meals['mainB']->id],
        'selected_breakfast_meal_ids' => [$meals['breakfast']->id],
        'selected_side_salad_meal_ids' => [$meals['salad']->id],
        'selected_dessert_meal_ids' => [$meals['dessert']->id],
    ];

    $dayMenu = [
        'breakfasts' => [AdaptedMenuBuilder::adaptMealForProfile($profile, $meals['breakfast'], $options)],
        'meals' => AdaptedMenuBuilder::adaptMainMealsForProfile($profile, [$meals['mainA'], $meals['mainB']], $options),
        'sideSalads' => [AdaptedMenuBuilder::adaptMealForProfile($profile, $meals['salad'], $options)],
        'desserts' => [AdaptedMenuBuilder::adaptMealForProfile($profile, $meals['dessert'], $options)],
        'soup' => [],
    ];

    $breakfastBefore = (float) ($dayMenu['breakfasts'][0]['adapted_nutrition']['calories']
        ?? $dayMenu['breakfasts'][0]['calories']
        ?? 0);

    $reconciled = DayMacroReconciliation::reconcile(
        $profile,
        $dayMenu,
        [$meals['mainA'], $meals['mainB']],
        $options,
    );
    $after = DayMacroReconciliation::sumDayMacros($reconciled['dayMenu']);
    $targets = UserPlanCalculator::calculateUserPlan($profile, $options)['daily_macros'];

    $breakfastAfter = (float) ($reconciled['dayMenu']['breakfasts'][0]['adapted_nutrition']['calories']
        ?? $reconciled['dayMenu']['breakfasts'][0]['calories']
        ?? 0);

    expect(abs($after['calories'] - $tier))->toBeLessThanOrEqual(UserPlanCalculator::dayCalorieTolerance())
        ->and(abs($after['protein_g'] - (float) $targets['protein_g']))->toBeLessThanOrEqual(UserPlanCalculator::dayMacroTolerance()['protein_g'])
        ->and($breakfastAfter)->toEqualWithDelta($breakfastBefore, 1.0);

    foreach ($reconciled['dayMenu']['meals'] as $index => $adaptedMain) {
        $meal = [$meals['mainA'], $meals['mainB']][$index];
        $fatId = $ingredients['fat']->id;
        $vegId = $ingredients['veg']->id;
        $beforeGrams = [];
        foreach ($dayMenu['meals'][$index]['ingredients'] as $row) {
            $beforeGrams[(int) $row['id']] = (float) $row['adapted_amount_grams'];
        }
        $afterGrams = [];
        foreach ($adaptedMain['ingredients'] as $row) {
            $afterGrams[(int) $row['id']] = (float) $row['adapted_amount_grams'];
        }

        expect($afterGrams[$fatId] ?? 0)->toEqualWithDelta($beforeGrams[$fatId] ?? 0, 0.05)
            ->and($afterGrams[$vegId] ?? 0)->toEqualWithDelta($beforeGrams[$vegId] ?? 0, 0.05)
            ->and(MealScalingRole::roleForIngredient($ingredients['fat'], $meal))->toBe(MealScalingRoleEnum::Fat)
            ->and(MealScalingRole::roleForIngredient($ingredients['veg'], $meal))->toBe(MealScalingRoleEnum::Vegetable);
    }
})->with([1000, 1200, 1500, 1800, 2000]);
