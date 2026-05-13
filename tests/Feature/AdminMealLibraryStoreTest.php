<?php

use App\Enums\CyclePhase;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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
        ->and($meal->meal_plan_tags)->toBe(['Balanced'])
        ->and($meal->cycle_phases)->toBe(['luteal'])
        ->and($meal->cycle_phase)->toBe(CyclePhase::Luteal)
        ->and($meal->diet_tags)->toBe(['Vegan', 'Gluten-free'])
        ->and($meal->diet_type)->toBeNull()
        ->and($meal->nutrition_aggregates_synced)->toBeFalse();
});

test('meal library store accepts multiple meal plan tags and cycle phases', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('admin.meal-library.store'), [
            'name' => 'Multi-tag meal',
            'total_calories' => 300,
            'total_protein' => 10,
            'total_carbs' => 20,
            'total_fat' => 12,
            'category' => 'Meal',
            'meal_plan_tags' => ['Balanced', 'Ketogenic'],
            'cycle_phases' => ['follicular', 'luteal'],
            'description' => 'Steps',
            'highlight' => 'Note',
        ])
        ->assertRedirect(route('admin.meal-library'))
        ->assertSessionHas('success');

    $meal = Meal::query()->where('name', 'Multi-tag meal')->firstOrFail();
    expect($meal->meal_plan_tags)->toBe(['Balanced', 'Ketogenic'])
        ->and($meal->cycle_phases)->toBe(['follicular', 'luteal'])
        ->and($meal->meal_plan_tag)->toBe('Balanced')
        ->and($meal->cycle_phase)->toBe(CyclePhase::Follicular);
});

test('guests cannot post to meal library store', function () {
    $this->post(route('admin.meal-library.store'), [
        'name' => 'X',
        'total_calories' => 1,
        'category' => 'Meal',
    ])->assertRedirect();
});

test('meal library store persists is_bulk and servings_count with single-serving macros', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('admin.meal-library.store'), [
            'name' => 'Bulk-stored meal',
            'total_calories' => 80,
            'total_protein' => 6,
            'total_carbs' => 10,
            'total_fat' => 4,
            'category' => 'Meal',
            'is_bulk' => true,
            'servings_count' => 4,
        ])
        ->assertRedirect(route('admin.meal-library'))
        ->assertSessionHas('success');

    $meal = Meal::query()->where('name', 'Bulk-stored meal')->firstOrFail();
    expect($meal->is_bulk)->toBeTrue()
        ->and((float) $meal->servings_count)->toBe(4.0)
        ->and((float) $meal->total_calories)->toBe(80.0)
        ->and((float) $meal->total_protein)->toBe(6.0);
});

test('meal library store requires servings_count when is_bulk is true', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('admin.meal-library.store'), [
            'name' => 'Invalid bulk meal',
            'total_calories' => 100,
            'category' => 'Meal',
            'is_bulk' => true,
        ])
        ->assertSessionHasErrors('servings_count');
});

test('meal library store persists uploaded photo to the public disk', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $photo = UploadedFile::fake()->image('meal-cover.jpg', 100, 100);

    $this->actingAs($user)
        ->post(route('admin.meal-library.store'), [
            'name' => 'Pest meal with photo',
            'total_calories' => 100,
            'total_protein' => 0,
            'total_carbs' => 0,
            'total_fat' => 0,
            'category' => 'Meal',
            'photo' => $photo,
        ])
        ->assertRedirect(route('admin.meal-library'))
        ->assertSessionHas('success');

    $meal = Meal::query()->where('name', 'Pest meal with photo')->firstOrFail();

    expect($meal->image_path)->not->toBeNull()
        ->and($meal->image_path)->toStartWith('meals/');

    Storage::disk('public')->assertExists($meal->image_path);
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
