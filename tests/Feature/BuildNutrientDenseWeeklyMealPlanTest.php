<?php

use App\Enums\MealPlanSchemaType;
use App\Enums\MealPlanSlotType;
use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Services\NutrientDenseWeeklyMealPlanBuilder;
use App\Services\NutrientDenseWeeklyRotationSchedule;

/**
 * @param  array{category?: RecipeCategory, meal_type?: MealType, calories?: float}  $overrides
 */
function nutrientDenseWeeklyPlanDeckMeal(string $name, array $overrides = []): Meal
{
    return Meal::factory()->create(array_merge([
        'name' => $name,
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
        'total_calories' => 360,
        'total_protein' => 30,
        'total_carbs' => 35,
        'total_fat' => 12,
        'library_sort_order' => 500,
    ], $overrides));
}

function seedNutrientDenseWeeklyPlanDeck(): void
{
    Ingredient::query()->create([
        'name' => 'Bone Broth (Base)',
        'usda_food_category' => 'Base Ingredient',
        'calories' => 18,
        'protein' => 3.2,
        'carbs' => 0.4,
        'fat' => 0.2,
        'micronutrients' => [],
        'is_verified' => true,
    ]);

    foreach (NutrientDenseWeeklyRotationSchedule::allScheduledMealNames() as $name) {
        nutrientDenseWeeklyPlanDeckMeal($name);
    }
}

test('nutrient dense weekly plan builder creates seven day rotating menus with twelve slots per day', function (): void {
    seedNutrientDenseWeeklyPlanDeck();

    $result = app(NutrientDenseWeeklyMealPlanBuilder::class)->build(refineRecipes: false);

    $plan = $result['plan'];
    $slotsPerDay = count(MealPlanSlotType::daySlotTemplate());

    expect($plan->schema_type)->toBe(MealPlanSchemaType::WeeklyStructured)
        ->and($plan->name)->toBe(NutrientDenseWeeklyMealPlanBuilder::PLAN_NAME)
        ->and($result['slots'])->toBe(7 * $slotsPerDay)
        ->and($plan->dayMeals()->count())->toBe($result['slots'] * 2);

    $dayOneBreakfast = $plan->dayMeals()
        ->where('day_number', 1)
        ->where('slot_type', MealPlanSlotType::Breakfast->value)
        ->where('slot_index', 1)
        ->where('is_option_b', false)
        ->first()
        ?->meal?->name;

    expect($dayOneBreakfast)->toBe('Mediterranean Omelet');
});

test('nutrient dense weekly plan stores 32/28/40 macro targets at reference tier', function (): void {
    seedNutrientDenseWeeklyPlanDeck();

    $builder = app(NutrientDenseWeeklyMealPlanBuilder::class);
    [$protein, $carbs, $fat] = $builder->referenceDailyMacros();

    expect($protein)->toBe(120.0)
        ->and($carbs)->toBe(105.0)
        ->and($fat)->toBe(66.67);
});

test('rebuilding nutrient dense weekly plan replaces existing plan with same name', function (): void {
    seedNutrientDenseWeeklyPlanDeck();

    $builder = app(NutrientDenseWeeklyMealPlanBuilder::class);
    $first = $builder->build(refineRecipes: false);
    $second = $builder->build(refineRecipes: false);

    expect(MealPlan::query()->where('name', NutrientDenseWeeklyMealPlanBuilder::PLAN_NAME)->count())->toBe(1)
        ->and($second['plan']->id)->toBe($first['plan']->id);
});

test('nutrient dense weekly plan build with skip refine creates missing egg breakfasts', function (): void {
    seedNutrientDenseWeeklyPlanDeck();

    Meal::query()->where('name', 'Butternut Squash & Eggs')->delete();

    $result = app(NutrientDenseWeeklyMealPlanBuilder::class)->build(refineRecipes: false);

    expect(Meal::queryForMealLibrary()->where('name', 'Butternut Squash & Eggs')->exists())->toBeTrue()
        ->and($result['slots'])->toBeGreaterThan(0);
});
