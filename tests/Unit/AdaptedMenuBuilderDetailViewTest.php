<?php

use App\Enums\MealPlanSchemaType;
use App\Enums\MealPlanSlotType;
use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\CustomerProfile;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\MealPlanDayMeal;
use App\Services\Nutrition\AdaptedMenuBuilder;
use App\Services\Nutrition\CraftCaloriePlanner;
use App\Services\Nutrition\ProductionWeeklyMenuSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('findAdaptedMealInDayMenu returns the matching meal payload from any bucket', function () {
    $dayMenu = [
        'breakfasts' => [['id' => 10, 'name' => 'Breakfast']],
        'meals' => [['id' => 20, 'name' => 'Main']],
        'sideSalads' => [],
        'desserts' => [],
        'soup' => [],
    ];

    expect(ProductionWeeklyMenuSchedule::findAdaptedMealInDayMenu($dayMenu, 20))
        ->toMatchArray(['id' => 20, 'name' => 'Main'])
        ->and(ProductionWeeklyMenuSchedule::findAdaptedMealInDayMenu($dayMenu, 99))->toBeNull();
});

test('adaptedMealForDetailView prefers reconciled weekday schedule over isolated adapt', function () {
    $profile = CustomerProfile::factory()->create([
        'daily_calorie_target' => 2000,
        'protein_percentage' => 35,
        'carb_percentage' => 35,
        'fat_percentage' => 30,
    ]);

    $chicken = Ingredient::factory()->create(['name' => 'Detail View Chicken']);
    $main = Meal::factory()->create([
        'name' => 'Detail View Main Plate',
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 420,
        'total_protein' => 35,
        'total_carbs' => 30,
        'total_fat' => 15,
    ]);
    $main->ingredients()->attach($chicken->id, [
        'amount_grams' => 150,
        'amount' => 150,
        'unit' => 'g',
    ]);

    $breakfast = Meal::factory()->create([
        'name' => 'Detail View Breakfast',
        'meal_type' => MealType::Breakfast,
        'category' => RecipeCategory::Breakfast,
        'total_calories' => 300,
    ]);
    $salad = Meal::factory()->create([
        'name' => 'Detail View Side Salad',
        'meal_type' => MealType::Salad,
        'category' => RecipeCategory::SideSalad,
        'total_calories' => 140,
    ]);
    $dessert = Meal::factory()->create([
        'name' => 'Chia Detail View Dessert',
        'meal_type' => MealType::Dessert,
        'category' => RecipeCategory::Dessert,
        'total_calories' => 150,
    ]);

    $plan = MealPlan::query()->create([
        'name' => 'Detail view weekly plan',
        'goal' => 'Detail view tests',
        'schema_type' => MealPlanSchemaType::WeeklyStructured,
        'plan_category' => 'balanced',
    ]);

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
            'day_number' => 2,
            'slot_type' => $slotType->value,
            'slot_index' => $slotIndex,
            'is_option_b' => false,
        ]);
    }

    config(['customer_nutrition.production_meal_plan_id' => $plan->id]);

    $buildOptions = [
        'craft_key' => CraftCaloriePlanner::CRAFT_FULL,
        'day_of_week' => 2,
        'plan_tier' => 2000,
        'selected_main_meal_ids' => [$main->id],
    ];

    $scheduled = ProductionWeeklyMenuSchedule::adaptedMealFromScheduledDay(
        $profile,
        (int) $main->id,
        $buildOptions,
    );

    expect($scheduled)->not->toBeNull();

    $scheduledCalories = (int) round((float) ($scheduled['adapted_nutrition']['calories'] ?? 0));
    $scheduledVitaminD = round((float) ($scheduled['adapted_nutrition']['vitamin_d'] ?? 0), 1);
    $scheduledChickenGrams = round((float) ($scheduled['ingredients'][0]['adapted_amount_grams'] ?? 0), 2);

    $detailViewAdapted = AdaptedMenuBuilder::adaptedMealForDetailView($profile, $main, $buildOptions);

    expect($detailViewAdapted)->not->toBeNull()
        ->and((int) round((float) ($detailViewAdapted['adapted_nutrition']['calories'] ?? 0)))->toBe($scheduledCalories)
        ->and(round((float) ($detailViewAdapted['adapted_nutrition']['vitamin_d'] ?? 0), 1))->toBe($scheduledVitaminD)
        ->and(round((float) ($detailViewAdapted['ingredients'][0]['adapted_amount_grams'] ?? 0), 2))->toBe($scheduledChickenGrams);

    $fallbackAdapted = AdaptedMenuBuilder::adaptedMealForDetailView($profile, $main, [
        'craft_key' => CraftCaloriePlanner::CRAFT_FULL,
    ]);

    $isolated = AdaptedMenuBuilder::adaptMealForProfile($profile, $main, [
        'craft_key' => CraftCaloriePlanner::CRAFT_FULL,
    ]);

    expect((int) round((float) ($fallbackAdapted['adapted_nutrition']['calories'] ?? 0)))
        ->toBe((int) round((float) ($isolated['adapted_nutrition']['calories'] ?? 0)));
});
