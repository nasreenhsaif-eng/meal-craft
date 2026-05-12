<?php

use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;

test('authenticated user can store a meal from the meal library form', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('admin.meal-library.store'), [
            'name' => 'Pest library meal',
            'total_calories' => 250,
            'total_protein' => 12,
            'total_carbs' => 30,
            'total_fat' => 8,
            'category' => 'Meal',
            'meal_plan_tag' => 'Balanced',
            'diet_tags' => ['Vegan', 'Gluten-free'],
            'cycle_phase' => 'luteal',
            'description' => 'Test instructions',
            'highlight' => 'A highlight',
        ])
        ->assertRedirect(route('admin.meal-library'))
        ->assertSessionHas('success');

    $meal = Meal::query()->where('name', 'Pest library meal')->firstOrFail();
    expect($meal->total_calories)->toBe(250.0)
        ->and($meal->meal_plan_tag)->toBe('Balanced')
        ->and($meal->diet_tags)->toBe(['Vegan', 'Gluten-free'])
        ->and($meal->diet_type)->toBeNull()
        ->and($meal->nutrition_aggregates_synced)->toBeFalse();
});

test('guests cannot post to meal library store', function () {
    $this->post(route('admin.meal-library.store'), [
        'name' => 'X',
        'total_calories' => 1,
        'category' => 'Meal',
    ])->assertRedirect();
});

test('meal library store attaches a verified ingredient by ingredient_id and grams', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::factory()->create([
        'name' => 'Pest Id Linked Ingredient',
        'is_verified' => true,
        'calories' => 50,
        'protein' => 1,
        'carbs' => 10,
        'fat' => 0,
    ]);

    $this->actingAs($user)
        ->post(route('admin.meal-library.store'), [
            'name' => 'Pest meal with id line',
            'total_calories' => 100,
            'total_protein' => 2,
            'total_carbs' => 20,
            'total_fat' => 0,
            'category' => 'Meal',
            'ingredients' => [
                [
                    'ingredient_id' => $ingredient->id,
                    'name' => 'Pest Id Linked Ingredient',
                    'amount_grams' => 200,
                ],
            ],
        ])
        ->assertRedirect(route('admin.meal-library'))
        ->assertSessionHas('success');

    $meal = Meal::query()->where('name', 'Pest meal with id line')->firstOrFail();
    $meal->load('ingredients');
    expect($meal->ingredients)->toHaveCount(1)
        ->and((float) $meal->ingredients->first()->pivot->amount_grams)->toBe(200.0)
        ->and($meal->nutrition_aggregates_synced)->toBeTrue()
        ->and((float) $meal->total_calories)->toBe(100.0);
});

test('meal library store persists safety tags and aggregated nutrition from ingredients', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::factory()->create([
        'name' => 'Pest Iron C Ingredient',
        'is_verified' => true,
        'calories' => 100,
        'protein' => 5,
        'carbs' => 10,
        'fat' => 2,
        'iron' => 5.0,
        'b6' => 0,
        'b9_folate' => 0,
        'b12' => 0,
        'magnesium' => 0,
        'common_allergens' => ['peanuts'],
        'micronutrients' => ['vitamin_c' => 30.0],
    ]);

    $this->actingAs($user)
        ->post(route('admin.meal-library.store'), [
            'name' => 'Pest SC safety meal',
            'total_calories' => 1,
            'total_protein' => 1,
            'total_carbs' => 1,
            'total_fat' => 1,
            'category' => 'Meal',
            'ingredients' => [
                [
                    'ingredient_id' => $ingredient->id,
                    'name' => 'Pest Iron C Ingredient',
                    'amount_grams' => 100,
                ],
            ],
        ])
        ->assertRedirect(route('admin.meal-library'))
        ->assertSessionHas('success');

    $meal = Meal::query()->where('name', 'Pest SC safety meal')->firstOrFail();
    expect($meal->safety_alert_tags)->toContain('Contains: Peanuts')
        ->and($meal->nutrition_aggregates_synced)->toBeTrue()
        ->and($meal->sickle_cell_program_highlight)->toBeTrue()
        ->and((float) $meal->total_calories)->toBe(100.0)
        ->and((float) $meal->total_iron)->toBe(5.0)
        ->and((float) $meal->total_vitamin_c)->toBe(30.0);
});
