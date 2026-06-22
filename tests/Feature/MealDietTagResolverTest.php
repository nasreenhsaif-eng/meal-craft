<?php

use App\Models\Ingredient;
use App\Models\Meal;
use App\Support\MealDietTagResolver;

test('chia pudding with walnuts is vegan but not nut-free', function () {
    $base = Ingredient::factory()->create([
        'name' => 'Coconut Chia Pudding (Base)',
        'usda_food_category' => 'Base Ingredient',
    ]);
    $walnuts = Ingredient::factory()->create(['name' => 'Walnuts']);
    $meal = Meal::factory()->create(['name' => 'Test Chia']);
    $meal->ingredients()->sync([
        $base->id => ['amount_grams' => 100],
        $walnuts->id => ['amount_grams' => 12],
    ]);

    $tags = MealDietTagResolver::resolveForMeal($meal->fresh(['ingredients.components']));

    expect($tags)->toContain('Vegan', 'Dairy-free', 'Gluten-free')
        ->and($tags)->not->toContain('Nut-free', 'Vegetarian');
});

test('egg breakfast is vegetarian and nut-free', function () {
    $egg = Ingredient::factory()->create(['name' => 'Egg']);
    $meal = Meal::factory()->create(['name' => 'Test Omelet']);
    $meal->ingredients()->sync([$egg->id => ['amount_grams' => 100]]);

    $tags = MealDietTagResolver::resolveForMeal($meal->fresh(['ingredients.components']));

    expect($tags)->toContain('Vegetarian', 'Dairy-free', 'Gluten-free', 'Nut-free')
        ->and($tags)->not->toContain('Vegan');
});

test('salmon plate is neither vegan nor vegetarian', function () {
    $salmon = Ingredient::factory()->create(['name' => 'Salmon (Raw)']);
    $meal = Meal::factory()->create(['name' => 'Test Salmon']);
    $meal->ingredients()->sync([$salmon->id => ['amount_grams' => 120]]);

    $tags = MealDietTagResolver::resolveForMeal($meal->fresh(['ingredients.components']));

    expect($tags)->toContain('Dairy-free', 'Gluten-free', 'Nut-free')
        ->and($tags)->not->toContain('Vegan', 'Vegetarian');
});

test('butternut squash and almond butter do not trigger dairy flags', function () {
    $squash = Ingredient::factory()->create(['name' => 'Butternut Squash']);
    $almondButter = Ingredient::factory()->create(['name' => 'Almond Butter']);
    $meal = Meal::factory()->create(['name' => 'Test Squash']);
    $meal->ingredients()->sync([
        $squash->id => ['amount_grams' => 80],
        $almondButter->id => ['amount_grams' => 10],
    ]);

    $tags = MealDietTagResolver::resolveForMeal($meal->fresh(['ingredients.components']));

    expect($tags)->toContain('Vegan', 'Dairy-free', 'Gluten-free');
});

test('harissa chicken is spicy and omnivore', function () {
    $chicken = Ingredient::factory()->create(['name' => 'Chicken Breast']);
    $harissa = Ingredient::factory()->create(['name' => 'Harissa Paste (Base)']);
    $meal = Meal::factory()->create(['name' => 'Test Harissa Chicken']);
    $meal->ingredients()->sync([
        $chicken->id => ['amount_grams' => 110],
        $harissa->id => ['amount_grams' => 18],
    ]);

    $tags = MealDietTagResolver::resolveForMeal($meal->fresh(['ingredients.components']));

    expect($tags)->toContain('Spicy', 'Dairy-free', 'Gluten-free', 'Nut-free')
        ->and($tags)->not->toContain('Vegan', 'Vegetarian');
});
