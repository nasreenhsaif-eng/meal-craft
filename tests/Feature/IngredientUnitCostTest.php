<?php

use App\Enums\UserRole;
use App\Models\Ingredient;
use App\Models\User;

test('admins can save an ingredient unit cost in bhd', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $ingredient = Ingredient::factory()->create([
        'name' => 'Salmon fillet',
        'is_verified' => true,
        'unit_cost_bhd' => null,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.ingredient-library.update', $ingredient), [
            'name' => 'Salmon fillet',
            'calories' => 200,
            'protein' => 20,
            'carbs' => 0,
            'fat' => 12,
            'unit_cost_bhd' => 1.250,
        ])
        ->assertRedirect(route('admin.ingredient-library'));

    expect((float) $ingredient->fresh()->unit_cost_bhd)->toBe(1.25);
});

test('ingredient library includes unit cost in the page props', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    Ingredient::factory()->create([
        'name' => 'Olive oil',
        'is_verified' => true,
        'unit_cost_bhd' => 0.45,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.ingredient-library'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/IngredientsLibrary')
            ->has('ingredients', fn ($ingredients) => $ingredients
                ->where('0.unitCostBhd', 0.45)
                ->etc()));
});
