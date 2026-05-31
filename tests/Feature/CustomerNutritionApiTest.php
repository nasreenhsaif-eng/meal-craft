<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\CustomerProfile;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use App\Services\Nutrition\UserPlanCalculator;

test('guests cannot post onboarding', function () {
    $this->postJson('/api/onboarding', [])->assertUnauthorized();
});

test('guests cannot fetch adapted menu', function () {
    $this->getJson('/api/menu/adapted')->assertUnauthorized();
});

test('authenticated user can complete onboarding and receive a plan', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/onboarding', [
        'weight_kg' => 80,
        'height_cm' => 180,
        'age' => 30,
        'sex' => 'male',
        'activity_level' => 'moderate',
        'macro_split_style' => 'high_protein',
    ]);

    $response->assertCreated()
        ->assertJsonPath('profile.macro_split_style', 'high_protein')
        ->assertJsonPath('plan.fixed.calories', 450)
        ->assertJsonStructure([
            'plan' => [
                'scaling_multiplier',
                'remaining' => ['calories', 'macros'],
            ],
        ]);

    $this->assertDatabaseHas('customer_profiles', [
        'user_id' => $user->id,
        'protein_percentage' => 45.0,
        'carb_percentage' => 25.0,
        'fat_percentage' => 30.0,
    ]);
});

test('adapted menu requires completed onboarding', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/menu/adapted')
        ->assertUnprocessable();
});

test('adapted menu scales breakfast and main meals by customer multiplier', function () {
    $user = User::factory()->create();
    CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 2000,
        'protein_percentage' => 30,
        'carb_percentage' => 40,
        'fat_percentage' => 30,
    ]);

    $ingredient = Ingredient::factory()->create([
        'calories' => 100,
        'protein' => 10,
        'carbs' => 10,
        'fat' => 5,
    ]);

    $breakfast = Meal::factory()->create([
        'name' => 'Test Breakfast',
        'meal_type' => MealType::Breakfast,
        'category' => RecipeCategory::Breakfast,
        'total_calories' => 250,
        'total_protein' => 20,
        'total_carbs' => 25,
        'total_fat' => 8,
        'library_sort_order' => 1,
    ]);
    $breakfast->ingredients()->attach($ingredient->id, ['amount_grams' => 100]);

    $main = Meal::factory()->create([
        'name' => 'Test Main',
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 375,
        'total_protein' => 30,
        'total_carbs' => 35,
        'total_fat' => 12,
        'library_sort_order' => 2,
    ]);
    $main->ingredients()->attach($ingredient->id, ['amount_grams' => 150]);

    $profile = CustomerProfile::query()->where('user_id', $user->id)->first();
    $plan = UserPlanCalculator::calculateUserPlan($profile);
    $multiplier = $plan['scaling_multiplier'];

    $response = $this->actingAs($user)->getJson('/api/menu/adapted');

    $response->assertSuccessful()
        ->assertJsonPath('plan.scaling_multiplier', $multiplier);

    $scalable = collect($response->json('scalable_meals'));
    $scaledBreakfast = $scalable->firstWhere('name', 'Test Breakfast');

    expect($scaledBreakfast)->not->toBeNull()
        ->and($scaledBreakfast['is_scaled'])->toBeTrue()
        ->and((float) $scaledBreakfast['ingredients'][0]['adapted_amount_grams'])
        ->toEqual(round(100 * $multiplier, 2));
});
