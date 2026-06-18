<?php

use App\Enums\CustomerCraftMealSlot;
use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Enums\UserRole;
use App\Models\CustomerProfile;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('customer can submit a craft plan with per-day soup flag', function () {
    $user = User::factory()->create(['role' => UserRole::Customer]);
    $profile = CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 1500,
    ]);

    $breakfast = Meal::factory()->create([
        'meal_type' => MealType::Breakfast,
        'category' => RecipeCategory::Breakfast,
        'total_calories' => 250,
    ]);
    $main = Meal::factory()->create([
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 350,
    ]);
    $salad = Meal::factory()->create([
        'meal_type' => MealType::Salad,
        'category' => RecipeCategory::SideSalad,
        'total_calories' => 180,
    ]);
    $dessert = Meal::factory()->create([
        'meal_type' => MealType::Dessert,
        'category' => RecipeCategory::Dessert,
        'total_calories' => 170,
    ]);
    $soup = Meal::factory()->create([
        'meal_type' => MealType::Soup,
        'category' => RecipeCategory::Soup,
        'total_calories' => 140,
    ]);

    $response = $this->actingAs($user)->postJson('/api/customer/craft-plan', [
        'craft_key' => 'full',
        'week_duration' => 7,
        'selected_days' => [1],
        'days' => [
            [
                'day_of_week' => 1,
                'include_soup' => true,
                'selections' => [
                    'breakfasts' => [$breakfast->id],
                    'meals' => [$main->id],
                    'sideSalads' => [$salad->id],
                    'desserts' => [$dessert->id],
                    'soup' => [$soup->id],
                ],
            ],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('plan.craft_key', 'full')
        ->assertJsonPath('plan.days.0.include_soup', true)
        ->assertJsonPath('summary_url', route('app.meal-plan', absolute: false));

    $this->assertDatabaseHas('customer_craft_plans', [
        'customer_profile_id' => $profile->id,
        'craft_key' => 'full',
    ]);

    $this->assertDatabaseHas('customer_craft_plan_day_meals', [
        'meal_id' => $soup->id,
        'slot' => CustomerCraftMealSlot::Soup->value,
    ]);
});

test('admin staff can submit a craft plan with an auto provisioned preview profile', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $main = Meal::factory()->create([
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 350,
    ]);

    $response = $this->actingAs($admin)->postJson('/api/customer/craft-plan', [
        'craft_key' => 'day',
        'week_duration' => 1,
        'selected_days' => [1],
        'days' => [
            [
                'day_of_week' => 1,
                'include_soup' => false,
                'selections' => [
                    'meals' => [$main->id],
                ],
            ],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('summary_url', route('app.meal-plan', absolute: false));

    $profile = $admin->fresh()->customerProfile;
    expect($profile)->not->toBeNull()
        ->and($profile->daily_calorie_target)->toBe(2000);

    $this->assertDatabaseHas('customer_craft_plans', [
        'customer_profile_id' => $profile->id,
        'craft_key' => 'day',
    ]);
});

test('kitchen daily sheet returns adapted ingredient lines for scalable meals', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $customer = User::factory()->create(['role' => UserRole::Customer, 'name' => 'Jordan Lee']);
    $profile = CustomerProfile::factory()->for($customer)->create([
        'daily_calorie_target' => 2000,
    ]);

    $ingredient = Ingredient::factory()->create([
        'name' => 'Chicken Breast',
        'calories' => 100,
        'protein' => 20,
        'carbs' => 0,
        'fat' => 3,
    ]);

    $main = Meal::factory()->create([
        'name' => 'Test Main',
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 375,
    ]);
    $main->ingredients()->attach($ingredient->id, ['amount_grams' => 150]);

    $this->actingAs($customer)->postJson('/api/customer/craft-plan', [
        'craft_key' => 'business',
        'week_duration' => 1,
        'selected_days' => [2],
        'days' => [
            [
                'day_of_week' => 2,
                'include_soup' => false,
                'selections' => [
                    'meals' => [$main->id],
                ],
            ],
        ],
    ])->assertCreated();

    $monday = now()->startOfWeek(Carbon\Carbon::MONDAY);

    $response = $this->actingAs($admin)->getJson('/api/admin/kitchen/daily-sheet?date='.$monday->toDateString());

    $response->assertSuccessful()
        ->assertJsonPath('rows.0.customer_name', 'Jordan Lee')
        ->assertJsonPath('rows.0.m1', fn ($value) => str_contains((string) $value, 'Test Main'));

    $lines = $response->json('ingredient_lines');
    expect($lines)->not->toBeEmpty()
        ->and($lines[0]['ingredient'])->toBe('Chicken Breast')
        ->and((float) $lines[0]['adapted_amount_grams'])->toBeGreaterThan(129)
        ->and((float) $lines[0]['adapted_amount_grams'])->toBeLessThan(131);
});

test('guests cannot submit craft plans', function () {
    $this->postJson('/api/customer/craft-plan', [])->assertUnauthorized();
});

test('customer can view meal plan summary after submitting selections', function () {
    $user = User::factory()->create(['role' => UserRole::Customer]);
    CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 2000,
    ]);

    $breakfast = Meal::factory()->create([
        'name' => 'Sunday Oats',
        'meal_type' => MealType::Breakfast,
        'category' => RecipeCategory::Breakfast,
        'total_calories' => 250,
        'total_protein' => 20,
        'total_carbs' => 30,
        'total_fat' => 8,
    ]);
    $main = Meal::factory()->create([
        'name' => 'Grilled Chicken Bowl',
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 450,
        'total_protein' => 40,
        'total_carbs' => 35,
        'total_fat' => 12,
    ]);
    $salad = Meal::factory()->create([
        'meal_type' => MealType::Salad,
        'category' => RecipeCategory::SideSalad,
        'total_calories' => 180,
    ]);
    $dessert = Meal::factory()->create([
        'meal_type' => MealType::Dessert,
        'category' => RecipeCategory::Dessert,
        'total_calories' => 170,
    ]);

    $this->actingAs($user)->postJson('/api/customer/craft-plan', [
        'craft_key' => 'full',
        'week_duration' => 7,
        'selected_days' => [1, 2],
        'days' => [
            [
                'day_of_week' => 1,
                'include_soup' => false,
                'selections' => [
                    'breakfasts' => [$breakfast->id],
                    'meals' => [$main->id],
                    'sideSalads' => [$salad->id],
                    'desserts' => [$dessert->id],
                ],
            ],
            [
                'day_of_week' => 2,
                'include_soup' => false,
                'selections' => [
                    'breakfasts' => [$breakfast->id],
                    'meals' => [$main->id],
                    'sideSalads' => [$salad->id],
                    'desserts' => [$dessert->id],
                ],
            ],
        ],
    ])->assertCreated();

    $this->actingAs($user)
        ->get(route('app.meal-plan'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('App/MealPlanSummary')
            ->where('craftPlan.craftTitle', 'Full Craft')
            ->where('craftPlan.planTierCalories', 2000)
            ->has('craftPlan.days', 2)
            ->where('craftPlan.days.0.label', 'Sunday')
            ->where('craftPlan.days.0.categories.breakfasts.0.title', 'Sunday Oats'));
});

test('meal plan summary redirects to consultation when no plan exists', function () {
    $user = User::factory()->create(['role' => UserRole::Customer]);
    CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 1500,
    ]);

    $this->actingAs($user)
        ->get(route('app.meal-plan'))
        ->assertRedirect(route('consultation.crafted-for-you'));
});

test('customer can view meal plan summary before onboarding is marked complete', function () {
    $user = User::factory()->create(['role' => UserRole::Customer]);
    $profile = CustomerProfile::factory()->for($user)->withoutOnboarding()->create([
        'daily_calorie_target' => 2000,
    ]);

    $breakfast = Meal::factory()->create([
        'meal_type' => MealType::Breakfast,
        'category' => RecipeCategory::Breakfast,
        'total_calories' => 250,
    ]);
    $main = Meal::factory()->create([
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 450,
    ]);
    $salad = Meal::factory()->create([
        'meal_type' => MealType::Salad,
        'category' => RecipeCategory::SideSalad,
        'total_calories' => 180,
    ]);
    $dessert = Meal::factory()->create([
        'meal_type' => MealType::Dessert,
        'category' => RecipeCategory::Dessert,
        'total_calories' => 170,
    ]);

    $this->actingAs($user)->postJson('/api/customer/craft-plan', [
        'craft_key' => 'full',
        'week_duration' => 1,
        'selected_days' => [1],
        'days' => [
            [
                'day_of_week' => 1,
                'include_soup' => false,
                'selections' => [
                    'breakfasts' => [$breakfast->id],
                    'meals' => [$main->id],
                    'sideSalads' => [$salad->id],
                    'desserts' => [$dessert->id],
                ],
            ],
        ],
    ])->assertCreated();

    $this->actingAs($user)
        ->get(route('app.meal-plan'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('App/MealPlanSummary')
            ->has('craftPlan.days', 1));

    expect($profile->fresh()?->onboarding_completed_at)->toBeNull();
});

test('non-admin cannot access kitchen daily sheet', function () {
    $user = User::factory()->create(['role' => UserRole::Customer]);

    $this->actingAs($user)
        ->getJson('/api/admin/kitchen/daily-sheet')
        ->assertForbidden();
});

test('admin can open kitchen logistics page', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->get(route('admin.kitchen-logistics'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('Admin/KitchenLogistics'));
});
