<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('admin dashboard renders with hardcoded stats payload', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/dashboard')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Dashboard')
            ->has('adminName')
            ->has('adminEmail')
            ->has('stats')
            ->where('stats.totalSubmissions', 0)
            ->where('stats.totalRevenue', 0)
            ->where('stats.activeUsers', [])
            ->where('stats.customersCount', 0)
            ->where('stats.ingredientCount', 0)
            ->where('stats.mealCount', 0)
            ->where('stats.mealPlanCount', 0));
});

test('admin ingredient library renders inertia page with diet tag options', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/ingredient-library')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/IngredientsLibrary')
            ->has('dietTags')
            ->has('dietTags.0.value')
            ->has('dietTags.0.label'));
});

test('admin meal library renders inertia page with diet type and cycle phase options', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/meal-library')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/MealLibrary')
            ->has('dietTypes')
            ->has('cyclePhases')
            ->where('dietTypes.0.value', 'balanced')
            ->where('cyclePhases.0.value', 'menstrual'));
});

test('admin meal plan library renders inertia page with diet type and cycle phase options', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/meal-plan-library')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/MealPlanLibrary')
            ->has('dietTypes')
            ->has('cyclePhases'));
});

test('guests cannot access admin inertia routes', function (string $path) {
    $this->get($path)->assertRedirect();
})->with([
    '/admin/dashboard',
    '/admin/ingredient-library',
    '/admin/meal-library',
    '/admin/meal-plan-library',
]);
