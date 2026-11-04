<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Enums\UserRole;
use App\Models\CustomerProfile;
use App\Models\Meal;
use App\Models\User;

test('digital recipes path redirects to consultation when no craft plan exists', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create([
        'daily_calorie_target' => 1500,
    ]);

    $this->actingAs($customer)
        ->get(route('meal-plan.recipes'))
        ->assertRedirect(route('consultation.crafted-for-you'));
});

test('digital recipes path renders cook it yourself after a craft plan is submitted', function () {
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
        'fulfillment_choice' => 'diy',
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
        ->get(route('meal-plan.recipes'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('App/CookItYourself')
            ->where('craftPlan.craftTitle', 'Full Craft')
            ->where('craftPlan.planTierCalories', 2000)
            ->has('craftPlan.days', 2)
            ->where('craftPlan.days.0.categories.breakfasts.0.title', 'Sunday Oats')
            ->where('recipesUrl', route('meal-plan.recipes'))
            ->where('favouritesUrl', route('app.favourites'))
            ->where('toolsUrl', route('app.tools')));
});

test('completed customers can open favourites and tools pages', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create();

    $this->actingAs($customer)
        ->get(route('app.favourites'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('App/Favourites')
            ->where('homeUrl', route('app.home'))
            ->where('recipesUrl', route('meal-plan.recipes'))
            ->where('favouritesUrl', route('app.favourites'))
            ->where('toolsUrl', route('app.tools')));

    $this->actingAs($customer)
        ->get(route('app.tools'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('App/MyTools')
            ->where('homeUrl', route('app.home'))
            ->where('favouritesUrl', route('app.favourites'))
            ->where('toolsUrl', route('app.tools')));
});
