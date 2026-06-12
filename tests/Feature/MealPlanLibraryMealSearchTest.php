<?php

use App\Enums\RecipeCategory;
use App\Models\Meal;
use App\Models\User;

test('meal plan library meal search filters by category and query', function () {
    $user = User::factory()->create();

    $breakfast = Meal::factory()->create([
        'name' => 'Sunrise oats',
        'category' => RecipeCategory::Breakfast,
    ]);
    $main = Meal::factory()->create([
        'name' => 'Herb chicken plate',
        'category' => RecipeCategory::Meal,
    ]);
    $soup = Meal::factory()->create([
        'name' => 'Sunrise tomato soup',
        'category' => RecipeCategory::Soup,
    ]);

    $this->actingAs($user)
        ->getJson(route('admin.meal-plan-library.meals.search', [
            'q' => 'sunrise',
            'categories' => [RecipeCategory::Breakfast->value],
        ]))
        ->assertOk()
        ->assertJsonPath('meals.0.id', $breakfast->id)
        ->assertJsonPath('meals.0.category', RecipeCategory::Breakfast->value)
        ->assertJsonCount(1, 'meals');

    $this->actingAs($user)
        ->getJson(route('admin.meal-plan-library.meals.search', [
            'q' => 'sunrise',
            'categories' => [
                RecipeCategory::Meal->value,
                RecipeCategory::SideSalad->value,
                RecipeCategory::Dessert->value,
                RecipeCategory::Soup->value,
            ],
        ]))
        ->assertOk()
        ->assertJsonPath('meals.0.id', $soup->id)
        ->assertJsonCount(1, 'meals');

    $this->actingAs($user)
        ->getJson(route('admin.meal-plan-library.meals.search', [
            'q' => 'chicken',
            'categories' => [RecipeCategory::Meal->value],
        ]))
        ->assertOk()
        ->assertJsonPath('meals.0.id', $main->id)
        ->assertJsonCount(1, 'meals');
});

test('meal plan library inertia page exposes meal search url', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/meal-plan-library')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/MealPlanLibrary')
            ->where('mealSearchUrl', route('admin.meal-plan-library.meals.search'))
            ->where('mealPlanStoreUrl', route('admin.meal-plan-library.store'))
            ->has('schedulerMeals')
            ->has('mealPlans'));
});
