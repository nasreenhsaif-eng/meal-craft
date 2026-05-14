<?php

use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use Livewire\Livewire;

test('saving a meal with use as base ingredient creates per-100g library row from batch totals', function () {
    $this->actingAs(User::factory()->create());

    $base = Ingredient::query()->create([
        'name' => 'Sauce Base',
        'usda_food_category' => 'Other',
        'calories' => 100,
        'protein' => 10,
        'carbs' => 5,
        'fat' => 4,
        'b9_folate' => 0,
        'b12' => 0,
        'iron' => 0,
        'magnesium' => 0,
        'micronutrients' => ['fiber' => 2.0],
        'is_verified' => true,
    ]);

    Livewire::test('pages::meals')
        ->set('name', 'Bulk Sauce')
        ->set('useRecipeAsIngredient', true)
        ->set('recipeIngredients', [
            ['ingredient_id' => $base->id, 'amount' => 1000, 'unit' => 'g'],
        ])
        ->call('saveMealFromBuilder')
        ->assertHasNoErrors();

    $meal = Meal::query()->where('name', 'Bulk Sauce')->firstOrFail();
    $derived = Ingredient::query()->where('source_meal_id', $meal->id)->firstOrFail();

    expect($derived->name)->toBe('Bulk Sauce')
        ->and((float) $derived->calories)->toBe(100.0)
        ->and((float) $derived->protein)->toBe(10.0)
        ->and($derived->is_verified)->toBeFalse();

    $micros = is_array($derived->micronutrients) ? $derived->micronutrients : [];
    expect((float) ($micros['fiber'] ?? 0))->toBe(2.0);
});

test('updating the meal updates the derived ingredient nutrition', function () {
    $this->actingAs(User::factory()->create());

    $light = Ingredient::query()->create([
        'name' => 'Light Part',
        'usda_food_category' => 'Other',
        'calories' => 100,
        'protein' => 5,
        'carbs' => 10,
        'fat' => 2,
        'b9_folate' => 0,
        'b12' => 0,
        'iron' => 0,
        'magnesium' => 0,
        'micronutrients' => [],
        'is_verified' => true,
    ]);

    $heavy = Ingredient::query()->create([
        'name' => 'Heavy Part',
        'usda_food_category' => 'Other',
        'calories' => 200,
        'protein' => 10,
        'carbs' => 5,
        'fat' => 12,
        'b9_folate' => 0,
        'b12' => 0,
        'iron' => 0,
        'magnesium' => 0,
        'micronutrients' => [],
        'is_verified' => true,
    ]);

    Livewire::test('pages::meals')
        ->set('name', 'Combo Batch')
        ->set('useRecipeAsIngredient', true)
        ->set('recipeIngredients', [
            ['ingredient_id' => $light->id, 'amount' => 500, 'unit' => 'g'],
            ['ingredient_id' => $heavy->id, 'amount' => 500, 'unit' => 'g'],
        ])
        ->call('saveMealFromBuilder')
        ->assertHasNoErrors();

    $meal = Meal::query()->where('name', 'Combo Batch')->firstOrFail();
    $derived = Ingredient::query()->where('source_meal_id', $meal->id)->firstOrFail();

    expect((float) $derived->calories)->toBe(150.0);

    Livewire::test('pages::meals', ['meal' => $meal->fresh()])
        ->set('useRecipeAsIngredient', true)
        ->set('recipeIngredients', [
            ['ingredient_id' => $light->id, 'amount' => 500, 'unit' => 'g'],
            ['ingredient_id' => $heavy->id, 'amount' => 250, 'unit' => 'g'],
        ])
        ->set('finishedWeightGrams', '750')
        ->call('saveMealFromBuilder')
        ->assertHasNoErrors();

    $derived->refresh();

    expect(round((float) $derived->calories, 2))->toBe(133.33);
});

test('turning off use as base ingredient clears the source meal link on the ingredient', function () {
    $this->actingAs(User::factory()->create());

    $base = Ingredient::query()->create([
        'name' => 'Base Only',
        'usda_food_category' => 'Other',
        'calories' => 50,
        'protein' => 1,
        'carbs' => 8,
        'fat' => 1,
        'b9_folate' => 0,
        'b12' => 0,
        'iron' => 0,
        'magnesium' => 0,
        'micronutrients' => [],
        'is_verified' => true,
    ]);

    Livewire::test('pages::meals')
        ->set('name', 'Toggle Off Meal')
        ->set('useRecipeAsIngredient', true)
        ->set('recipeIngredients', [
            ['ingredient_id' => $base->id, 'amount' => 100, 'unit' => 'g'],
        ])
        ->call('saveMealFromBuilder')
        ->assertHasNoErrors();

    $meal = Meal::query()->where('name', 'Toggle Off Meal')->firstOrFail();
    $derivedId = Ingredient::query()->where('source_meal_id', $meal->id)->value('id');
    expect($derivedId)->not->toBeNull();

    Livewire::test('pages::meals', ['meal' => $meal->fresh()])
        ->set('useRecipeAsIngredient', false)
        ->set('recipeIngredients', [
            ['ingredient_id' => $base->id, 'amount' => 100, 'unit' => 'g'],
        ])
        ->call('saveMealFromBuilder')
        ->assertHasNoErrors();

    expect(Ingredient::query()->find($derivedId)?->source_meal_id)->toBeNull();
});

test('meal edit ingredient picker excludes this meals derived ingredient', function () {
    $this->actingAs(User::factory()->create());

    $base = Ingredient::query()->create([
        'name' => 'Picker Base',
        'usda_food_category' => 'Other',
        'calories' => 80,
        'protein' => 4,
        'carbs' => 6,
        'fat' => 3,
        'b9_folate' => 0,
        'b12' => 0,
        'iron' => 0,
        'magnesium' => 0,
        'micronutrients' => [],
        'is_verified' => true,
    ]);

    Livewire::test('pages::meals')
        ->set('name', 'Picker Meal')
        ->set('useRecipeAsIngredient', true)
        ->set('recipeIngredients', [
            ['ingredient_id' => $base->id, 'amount' => 200, 'unit' => 'g'],
        ])
        ->call('saveMealFromBuilder')
        ->assertHasNoErrors();

    $meal = Meal::query()->where('name', 'Picker Meal')->firstOrFail();
    $derived = Ingredient::query()->where('source_meal_id', $meal->id)->firstOrFail();

    $component = Livewire::test('pages::meals', ['meal' => $meal->fresh()]);
    $optionIds = $component->instance()->ingredientSearchResults(0)->pluck('id')->map(fn ($id): int => (int) $id)->all();

    expect($optionIds)->toContain((int) $base->id)
        ->not->toContain((int) $derived->id);
});

test('derived ingredient density uses finished weight when lower than raw batch', function () {
    $this->actingAs(User::factory()->create());

    $base = Ingredient::query()->create([
        'name' => 'Reduction Base',
        'usda_food_category' => 'Other',
        'calories' => 100,
        'protein' => 10,
        'carbs' => 5,
        'fat' => 4,
        'b9_folate' => 0,
        'b12' => 0,
        'iron' => 0,
        'magnesium' => 0,
        'micronutrients' => [],
        'is_verified' => true,
    ]);

    Livewire::test('pages::meals')
        ->set('name', 'Reduced Sauce')
        ->set('useRecipeAsIngredient', true)
        ->set('recipeIngredients', [
            ['ingredient_id' => $base->id, 'amount' => 1000, 'unit' => 'g'],
        ])
        ->set('finishedWeightGrams', '800')
        ->call('saveMealFromBuilder')
        ->assertHasNoErrors();

    $meal = Meal::query()->where('name', 'Reduced Sauce')->firstOrFail();
    $derived = Ingredient::query()->where('source_meal_id', $meal->id)->firstOrFail();

    expect((float) $meal->finished_weight_grams)->toBe(800.0)
        ->and((float) $derived->calories)->toBe(125.0);
});
