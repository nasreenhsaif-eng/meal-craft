<?php

use App\Enums\MealPlanSchemaType;
use App\Enums\MealPlanSlotType;
use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\User;
use App\Support\StandardMeatPortion;
use Inertia\Testing\AssertableInertia as Assert;

test('meal plan library show heals one-gram chicken before card macros are rendered', function (): void {
    $user = User::factory()->create();

    $chicken = Ingredient::factory()->create([
        'name' => 'Rosemary Garlic Chicken (Base)',
        'calories' => 200,
        'protein' => 24,
        'carbs' => 3,
        'fat' => 10,
    ]);
    $rocca = Ingredient::factory()->create([
        'name' => 'Rocca',
        'calories' => 25,
        'protein' => 2.6,
        'carbs' => 3.7,
        'fat' => 0.4,
    ]);

    $meal = Meal::factory()->create([
        'name' => 'Rosemary Chicken Rocca Salad',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
        'total_calories' => 130,
        'total_protein' => 4,
        'total_carbs' => 10.2,
        'total_fat' => 9.4,
        'nutrition_aggregates_synced' => true,
        'library_edited_at' => now(),
    ]);
    $meal->ingredients()->sync([
        $chicken->id => ['amount_grams' => 1.0, 'amount' => 1.0, 'unit' => 'g'],
        $rocca->id => ['amount_grams' => 50, 'amount' => 50, 'unit' => 'g'],
    ]);

    $plan = MealPlan::query()->create([
        'name' => 'Collapsed Chicken Plan',
        'goal' => 'Expose 1g chicken on admin meal plan cards.',
        'schema_type' => MealPlanSchemaType::WeeklyStructured,
        'target_total_calories' => 14000,
    ]);

    $plan->dayMeals()->create([
        'meal_id' => $meal->id,
        'day_number' => 1,
        'slot_type' => MealPlanSlotType::Main,
        'slot_index' => 2,
        'is_option_b' => false,
    ]);

    $this->actingAs($user)
        ->get(route('admin.meal-plan-library.show', $plan))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/MealPlanDetail')
            ->where('days.0.categories.meals.0.title', 'Rosemary Chicken Rocca Salad')
            ->where('days.0.categories.meals.0.macros.protein', fn ($protein): bool => (float) $protein > 20.0)
            ->where('days.0.categories.meals.0.macros.calories', fn ($calories): bool => (float) $calories > 200.0));

    $meal->refresh()->load('ingredients');
    expect((float) $meal->ingredients->firstWhere('name', 'Rosemary Garlic Chicken (Base)')->pivot->amount_grams)
        ->toBe(StandardMeatPortion::GRAMS)
        ->and((float) $meal->total_protein)->toBeGreaterThan(20.0);
});
