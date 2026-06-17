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
        ->assertJsonPath('plan.days.0.include_soup', true);

    $this->assertDatabaseHas('customer_craft_plans', [
        'customer_profile_id' => $profile->id,
        'craft_key' => 'full',
    ]);

    $this->assertDatabaseHas('customer_craft_plan_day_meals', [
        'meal_id' => $soup->id,
        'slot' => CustomerCraftMealSlot::Soup->value,
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
