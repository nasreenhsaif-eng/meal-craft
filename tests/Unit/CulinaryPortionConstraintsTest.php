<?php

use App\Models\Ingredient;
use App\Models\Meal;
use App\Support\CulinaryPortionConstraints;
use App\Support\KitchenPortionRounding;

test('woody fresh herbs snap to whole grams instead of five-gram vegetable steps', function (): void {
    $rosemary = new Ingredient(['name' => 'Rosemary (Fresh)']);

    expect(KitchenPortionRounding::isWoodyFreshHerb($rosemary))->toBeTrue()
        ->and(KitchenPortionRounding::snapGramsForIngredient($rosemary, 1.0))->toBe(1.0)
        ->and(KitchenPortionRounding::snapGramsForIngredient($rosemary, 4.2))->toBe(4.0);
});

test('sweet potato egg hash enforces structural floors and herb caps', function (): void {
    $meal = new Meal(['name' => 'Sweet Potato Egg Hash']);

    $potato = new Ingredient(['name' => 'Sweet Potato']);
    $potato->id = 1;
    $onion = new Ingredient(['name' => 'White Onion']);
    $onion->id = 2;
    $rosemary = new Ingredient(['name' => 'Rosemary (Fresh)']);
    $rosemary->id = 3;

    $meal->setRelation('ingredients', collect([$potato, $onion, $rosemary]));

    $adjusted = CulinaryPortionConstraints::apply($meal, [
        1 => 20.0,
        2 => 5.0,
        3 => 8.0,
    ]);

    expect($adjusted[1])->toBe(120.0)
        ->and($adjusted[2])->toBe(50.0)
        ->and($adjusted[3])->toBe(1.0)
        ->and(CulinaryPortionConstraints::violationMessages($meal, [
            1 => 20.0,
            2 => 5.0,
            3 => 8.0,
        ]))->not->toBeEmpty();
});

test('egg in egg hash title is not treated as structural title base', function (): void {
    $meal = new Meal(['name' => 'Sweet Potato Egg Hash']);
    $egg = new Ingredient(['name' => 'Egg']);

    expect(CulinaryPortionConstraints::isTitleStructuralIngredient($meal, 'Egg'))->toBeFalse()
        ->and(CulinaryPortionConstraints::isTitleStructuralIngredient($meal, 'Sweet Potato'))->toBeTrue()
        ->and(CulinaryPortionConstraints::minimumGrams($meal, $egg))->toBeNull();
});
