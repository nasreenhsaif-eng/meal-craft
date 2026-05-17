<?php

use App\Models\Ingredient;
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
            ->has('dietTags.0.label')
            ->has('ingredients')
            ->has('csvTemplateUrl')
            ->has('csvExportUrl')
            ->has('csvImportUrl')
            ->has('componentPickerProfiles')
            ->has('ingredientStoreUrl')
            ->has('ingredientBulkDestroyUrl'));
});

test('admin ingredient library passes verified ingredients as flattened rows', function () {
    $user = User::factory()->create();
    Ingredient::factory()->create([
        'is_verified' => true,
        'name' => 'Library Audit Ingredient',
        'usda_food_category' => 'Spices',
        'fdc_id' => 12345,
        'micronutrients' => [
            'vitamin_a' => 10.5,
            'vitamin_c' => 2,
            'fiber' => 1.25,
        ],
    ]);
    Ingredient::factory()->create([
        'is_verified' => false,
        'name' => 'Unverified Should Not Appear',
    ]);

    $this->actingAs($user)
        ->get('/admin/ingredient-library')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/IngredientsLibrary')
            ->has('ingredients', 1)
            ->where('ingredients.0.name', 'Library Audit Ingredient')
            ->where('ingredients.0.category', 'Spices')
            ->where('ingredients.0.fdc', '12345')
            ->where('ingredients.0.vitA', 10.5)
            ->where('ingredients.0.fiber', 1.25));
});

test('admin meal library renders inertia page with cycle phase options and meal store url', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/meal-library')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/MealLibrary')
            ->has('cyclePhases')
            ->has('meals')
            ->has('ingredientProfiles')
            ->has('mealCategoryOptions')
            ->has('mealStoreUrl')
            ->has('mealBulkDestroyUrl')
            ->has('mealReorderUrl')
            ->has('csvMealCraftTemplateUrl')
            ->has('csvExportUrl')
            ->has('csvImportUrl')
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
