<?php

use App\Enums\MealPlanLibraryCategory;
use App\Enums\MealPlanSchemaType;
use App\Enums\MealPlanSlotType;
use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Enums\UserRole;
use App\Models\CustomerCraftPlan;
use App\Models\CustomerProfile;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\User;
use App\Services\MealPlanDefaultDaySelections;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can publish a meal plan week and seed open customer slots', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    $profile = CustomerProfile::factory()->for($customer)->create([
        'daily_calorie_target' => 1500,
    ]);

    $breakfast = Meal::factory()->create([
        'category' => RecipeCategory::Breakfast,
        'meal_type' => MealType::Breakfast,
    ]);
    $main = Meal::factory()->create([
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
    ]);
    $salad = Meal::factory()->create([
        'category' => RecipeCategory::SideSalad,
        'meal_type' => MealType::Salad,
    ]);

    $plan = MealPlan::query()->create([
        'name' => 'Balanced Anti-inflammatory',
        'goal' => 'Nutrient-dense meal plan with balanced macronutrients and anti-inflammatory whole foods.',
        'schema_type' => MealPlanSchemaType::WeeklyStructured,
        'plan_category' => MealPlanLibraryCategory::NutrientDense,
        'target_total_calories' => 10500,
    ]);

    foreach ([1, 2, 3, 4, 5, 6, 7] as $dayNumber) {
        $plan->dayMeals()->createMany([
            [
                'meal_id' => $breakfast->id,
                'day_number' => $dayNumber,
                'slot_type' => MealPlanSlotType::Breakfast,
                'slot_index' => 1,
                'is_option_b' => false,
            ],
            [
                'meal_id' => $main->id,
                'day_number' => $dayNumber,
                'slot_type' => MealPlanSlotType::Main,
                'slot_index' => 1,
                'is_option_b' => false,
            ],
            [
                'meal_id' => $salad->id,
                'day_number' => $dayNumber,
                'slot_type' => MealPlanSlotType::Salad,
                'slot_index' => 1,
                'is_option_b' => false,
            ],
        ]);
    }

    $selections = [];
    foreach ([1, 2, 3, 4, 5, 6, 7] as $dayNumber) {
        $selections[$dayNumber] = [
            'breakfasts' => [$breakfast->id],
            'meals' => [$main->id],
            'sideSalads' => [$salad->id],
            'desserts' => [],
            'soup' => [],
        ];
    }

    $this->actingAs($admin)
        ->put(route('admin.meal-plan-library.default-selections', $plan), [
            'selections' => $selections,
            'description' => 'Nutrient-dense meal plan with balanced macronutrients and anti-inflammatory whole foods.',
            'published_starts_on' => '2026-09-27',
            'published_ends_on' => '2026-10-03',
        ])
        ->assertRedirect(route('admin.meal-plan-library.show', $plan));

    $plan->refresh();

    expect($plan->published_starts_on?->toDateString())->toBe('2026-09-27')
        ->and($plan->published_ends_on?->toDateString())->toBe('2026-10-03')
        ->and(MealPlanDefaultDaySelections::forDay($plan, 1)['meals'])->toBe([$main->id]);

    $draft = CustomerCraftPlan::query()
        ->where('customer_profile_id', $profile->id)
        ->whereNull('submitted_at')
        ->first();

    expect($draft)->not->toBeNull()
        ->and($draft->selected_weekdays)->not->toBeEmpty();
});

test('publish skips customers who already chose meals for the week', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    $profile = CustomerProfile::factory()->for($customer)->create([
        'daily_calorie_target' => 1500,
    ]);

    CustomerCraftPlan::query()->create([
        'customer_profile_id' => $profile->id,
        'craft_key' => 'full',
        'week_duration' => 5,
        'selected_weekdays' => [1, 2, 3, 4, 5],
        'submitted_at' => '2026-09-26 10:00:00',
    ]);

    $breakfast = Meal::factory()->create([
        'category' => RecipeCategory::Breakfast,
        'meal_type' => MealType::Breakfast,
    ]);

    $plan = MealPlan::query()->create([
        'name' => 'Balanced Anti-inflammatory',
        'goal' => 'Goal',
        'schema_type' => MealPlanSchemaType::WeeklyStructured,
        'plan_category' => MealPlanLibraryCategory::NutrientDense,
        'target_total_calories' => 10500,
    ]);

    $plan->dayMeals()->create([
        'meal_id' => $breakfast->id,
        'day_number' => 1,
        'slot_type' => MealPlanSlotType::Breakfast,
        'slot_index' => 1,
        'is_option_b' => false,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.meal-plan-library.default-selections', $plan), [
            'selections' => [
                1 => [
                    'breakfasts' => [$breakfast->id],
                    'meals' => [],
                    'sideSalads' => [],
                    'desserts' => [],
                    'soup' => [],
                ],
            ],
            'published_starts_on' => '2026-09-27',
            'published_ends_on' => '2026-10-03',
        ])
        ->assertRedirect();

    expect(
        CustomerCraftPlan::query()
            ->where('customer_profile_id', $profile->id)
            ->whereNull('submitted_at')
            ->count()
    )->toBe(0);
});
