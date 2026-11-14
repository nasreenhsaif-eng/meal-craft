<?php

use App\Models\Ingredient;
use App\Models\Meal;
use App\Support\MealFoodFilterResolver;

test('chia pudding with walnuts triggers nuts filter', function () {
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

    $filters = MealFoodFilterResolver::resolveForMeal($meal->fresh(['ingredients.components']));

    expect($filters)->toContain('nuts')
        ->and($filters)->not->toContain('dairy', 'eggs', 'fish');
});

test('egg breakfast triggers eggs filter', function () {
    $egg = Ingredient::factory()->create(['name' => 'Egg']);
    $meal = Meal::factory()->create(['name' => 'Test Omelet']);
    $meal->ingredients()->sync([$egg->id => ['amount_grams' => 100]]);

    $filters = MealFoodFilterResolver::resolveForMeal($meal->fresh(['ingredients.components']));

    expect($filters)->toContain('eggs')
        ->and($filters)->not->toContain('dairy', 'nuts');
});

test('salmon plate triggers fish but not shellfish', function () {
    $salmon = Ingredient::factory()->create(['name' => 'Salmon (Raw)']);
    $meal = Meal::factory()->create(['name' => 'Test Salmon']);
    $meal->ingredients()->sync([$salmon->id => ['amount_grams' => 120]]);

    $filters = MealFoodFilterResolver::resolveForMeal($meal->fresh(['ingredients.components']));

    expect($filters)->toContain('fish')
        ->and($filters)->not->toContain('shellfish', 'nuts', 'dairy');
});

test('harissa chicken triggers spicy filter', function () {
    $chicken = Ingredient::factory()->create(['name' => 'Chicken Breast']);
    $harissa = Ingredient::factory()->create(['name' => 'Harissa Paste (Base)']);
    $meal = Meal::factory()->create(['name' => 'Test Harissa Chicken']);
    $meal->ingredients()->sync([
        $chicken->id => ['amount_grams' => 110],
        $harissa->id => ['amount_grams' => 18],
    ]);

    $filters = MealFoodFilterResolver::resolveForMeal($meal->fresh(['ingredients.components']));

    expect($filters)->toContain('spicy')
        ->and($filters)->not->toContain('nuts', 'dairy');
});

test('green beans do not trigger beans filter', function () {
    $greenBeans = Ingredient::factory()->create(['name' => 'Garlicky Green Beans (Base)']);
    $meal = Meal::factory()->create(['name' => 'Test Green Beans']);
    $meal->ingredients()->sync([$greenBeans->id => ['amount_grams' => 85]]);

    $filters = MealFoodFilterResolver::resolveForMeal($meal->fresh(['ingredients.components']));

    expect($filters)->not->toContain('beans');
});

test('chickpea hummus triggers beans filter', function () {
    $hummus = Ingredient::factory()->create(['name' => 'Hummus']);
    $meal = Meal::factory()->create(['name' => 'Test Hummus Plate']);
    $meal->ingredients()->sync([$hummus->id => ['amount_grams' => 40]]);

    $filters = MealFoodFilterResolver::resolveForMeal($meal->fresh(['ingredients.components']));

    expect($filters)->toContain('beans');
});

test('tomato sauce triggers nightshades but black pepper does not', function () {
    $tomato = Ingredient::factory()->create(['name' => 'Tomato Sauce']);
    $pepper = Ingredient::factory()->create(['name' => 'Black Pepper']);
    $meal = Meal::factory()->create(['name' => 'Test Tomato']);
    $meal->ingredients()->sync([
        $tomato->id => ['amount_grams' => 50],
        $pepper->id => ['amount_grams' => 1],
    ]);

    $filters = MealFoodFilterResolver::resolveForMeal($meal->fresh(['ingredients.components']));

    expect($filters)->toContain('nightshades');
});

test('greek yogurt triggers dairy filter', function () {
    $yogurt = Ingredient::factory()->create(['name' => 'Greek Yogurt']);
    $meal = Meal::factory()->create(['name' => 'Test Yogurt']);
    $meal->ingredients()->sync([$yogurt->id => ['amount_grams' => 150]]);

    $filters = MealFoodFilterResolver::resolveForMeal($meal->fresh(['ingredients.components']));

    expect($filters)->toContain('dairy');
});
