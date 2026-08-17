<?php

use App\Enums\MealPlanSchemaType;
use App\Enums\MealPlanSlotType;
use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\CustomerProfile;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\MealPlanDayMeal;
use App\Services\Nutrition\ProductionWeeklyMenuSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('daysToBuildFromAdaptOptions returns a single weekday when day_of_week is set', function () {
    expect(ProductionWeeklyMenuSchedule::daysToBuildFromAdaptOptions(['day_of_week' => 3]))->toBe([3])
        ->and(ProductionWeeklyMenuSchedule::daysToBuildFromAdaptOptions([]))->toBe(range(1, 7))
        ->and(ProductionWeeklyMenuSchedule::daysToBuildFromAdaptOptions(['day_of_week' => 0]))->toBe(range(1, 7));
});

test('scheduledFullCraftByWeekday only builds the requested weekday', function () {
    $profile = CustomerProfile::factory()->create([
        'daily_calorie_target' => 1500,
        'protein_percentage' => 35,
        'carb_percentage' => 35,
        'fat_percentage' => 30,
    ]);

    $breakfast = Meal::factory()->create([
        'name' => 'Gouda & Spinach Scramble',
        'meal_type' => MealType::Breakfast,
        'category' => RecipeCategory::Breakfast,
        'total_calories' => 300,
    ]);
    $main = Meal::factory()->create([
        'name' => 'Scoped Main Plate',
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 420,
    ]);
    $salad = Meal::factory()->create([
        'name' => 'Scoped Side Salad',
        'meal_type' => MealType::Salad,
        'category' => RecipeCategory::SideSalad,
        'total_calories' => 140,
    ]);
    $dessert = Meal::factory()->create([
        'name' => 'Chia Scoped Dessert',
        'meal_type' => MealType::Dessert,
        'category' => RecipeCategory::Dessert,
        'total_calories' => 150,
    ]);

    $plan = MealPlan::query()->create([
        'name' => 'Day-scope weekly plan',
        'goal' => 'Scope build tests',
        'schema_type' => MealPlanSchemaType::WeeklyStructured,
        'plan_category' => 'balanced',
    ]);

    foreach ([1, 2] as $dayNumber) {
        foreach (
            [
                [MealPlanSlotType::Breakfast, 1, $breakfast],
                [MealPlanSlotType::Main, 1, $main],
                [MealPlanSlotType::Main, 2, $main],
                [MealPlanSlotType::Salad, 1, $salad],
                [MealPlanSlotType::Dessert, 1, $dessert],
            ] as [$slotType, $slotIndex, $meal]
        ) {
            MealPlanDayMeal::query()->create([
                'meal_plan_id' => $plan->id,
                'meal_id' => $meal->id,
                'day_number' => $dayNumber,
                'slot_type' => $slotType->value,
                'slot_index' => $slotIndex,
                'is_option_b' => false,
            ]);
        }
    }

    config(['customer_nutrition.production_meal_plan_id' => $plan->id]);

    $mondayOnly = ProductionWeeklyMenuSchedule::scheduledFullCraftByWeekday(
        $profile,
        $plan,
        [
            'craft_key' => 'full',
            'day_of_week' => 2,
        ],
    );

    expect($mondayOnly)->toHaveKeys([2])
        ->and($mondayOnly)->not->toHaveKey(1)
        ->and($mondayOnly[2]['meals'])->not->toBeEmpty();

    $fullWeek = ProductionWeeklyMenuSchedule::scheduledFullCraftByWeekday(
        $profile,
        $plan,
        ['craft_key' => 'full'],
    );

    expect($fullWeek)->toHaveKeys([1, 2]);
});
