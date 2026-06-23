<?php

use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;

test('meal detail view api formats egg ingredients with large egg counts', function () {
    $user = User::factory()->create();

    $egg = Ingredient::factory()->create(['name' => 'Egg']);
    $meal = Meal::factory()->create(['name' => 'API Detail Hummus Egg Stack']);
    $meal->ingredients()->attach($egg->id, ['amount_grams' => 100, 'amount' => 100, 'unit' => 'g']);

    $this->actingAs($user)
        ->getJson(route('api.meals.detail-view', $meal))
        ->assertOk()
        ->assertJsonPath('detailView.ingredients.0', '2 large eggs (100g)');
});

test('meal detail view api returns persisted instructions and ingredients', function () {
    $user = User::factory()->create();

    $barberries = Ingredient::factory()->create(['name' => 'Barberries']);
    $meal = Meal::factory()->create([
        'name' => 'API Detail Sweet Potato Hash',
        'instructions' => '1. Roast sweet potato with rosemary, thyme, sea salt, and black pepper.',
        'description' => '1. Roast sweet potato with rosemary, thyme, sea salt, and black pepper.',
    ]);
    $meal->ingredients()->attach($barberries->id, ['amount_grams' => 5, 'amount' => 5, 'unit' => 'g']);

    $this->actingAs($user)
        ->getJson(route('api.meals.detail-view', $meal))
        ->assertOk()
        ->assertJsonPath('detailView.instructions.0', fn (string $step): bool => str_contains($step, 'rosemary'))
        ->assertJsonPath('editForm.ingredientRows.0.selectedName', 'Barberries');
});
