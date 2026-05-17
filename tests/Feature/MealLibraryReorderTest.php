<?php

use App\Enums\RecipeCategory;
use App\Models\Meal;
use App\Models\User;

test('guest cannot reorder meals in meal library', function () {
    $meal = Meal::query()->create([
        'name' => 'Guest Guard',
        'category' => RecipeCategory::Meal,
        'library_sort_order' => 0,
        'total_calories' => 100,
        'total_protein' => 1,
        'total_carbs' => 1,
        'total_fat' => 1,
        'total_b6' => 0,
        'total_folate' => 0,
        'total_b12' => 0,
        'total_iron' => 0,
        'total_magnesium' => 0,
        'total_fiber' => 0,
        'total_sugar' => 0,
        'total_calcium' => 0,
        'total_potassium' => 0,
        'total_sodium' => 0,
        'total_zinc' => 0,
        'total_vitamin_c' => 0,
        'total_vitamin_a' => 0,
        'total_vitamin_e' => 0,
        'total_vitamin_d' => 0,
        'total_vitamin_k' => 0,
    ]);

    $this->post(route('admin.meal-library.reorder'), ['ids' => [$meal->id]])
        ->assertRedirect();

    expect($meal->fresh()->library_sort_order)->toBe(0);
});

test('authenticated user can persist meal library sort order', function () {
    $user = User::factory()->create();

    $first = Meal::query()->create([
        'name' => 'First',
        'category' => RecipeCategory::Meal,
        'library_sort_order' => 0,
        'total_calories' => 100,
        'total_protein' => 1,
        'total_carbs' => 1,
        'total_fat' => 1,
        'total_b6' => 0,
        'total_folate' => 0,
        'total_b12' => 0,
        'total_iron' => 0,
        'total_magnesium' => 0,
        'total_fiber' => 0,
        'total_sugar' => 0,
        'total_calcium' => 0,
        'total_potassium' => 0,
        'total_sodium' => 0,
        'total_zinc' => 0,
        'total_vitamin_c' => 0,
        'total_vitamin_a' => 0,
        'total_vitamin_e' => 0,
        'total_vitamin_d' => 0,
        'total_vitamin_k' => 0,
    ]);

    $second = Meal::query()->create([
        'name' => 'Second',
        'category' => RecipeCategory::Breakfast,
        'library_sort_order' => 1,
        'total_calories' => 200,
        'total_protein' => 2,
        'total_carbs' => 2,
        'total_fat' => 2,
        'total_b6' => 0,
        'total_folate' => 0,
        'total_b12' => 0,
        'total_iron' => 0,
        'total_magnesium' => 0,
        'total_fiber' => 0,
        'total_sugar' => 0,
        'total_calcium' => 0,
        'total_potassium' => 0,
        'total_sodium' => 0,
        'total_zinc' => 0,
        'total_vitamin_c' => 0,
        'total_vitamin_a' => 0,
        'total_vitamin_e' => 0,
        'total_vitamin_d' => 0,
        'total_vitamin_k' => 0,
    ]);

    $this->actingAs($user)
        ->postJson(route('admin.meal-library.reorder'), ['ids' => [$second->id, $first->id]])
        ->assertOk()
        ->assertJson(['message' => 'Meal order saved.']);

    expect($second->fresh()->library_sort_order)->toBe(0)
        ->and($first->fresh()->library_sort_order)->toBe(1);

    $ordered = Meal::queryForMealLibrary()->pluck('name')->all();
    expect($ordered)->toBe(['Second', 'First']);
});
