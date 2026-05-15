<?php

use App\Models\Ingredient;
use App\Support\IngredientG6pdSafety;
use App\Support\IngredientLibraryCategory;

test('meal contains g6pd trigger when a direct ingredient is flagged', function () {
    $safe = Ingredient::factory()->create(['is_g6pd_trigger' => false]);
    $trigger = Ingredient::factory()->create(['is_g6pd_trigger' => true]);

    expect(IngredientG6pdSafety::mealContainsG6pdTrigger([$safe->id]))->toBeFalse()
        ->and(IngredientG6pdSafety::mealContainsG6pdTrigger([$safe->id, $trigger->id]))->toBeTrue();
});

test('meal contains g6pd trigger when a base ingredient includes a flagged child', function () {
    $child = Ingredient::factory()->create(['is_g6pd_trigger' => true]);
    $base = Ingredient::factory()->create([
        'usda_food_category' => IngredientLibraryCategory::BaseIngredient,
        'is_g6pd_trigger' => false,
    ]);
    $base->components()->attach($child->id, ['amount_grams' => 100]);

    expect(IngredientG6pdSafety::mealContainsG6pdTrigger([$base->id]))->toBeTrue();
});

test('meal contains g6pd trigger when ingredient name matches canonical list without db flag', function () {
    $beans = Ingredient::factory()->create([
        'name' => 'Cannellini Beans',
        'is_verified' => true,
        'is_g6pd_trigger' => false,
    ]);

    expect(IngredientG6pdSafety::mealContainsG6pdTrigger([$beans->id]))->toBeTrue()
        ->and(IngredientG6pdSafety::canonicalNameIndicatesG6pdTrigger('Organic cannellini bean'))->toBeTrue()
        ->and(IngredientG6pdSafety::canonicalNameIndicatesG6pdTrigger('Black beans'))->toBeFalse();
});

test('meal contains g6pd trigger when base ingredient includes canonical-trigger child without db flag', function () {
    $child = Ingredient::factory()->create([
        'name' => 'Cannellini Beans',
        'is_g6pd_trigger' => false,
        'is_verified' => true,
    ]);
    $base = Ingredient::factory()->create([
        'usda_food_category' => IngredientLibraryCategory::BaseIngredient,
        'is_g6pd_trigger' => false,
        'is_verified' => true,
    ]);
    $base->components()->attach($child->id, ['amount_grams' => 100]);

    expect(IngredientG6pdSafety::mealContainsG6pdTrigger([$base->id]))->toBeTrue();
});

test('merge trigger into safety labels adds g6pd trigger once', function () {
    $labels = IngredientG6pdSafety::mergeTriggerIntoSafetyLabels(['Contains: Peanuts'], true);

    expect($labels)->toContain('Contains: Peanuts')
        ->and($labels)->toContain(IngredientG6pdSafety::TRIGGER_SAFETY_LABEL);
});
