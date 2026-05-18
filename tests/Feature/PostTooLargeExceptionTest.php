<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Meal;
use App\Models\User;

test('post too large on meal library update redirects with error for inertia requests', function () {
    $user = User::factory()->create();

    $meal = Meal::query()->create([
        'name' => 'Photo test meal',
        'description' => null,
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::fromRecipeCategory(RecipeCategory::Meal),
        'total_calories' => 100,
        'total_protein' => 5,
        'total_carbs' => 10,
        'total_fat' => 2,
        'nutrition_aggregates_synced' => false,
    ]);

    $this->actingAs($user)
        ->withHeaders([
            'X-Inertia' => 'true',
        ])
        ->withServerVariables([
            'CONTENT_LENGTH' => (string) (20 * 1024 * 1024),
        ])
        ->from(route('admin.meal-library'))
        ->post(route('admin.meal-library.update', $meal), [
            'name' => 'Photo test meal',
        ])
        ->assertRedirect(route('admin.meal-library'))
        ->assertSessionHas('error');
});
