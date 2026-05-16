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

test('meal library update resolves base recipe suffix ingredient names', function () {
    $user = User::factory()->create();
    $carrot = Ingredient::factory()->create([
        'name' => 'Carrot, raw',
        'is_verified' => true,
        'calories' => 41,
        'protein' => 1,
        'carbs' => 10,
        'fat' => 0,
    ]);

    $broth = Ingredient::factory()->create([
        'name' => 'Vegetable Broth',
        'usda_food_category' => 'Base Ingredient',
        'is_verified' => true,
        'calories' => 0,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 0,
    ]);
    $broth->components()->sync([
        (int) $carrot->id => ['amount_grams' => 100],
    ]);

    $meal = Meal::query()->create([
        'name' => 'Broth Bowl',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::fromRecipeCategory(RecipeCategory::Meal),
        'total_calories' => 50,
        'total_protein' => 1,
        'total_carbs' => 5,
        'total_fat' => 0,
        'nutrition_aggregates_synced' => false,
    ]);

    $this->actingAs($user)
        ->post(route('admin.meal-library.update', $meal), [
            'name' => 'Broth Bowl',
            'total_calories' => 82,
            'total_protein' => 2,
            'total_carbs' => 20,
            'total_fat' => 0,
            'category' => 'Meal',
            'ingredients' => [
                [
                    'name' => 'Vegetable Broth (Base)',
                    'amount_grams' => 200,
                ],
            ],
        ])
        ->assertRedirect(route('admin.meal-library'))
        ->assertSessionHas('success');

    $meal->refresh();
    $meal->load('ingredients');

    expect($meal->ingredients)->toHaveCount(1)
        ->and((int) $meal->ingredients->first()->id)->toBe((int) $broth->id)
        ->and((float) $meal->total_calories)->toBeGreaterThan(0);
});

test('meal library update accepts meal ingredient ids that are not verified', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::factory()->create([
        'name' => 'Legacy unverified rice',
        'is_verified' => false,
        'calories' => 120,
        'protein' => 3,
        'carbs' => 25,
        'fat' => 1,
    ]);

    $meal = Meal::query()->create([
        'name' => 'Legacy meal',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::fromRecipeCategory(RecipeCategory::Meal),
        'total_calories' => 120,
        'total_protein' => 3,
        'total_carbs' => 25,
        'total_fat' => 1,
        'nutrition_aggregates_synced' => false,
    ]);
    $meal->ingredients()->attach($ingredient->id, ['amount_grams' => 80, 'amount' => 80, 'unit' => 'g']);

    $this->actingAs($user)
        ->post(route('admin.meal-library.update', $meal), [
            'name' => 'Legacy meal updated',
            'total_calories' => 150,
            'total_protein' => 4,
            'total_carbs' => 30,
            'total_fat' => 1,
            'category' => 'Meal',
            'ingredients' => [
                [
                    'ingredient_id' => $ingredient->id,
                    'name' => $ingredient->name,
                    'amount_grams' => 100,
                ],
            ],
        ])
        ->assertRedirect(route('admin.meal-library'))
        ->assertSessionHas('success');

    expect($meal->fresh()->name)->toBe('Legacy meal updated');
});

test('meal library update strips unknown meal plan tags before validation', function () {
    $user = User::factory()->create();

    $meal = Meal::query()->create([
        'name' => 'Tagged meal',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::fromRecipeCategory(RecipeCategory::Meal),
        'meal_plan_tags' => ['Retired Program Tag'],
        'total_calories' => 200,
        'total_protein' => 10,
        'total_carbs' => 20,
        'total_fat' => 5,
        'nutrition_aggregates_synced' => false,
    ]);

    $this->actingAs($user)
        ->post(route('admin.meal-library.update', $meal), [
            'name' => 'Tagged meal',
            'total_calories' => 210,
            'total_protein' => 11,
            'total_carbs' => 21,
            'total_fat' => 5,
            'category' => 'Meal',
            'meal_plan_tags' => ['Retired Program Tag', 'Balanced'],
        ])
        ->assertRedirect(route('admin.meal-library'))
        ->assertSessionHas('success');

    $meal->refresh();
    expect($meal->meal_plan_tags)->toBe(['Balanced']);
});

test('meal library update strips unknown diet tags before validation', function () {
    $user = User::factory()->create();

    $meal = Meal::query()->create([
        'name' => 'Diet tagged meal',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::fromRecipeCategory(RecipeCategory::Meal),
        'diet_tags' => ['Paleo', 'Vegan'],
        'total_calories' => 300,
        'total_protein' => 15,
        'total_carbs' => 30,
        'total_fat' => 10,
        'nutrition_aggregates_synced' => false,
    ]);

    $this->actingAs($user)
        ->post(route('admin.meal-library.update', $meal), [
            'name' => 'Diet tagged meal',
            'total_calories' => 310,
            'total_protein' => 16,
            'total_carbs' => 31,
            'total_fat' => 10,
            'category' => 'Meal',
            'diet_tags' => ['Paleo', 'Vegan'],
        ])
        ->assertRedirect(route('admin.meal-library'))
        ->assertSessionHas('success');

    $meal->refresh();
    expect($meal->diet_tags)->toBe(['Vegan']);
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
