<?php

use App\Models\Ingredient;
use App\Models\User;
use App\Services\BaseIngredientService;

test('ingredient detail view api returns base recipe components and instructions', function () {
    $user = User::factory()->create();
    $child = Ingredient::factory()->create([
        'name' => 'Cumin Powder',
        'is_verified' => true,
        'calories' => 300,
        'protein' => 10,
        'carbs' => 50,
        'fat' => 10,
    ]);

    $base = app(BaseIngredientService::class)->upsert(
        null,
        'Test Shawarma Spice Blend (Base)',
        [['ingredient_id' => $child->id, 'amount_grams' => 12]],
        48,
        [
            'description' => 'House spice blend for shawarma.',
            'instructions' => "Step 1: Toast cumin.\nStep 2: Whisk spices together.",
        ],
    );

    $this->actingAs($user)
        ->getJson(route('api.ingredients.detail-view', $base))
        ->assertOk()
        ->assertJsonPath('title', 'Test Shawarma Spice Blend (Base)')
        ->assertJsonPath('detailView.shortDescription', 'House spice blend for shawarma.')
        ->assertJsonPath('detailView.ingredients.0', '12g Cumin Powder')
        ->assertJsonPath('detailView.ingredientItems.0.isBaseRecipe', false)
        ->assertJsonPath('detailView.instructions.0', 'Toast cumin.')
        ->assertJsonPath('detailView.nutritionSubheading', 'Per 100 g totals');
});

test('ingredient detail view api returns 404 for non-base ingredients', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::factory()->create([
        'name' => 'Chicken Breast',
        'usda_food_category' => 'Proteins',
    ]);

    $this->actingAs($user)
        ->getJson(route('api.ingredients.detail-view', $ingredient))
        ->assertNotFound();
});
