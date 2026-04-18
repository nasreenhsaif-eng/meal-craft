<?php

use App\Enums\MealPlanLibraryCategory;
use App\Enums\MealPlanSchemaType;
use App\Enums\MealPlanSlotType;
use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\MealPlanDayMeal;
use App\Models\User;
use Livewire\Livewire;

test('inline ingredient preview increases day core calories when amount is raised', function () {
    $user = User::factory()->create();

    $ingredient = Ingredient::query()->create([
        'name' => 'Deep edit test ingredient',
        'calories' => 100,
        'protein' => 10,
        'carbs' => 10,
        'fat' => 2,
        'density' => 1.0,
    ]);

    $meal = Meal::query()->create([
        'name' => 'Breakfast test',
        'meal_type' => MealType::Breakfast->value,
        'category' => RecipeCategory::Breakfast->value,
        'total_calories' => 100,
        'total_protein' => 10,
        'total_carbs' => 10,
        'total_fat' => 2,
    ]);

    $meal->ingredients()->attach($ingredient->id, [
        'amount' => 100,
        'unit' => 'g',
        'amount_grams' => 100,
    ]);

    $plan = MealPlan::query()->create([
        'name' => 'Deep edit plan',
        'goal' => 'Test',
        'schema_type' => MealPlanSchemaType::WeeklyStructured,
        'plan_category' => MealPlanLibraryCategory::Balanced,
    ]);

    $dayRow = MealPlanDayMeal::query()->create([
        'meal_plan_id' => $plan->id,
        'meal_id' => $meal->id,
        'day_number' => 1,
        'slot_type' => MealPlanSlotType::Breakfast->value,
        'slot_index' => 1,
        'is_option_b' => false,
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::meal-plans')
        ->set('detailsPlanId', $plan->id)
        ->set('showDetailsModal', true)
        ->set('detailsDay', 1)
        ->set('detailsOptionB', false)
        ->call('openInlineIngredientEditor', $dayRow->id);

    $before = (float) $component->instance()->detailsDayCoreNutritionTotals['calories'];

    $component->set('inlineEditRows.0.amount', 300);

    $after = (float) $component->instance()->detailsDayCoreNutritionTotals['calories'];

    expect($after)->toBeGreaterThan($before);
});
