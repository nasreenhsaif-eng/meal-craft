<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;

test('authenticated user can update a meal from the meal library form', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::factory()->create([
        'name' => 'Update Test Rice',
        'is_verified' => true,
        'calories' => 100,
        'protein' => 2,
        'carbs' => 22,
        'fat' => 0.5,
    ]);

    $meal = Meal::query()->create([
        'name' => 'Before update name',
        'description' => 'Old',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::fromRecipeCategory(RecipeCategory::Meal),
        'total_calories' => 100,
        'total_protein' => 2,
        'total_carbs' => 22,
        'total_fat' => 0.5,
        'nutrition_aggregates_synced' => false,
    ]);

    $meal->ingredients()->attach($ingredient->id, ['amount_grams' => 50, 'amount' => 50, 'unit' => 'g']);

    $this->actingAs($user)
        ->post(route('admin.meal-library.update', $meal), [
            'name' => 'After update name',
            'total_calories' => 180,
            'total_protein' => 3,
            'total_carbs' => 30,
            'total_fat' => 1,
            'category' => 'Meal',
            'description' => 'Updated instructions',
            'ingredients' => [
                [
                    'ingredient_id' => $ingredient->id,
                    'name' => $ingredient->name,
                    'amount_grams' => 100,
                ],
            ],
        ])
        ->assertRedirect(route('admin.meal-library'))
        ->assertSessionHas('success', __('Meal updated successfully.'));

    $meal->refresh();
    expect($meal->name)->toBe('After update name')
        ->and((string) $meal->description)->toBe('Updated instructions');
});

test('meal library update can set bulk recipe fields', function () {
    $user = User::factory()->create();

    $meal = Meal::query()->create([
        'name' => 'Plain meal',
        'description' => null,
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::fromRecipeCategory(RecipeCategory::Meal),
        'total_calories' => 200,
        'total_protein' => 10,
        'total_carbs' => 20,
        'total_fat' => 5,
        'is_bulk' => false,
        'servings_count' => null,
        'nutrition_aggregates_synced' => false,
    ]);

    $this->actingAs($user)
        ->post(route('admin.meal-library.update', $meal), [
            'name' => 'Plain meal',
            'total_calories' => 50,
            'total_protein' => 5,
            'total_carbs' => 8,
            'total_fat' => 2,
            'category' => 'Meal',
            'is_bulk' => true,
            'servings_count' => 6,
        ])
        ->assertRedirect(route('admin.meal-library'))
        ->assertSessionHas('success');

    $meal->refresh();
    expect($meal->is_bulk)->toBeTrue()
        ->and((float) $meal->servings_count)->toBe(6.0)
        ->and((float) $meal->total_calories)->toBe(50.0);
});

test('meal library store accepts duplicate submission context flash message', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('admin.meal-library.store'), [
            'name' => 'Duplicate context meal',
            'total_calories' => 200,
            'total_protein' => 10,
            'total_carbs' => 20,
            'total_fat' => 5,
            'category' => 'Meal',
            'submission_context' => 'duplicate',
        ])
        ->assertRedirect(route('admin.meal-library'))
        ->assertSessionHas('success', __('New meal version saved successfully.'));
});
