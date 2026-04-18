<?php

use App\Enums\MealPlanLibraryCategory;
use App\Enums\MealPlanSchemaType;
use App\Enums\MealPlanSlotType;
use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\MealPlanDayMeal;
use App\Services\MealPlanService;

it('redirects guests from the four-week meal plans page', function () {
    $this->get(route('meal-plans.four-week'))->assertRedirect(route('login'));
});

it('auto-fills a four-week plan with the correct number of slot rows', function () {
    foreach (RecipeCategory::cases() as $category) {
        Meal::query()->create([
            'name' => 'Test '.$category->value,
            'category' => $category,
            'meal_type' => MealType::fromRecipeCategory($category),
            'total_calories' => 100,
            'total_protein' => 10,
            'total_carbs' => 10,
            'total_fat' => 5,
        ]);
    }

    $plan = MealPlan::query()->create([
        'name' => 'Spring',
        'goal' => 'Balance',
        'schema_type' => MealPlanSchemaType::FourWeek,
        'target_total_calories' => 28000,
        'target_total_protein_g' => 1400,
    ]);

    $service = app(MealPlanService::class);
    $result = $service->autoFillFourWeekPlan($plan);

    expect($result->ok)->toBeTrue();

    $expectedRows = 28 * MealPlanSlotType::slotsPerDayPerOption() * 2;
    expect(MealPlanDayMeal::query()->where('meal_plan_id', $plan->id)->count())->toBe($expectedRows);
});

it('can swap a slot meal when the replacement matches the slot category', function () {
    $breakfastA = Meal::query()->create([
        'name' => 'Oats A',
        'category' => RecipeCategory::Breakfast,
        'meal_type' => MealType::Breakfast,
        'total_calories' => 300,
        'total_protein' => 12,
        'total_carbs' => 40,
        'total_fat' => 8,
    ]);

    $breakfastB = Meal::query()->create([
        'name' => 'Oats B',
        'category' => RecipeCategory::Breakfast,
        'meal_type' => MealType::Breakfast,
        'total_calories' => 310,
        'total_protein' => 13,
        'total_carbs' => 41,
        'total_fat' => 9,
    ]);

    $plan = MealPlan::query()->create([
        'name' => 'Swap test',
        'goal' => 'Test',
        'schema_type' => MealPlanSchemaType::FourWeek,
        'target_total_calories' => 28000,
    ]);

    MealPlanDayMeal::query()->create([
        'meal_plan_id' => $plan->id,
        'meal_id' => $breakfastA->id,
        'day_number' => 1,
        'slot_type' => MealPlanSlotType::Breakfast->value,
        'slot_index' => 1,
        'is_option_b' => false,
    ]);

    $service = app(MealPlanService::class);
    $service->updateSlotMeal($plan, 1, MealPlanSlotType::Breakfast, 1, false, $breakfastB->id);

    $assignment = MealPlanDayMeal::query()->where('meal_plan_id', $plan->id)->firstOrFail();

    expect($assignment->meal_id)->toBe($breakfastB->id);
});

it('rejects swapping a slot when the meal category does not match', function () {
    $breakfast = Meal::query()->create([
        'name' => 'Oats',
        'category' => RecipeCategory::Breakfast,
        'meal_type' => MealType::Breakfast,
        'total_calories' => 300,
        'total_protein' => 12,
        'total_carbs' => 40,
        'total_fat' => 8,
    ]);

    $soupMeal = Meal::query()->create([
        'name' => 'Broth',
        'category' => RecipeCategory::Soup,
        'meal_type' => MealType::Soup,
        'total_calories' => 80,
        'total_protein' => 4,
        'total_carbs' => 8,
        'total_fat' => 2,
    ]);

    $plan = MealPlan::query()->create([
        'name' => 'Mismatch',
        'goal' => 'Test',
        'schema_type' => MealPlanSchemaType::FourWeek,
        'target_total_calories' => 28000,
    ]);

    MealPlanDayMeal::query()->create([
        'meal_plan_id' => $plan->id,
        'meal_id' => $breakfast->id,
        'day_number' => 1,
        'slot_type' => MealPlanSlotType::Breakfast->value,
        'slot_index' => 1,
        'is_option_b' => false,
    ]);

    $service = app(MealPlanService::class);

    $service->updateSlotMeal($plan, 1, MealPlanSlotType::Breakfast, 1, false, $soupMeal->id);
})->throws(\InvalidArgumentException::class);

it('auto-fills a weekly structured plan for seven days', function () {
    foreach (RecipeCategory::cases() as $category) {
        Meal::query()->create([
            'name' => 'Week '.$category->value,
            'category' => $category,
            'meal_type' => MealType::fromRecipeCategory($category),
            'total_calories' => 100,
            'total_protein' => 10,
            'total_carbs' => 10,
            'total_fat' => 5,
            'total_folate' => 50,
            'total_iron' => 2,
        ]);
    }

    $plan = MealPlan::query()->create([
        'name' => 'Week test',
        'goal' => 'Test',
        'schema_type' => MealPlanSchemaType::WeeklyStructured,
        'plan_category' => MealPlanLibraryCategory::Balanced,
        'target_total_calories' => 14000,
    ]);

    $service = app(MealPlanService::class);
    $result = $service->autoFillWeeklyStructuredPlan($plan);

    expect($result->ok)->toBeTrue();

    $expectedRows = 7 * MealPlanSlotType::slotsPerDayPerOption() * 2;
    expect(MealPlanDayMeal::query()->where('meal_plan_id', $plan->id)->count())->toBe($expectedRows);
});

it('can swap a slot on a weekly structured plan', function () {
    $mainA = Meal::query()->create([
        'name' => 'Rice A',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
        'total_calories' => 400,
        'total_protein' => 15,
        'total_carbs' => 60,
        'total_fat' => 10,
    ]);

    $mainB = Meal::query()->create([
        'name' => 'Rice B',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
        'total_calories' => 410,
        'total_protein' => 16,
        'total_carbs' => 61,
        'total_fat' => 11,
    ]);

    $plan = MealPlan::query()->create([
        'name' => 'Swap weekly',
        'goal' => 'Test',
        'schema_type' => MealPlanSchemaType::WeeklyStructured,
        'plan_category' => MealPlanLibraryCategory::Balanced,
        'target_total_calories' => 7000,
    ]);

    MealPlanDayMeal::query()->create([
        'meal_plan_id' => $plan->id,
        'meal_id' => $mainA->id,
        'day_number' => 3,
        'slot_type' => MealPlanSlotType::Main->value,
        'slot_index' => 2,
        'is_option_b' => false,
    ]);

    $service = app(MealPlanService::class);
    $service->updateSlotMeal($plan, 3, MealPlanSlotType::Main, 2, false, $mainB->id);

    $assignment = MealPlanDayMeal::query()->where('meal_plan_id', $plan->id)->firstOrFail();

    expect($assignment->meal_id)->toBe($mainB->id);
});
