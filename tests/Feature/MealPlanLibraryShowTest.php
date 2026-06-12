<?php

use App\Enums\MealPlanSchemaType;
use App\Enums\MealPlanSlotType;
use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot view a meal plan detail page', function (): void {
    $plan = MealPlan::query()->create([
        'name' => 'Guest Blocked Plan',
        'goal' => 'Test',
        'schema_type' => MealPlanSchemaType::WeeklyStructured,
        'target_total_calories' => 14000,
    ]);

    $this->get(route('admin.meal-plan-library.show', $plan))->assertRedirect();
});

test('authenticated users can view meal plan detail with day categories', function (): void {
    $user = User::factory()->create();

    $breakfast = Meal::factory()->create([
        'name' => 'Detail Breakfast',
        'category' => RecipeCategory::Breakfast,
        'meal_type' => MealType::Breakfast,
    ]);

    $main = Meal::factory()->create([
        'name' => 'Detail Main',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
    ]);

    $plan = MealPlan::query()->create([
        'name' => 'Detail Plan',
        'goal' => 'Weekly balanced rotation.',
        'schema_type' => MealPlanSchemaType::WeeklyStructured,
        'target_total_calories' => 14000,
    ]);

    $plan->dayMeals()->createMany([
        [
            'meal_id' => $breakfast->id,
            'day_number' => 1,
            'slot_type' => MealPlanSlotType::Breakfast,
            'slot_index' => 1,
            'is_option_b' => false,
        ],
        [
            'meal_id' => $main->id,
            'day_number' => 1,
            'slot_type' => MealPlanSlotType::Main,
            'slot_index' => 1,
            'is_option_b' => false,
        ],
    ]);

    $this->actingAs($user)
        ->get(route('admin.meal-plan-library.show', $plan))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/MealPlanDetail')
            ->where('mealPlan.name', 'Detail Plan')
            ->where('mealPlan.goal', 'Weekly balanced rotation.')
            ->has('days', 7)
            ->where('days.0.dayNumber', 1)
            ->where('days.0.label', 'Sun')
            ->has('days.0.categories.breakfasts', 1)
            ->has('days.0.categories.meals', 1)
            ->where('days.0.categories.breakfasts.0.title', 'Detail Breakfast')
            ->where('days.0.categories.meals.0.title', 'Detail Main')
            ->has('days.0.categories.breakfasts.0.detailView')
            ->has('libraryUrl'));
});
