<?php

use App\Enums\MealPlanSchemaType;
use App\Enums\MealPlanSlotType;
use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\User;

test('guests cannot store a meal plan from the library page', function (): void {
    $this->post(route('admin.meal-plan-library.store'))->assertRedirect();
});

test('authenticated users can store a weekly structured meal plan from scheduler slots', function (): void {
    $user = User::factory()->create();

    $mealsBySlot = [];
    foreach (MealPlanSlotType::daySlotTemplate() as [$slotType, $slotIndex]) {
        $category = $slotType->recipeCategory();
        $key = $slotType->value.'_'.$slotIndex;
        if (! isset($mealsBySlot[$key])) {
            $mealsBySlot[$key] = Meal::factory()->create([
                'name' => ucfirst($slotType->value).' fixture '.$slotIndex,
                'category' => $category,
                'meal_type' => MealType::fromRecipeCategory($category),
            ]);
        }
    }

    $slots = [];
    foreach (range(1, 7) as $dayNumber) {
        foreach (MealPlanSlotType::daySlotTemplate() as [$slotType, $slotIndex]) {
            $key = $slotType->value.'_'.$slotIndex;
            $slots[] = [
                'day_number' => $dayNumber,
                'slot_type' => $slotType->value,
                'slot_index' => $slotIndex,
                'meal_id' => $mealsBySlot[$key]->id,
            ];
        }
    }

    $this->actingAs($user)
        ->post(route('admin.meal-plan-library.store'), [
            'name' => 'Scheduler Saved Plan',
            'goal' => 'Support balanced weekly nutrition.',
            'plan_category' => 'balanced',
            'target_daily_calories' => 2000,
            'target_daily_protein_g' => 120,
            'slots' => $slots,
        ])
        ->assertRedirect(route('admin.meal-plan-library'))
        ->assertSessionHas('success');

    $plan = MealPlan::query()->where('name', 'Scheduler Saved Plan')->first();

    expect($plan)->not->toBeNull()
        ->and($plan->schema_type)->toBe(MealPlanSchemaType::WeeklyStructured)
        ->and($plan->dayMeals()->count())->toBe(count($slots) * 2);
});

test('store meal plan validates missing scheduler slots', function (): void {
    $user = User::factory()->create();

    $breakfast = Meal::factory()->create([
        'category' => RecipeCategory::Breakfast,
        'meal_type' => MealType::Breakfast,
    ]);

    $this->actingAs($user)
        ->from(route('admin.meal-plan-library'))
        ->post(route('admin.meal-plan-library.store'), [
            'name' => 'Incomplete Plan',
            'goal' => 'Missing slots.',
            'plan_category' => 'balanced',
            'target_daily_calories' => 1800,
            'slots' => [[
                'day_number' => 1,
                'slot_type' => MealPlanSlotType::Breakfast->value,
                'slot_index' => 1,
                'meal_id' => $breakfast->id,
            ]],
        ])
        ->assertSessionHasErrors('slots');
});
