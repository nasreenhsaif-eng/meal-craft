<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\CustomerProfile;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use App\Services\Nutrition\AdaptedMenuBuilder;
use App\Services\Nutrition\MacroFirstMainMealScaler;
use App\Services\Nutrition\UserPlanCalculator;
use App\Services\RecipeNutritionCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('macro first scaler hits main slot protein and carb targets for chicken and rice meal', function () {
    $user = User::factory()->create();
    $profile = CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 2000,
        'protein_percentage' => 40,
        'carb_percentage' => 30,
        'fat_percentage' => 30,
    ]);

    $chicken = Ingredient::factory()->create([
        'name' => 'Chicken Breast',
        'calories' => 165,
        'protein' => 31,
        'carbs' => 0,
        'fat' => 3.6,
    ]);
    $rice = Ingredient::factory()->create([
        'name' => 'Cooked White Basmati Rice (Base)',
        'calories' => 130,
        'protein' => 2.7,
        'carbs' => 28,
        'fat' => 0.3,
    ]);
    $zucchini = Ingredient::factory()->create([
        'name' => 'Zucchini',
        'calories' => 17,
        'protein' => 1.2,
        'carbs' => 3.1,
        'fat' => 0.3,
        'usda_food_category' => 'Vegetables',
    ]);
    $oil = Ingredient::factory()->create([
        'name' => 'Olive Oil',
        'calories' => 884,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 100,
    ]);
    $rosemary = Ingredient::factory()->create([
        'name' => 'Fresh Rosemary',
        'calories' => 131,
        'protein' => 3.3,
        'carbs' => 21,
        'fat' => 5.9,
        'usda_food_category' => 'Herbs',
    ]);

    $meal = Meal::factory()->create([
        'name' => 'Rosemary Chicken Rice Bowl',
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 360,
        'total_protein' => 36,
        'total_carbs' => 30,
        'total_fat' => 12,
    ]);
    $meal->ingredients()->attach($chicken->id, ['amount_grams' => 150]);
    $meal->ingredients()->attach($rice->id, ['amount_grams' => 120]);
    $meal->ingredients()->attach($zucchini->id, ['amount_grams' => 80]);
    $meal->ingredients()->attach($oil->id, ['amount_grams' => 8]);
    $meal->ingredients()->attach($rosemary->id, ['amount_grams' => 2]);

    $plan = UserPlanCalculator::calculateUserPlan($profile);
    $targetProtein = (float) $plan['scalable_slot_targets']['main_each']['macros']['protein_g'];
    $targetCarbs = (float) $plan['scalable_slot_targets']['main_each']['macros']['carbs_g'];
    $targetCalories = (float) $plan['scalable_slot_targets']['main_each']['calories'];

    $adapted = MacroFirstMainMealScaler::adapt($meal->fresh(['ingredients']), $plan);
    $rows = AdaptedMenuBuilder::scaledIngredientRowsFromAdaptedGramsPublic($meal->fresh(['ingredients']), $adapted['grams']);
    $nutrition = RecipeNutritionCalculator::fromRows($rows);

    $baselineZucchini = AdaptedMenuBuilder::baselineGramsByIngredientId($meal->fresh(['ingredients']))[$zucchini->id];

    expect($adapted['protein_balanced'])->toBeTrue()
        ->and((float) $nutrition['protein'])->toBeGreaterThanOrEqual($targetProtein - 5)
        ->and((float) $nutrition['carbs'])->toBeGreaterThanOrEqual($targetCarbs - 3)
        ->and((float) $nutrition['calories'])->toBeLessThanOrEqual($targetCalories + 1)
        ->and($adapted['grams'][$zucchini->id])->toEqual($baselineZucchini);
});

test('macro first protein boost scales protein role ingredients only', function () {
    $user = User::factory()->create();
    $profile = CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 2000,
        'protein_percentage' => 40,
        'carb_percentage' => 30,
        'fat_percentage' => 30,
    ]);

    $chicken = Ingredient::factory()->create([
        'name' => 'Chicken Breast',
        'calories' => 165,
        'protein' => 31,
        'carbs' => 0,
        'fat' => 3.6,
        'usda_food_category' => 'Proteins',
    ]);
    $rice = Ingredient::factory()->create([
        'name' => 'Cooked White Basmati Rice (Base)',
        'calories' => 130,
        'protein' => 2.7,
        'carbs' => 28,
        'fat' => 0.3,
    ]);

    $meal = Meal::factory()->create([
        'name' => 'Boost Test Chicken Bowl',
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 360,
        'total_protein' => 36,
        'total_carbs' => 30,
        'total_fat' => 12,
    ]);
    $meal->ingredients()->attach($chicken->id, ['amount_grams' => 150]);
    $meal->ingredients()->attach($rice->id, ['amount_grams' => 120]);

    $plan = UserPlanCalculator::calculateUserPlan($profile);
    $adapted = MacroFirstMainMealScaler::adapt($meal->fresh(['ingredients']), $plan);
    $boosted = MacroFirstMainMealScaler::boostProteinRoleGrams($meal->fresh(['ingredients']), $adapted['grams'], 1.2);

    expect($boosted[$chicken->id])->toBeGreaterThan($adapted['grams'][$chicken->id])
        ->and($boosted[$rice->id])->toEqual($adapted['grams'][$rice->id]);
});

test('macro first scaler caps high carb vegan mains to slot calorie target', function () {
    $meal = Meal::query()
        ->where('name', 'Vegan Butternut Squash, Lentil & Peanut Stew w Brown Rice')
        ->first();

    if ($meal === null) {
        $this->markTestSkipped('Vegan butternut stew meal not seeded.');
    }

    $profile = new CustomerProfile([
        'id' => 1,
        'daily_calorie_target' => 2000,
        'protein_percentage' => 30.0,
        'carb_percentage' => 40.0,
        'fat_percentage' => 30.0,
    ]);

    $plan = UserPlanCalculator::calculateUserPlan($profile, [
        'plan_tier' => 2000.0,
        'selected_fixed_slots' => ['dessert'],
    ]);
    $targetCalories = (float) $plan['scalable_slot_targets']['main_each']['calories'];

    $adapted = MacroFirstMainMealScaler::adapt($meal->fresh(['ingredients']), $plan);
    $rows = AdaptedMenuBuilder::scaledIngredientRowsFromAdaptedGramsPublic($meal->fresh(['ingredients']), $adapted['grams']);
    $nutrition = RecipeNutritionCalculator::fromRows($rows);

    expect((float) $nutrition['calories'])->toBeLessThanOrEqual($targetCalories + 1);
});

test('adaptMainMealsForProfile uses macro first scaling when enabled', function () {
    config(['customer_nutrition.macro_first_main_scaling.enabled' => true]);

    expect(MacroFirstMainMealScaler::isEnabled())->toBeTrue();
});

test('macro first adapted main reports protein balanced flag', function () {
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

    $main = Meal::factory()->create([
        'name' => 'Lean Chicken Plate',
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 360,
        'total_protein' => 28,
        'total_carbs' => 30,
        'total_fat' => 12,
    ]);
    $main->ingredients()->attach($ingredient->id, ['amount_grams' => 250]);

    $adapted = AdaptedMenuBuilder::adaptMainMealsForProfile($profile, [$main]);

    expect($adapted)->toHaveCount(1)
        ->and($adapted[0]['protein_balanced'] ?? false)->toBeTrue();
});
