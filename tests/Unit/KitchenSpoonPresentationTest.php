<?php

use App\Models\Ingredient;
use App\Support\KitchenSpoonPresentation;

test('black seeds show a half teaspoon beside grams', function () {
    $seeds = new Ingredient([
        'name' => 'Black Seeds',
        'usda_food_category' => 'Spices and Herbs',
    ]);

    expect(KitchenSpoonPresentation::appliesTo($seeds))->toBeTrue()
        ->and(KitchenSpoonPresentation::labelForGrams($seeds, 3.0))->toBe('½ tsp')
        ->and(KitchenSpoonPresentation::formatTierLine($seeds, 3.0, '3'))
        ->toBe('Black Seeds — 3g (½ tsp)')
        ->and(KitchenSpoonPresentation::formatLibraryLine($seeds, 3.0, '3'))
        ->toBe('3g (½ tsp) Black Seeds');
});

test('sea salt pinches stay kitchen-friendly', function () {
    $salt = new Ingredient([
        'name' => 'Sea Salt',
        'usda_food_category' => 'Spices and Herbs',
    ]);

    expect(KitchenSpoonPresentation::labelForGrams($salt, 0.5))->toBe('pinch')
        ->and(KitchenSpoonPresentation::labelForGrams($salt, 1.0))->toBe('¼ tsp');
});

test('olive oil grams get a teaspoon label', function () {
    $oil = new Ingredient([
        'name' => 'Olive Oil (Extra Virgin)',
        'usda_food_category' => 'Fats',
        'density' => 0.92,
    ]);

    // 5 g ≈ 5.4 ml ≈ 1 tsp
    expect(KitchenSpoonPresentation::labelForGrams($oil, 5.0))->toBe('1 tsp')
        ->and(KitchenSpoonPresentation::formatLibraryLine($oil, 5.0, '5'))
        ->toBe('5g (1 tsp) Olive Oil (Extra Virgin)');

    // 13.8 g ≈ 15 ml ≈ 1 tbsp
    expect(KitchenSpoonPresentation::labelForGrams($oil, 13.8))->toBe('1 tbsp')
        ->and(KitchenSpoonPresentation::formatLibraryLine($oil, 13.8, '13.8'))
        ->toBe('13.8g (1 tbsp) Olive Oil (Extra Virgin)');
});

test('dressings and pastes use spoon labels', function () {
    $dressing = new Ingredient([
        'name' => 'Lemon-Tahini Dressing (Base)',
        'usda_food_category' => 'Fats',
        'density' => 1.0,
    ]);
    $paste = new Ingredient([
        'name' => 'Harissa Paste (Base)',
        'usda_food_category' => 'Condiments',
        'density' => 1.1,
    ]);

    expect(KitchenSpoonPresentation::appliesTo($dressing))->toBeTrue()
        ->and(KitchenSpoonPresentation::labelForGrams($dressing, 20.0))->toBe('1½ tbsp')
        ->and(KitchenSpoonPresentation::labelForGrams($paste, 10.0))->toBe('2 tsp');
});

test('water volumes promote to cups', function () {
    $water = new Ingredient([
        'name' => 'Water (Filtered)',
        'usda_food_category' => 'Liquids',
        'density' => 1.0,
    ]);

    expect(KitchenSpoonPresentation::labelForGrams($water, 120.0))->toBe('½ cup')
        ->and(KitchenSpoonPresentation::formatTierLine($water, 120.0, '120'))
        ->toBe('Water (Filtered) — 120g (½ cup)');
});

test('fresh herbs get teaspoon labels', function () {
    $dill = new Ingredient([
        'name' => 'Dill (Fresh)',
        'usda_food_category' => 'Vegetables',
    ]);

    expect(KitchenSpoonPresentation::appliesTo($dill))->toBeTrue()
        ->and(KitchenSpoonPresentation::labelForGrams($dill, 5.0))->toBe('1 tbsp');
});

test('fresh chillies get teaspoon labels', function () {
    $chillies = new Ingredient([
        'name' => 'Red Thai Chillies',
        'usda_food_category' => 'Vegetables',
    ]);

    expect(KitchenSpoonPresentation::appliesTo($chillies))->toBeTrue()
        ->and(KitchenSpoonPresentation::labelForGrams($chillies, 2.0))->toBe('1 tsp')
        ->and(KitchenSpoonPresentation::formatTierLine($chillies, 2.0, '2'))
        ->toBe('Red Thai Chillies — 2g (1 tsp)');
});

test('bulk vegetables do not get spoon labels', function () {
    $cauliflower = new Ingredient([
        'name' => 'Cauliflower',
        'usda_food_category' => 'Vegetables',
    ]);

    expect(KitchenSpoonPresentation::appliesTo($cauliflower))->toBeFalse()
        ->and(KitchenSpoonPresentation::labelForGrams($cauliflower, 150.0))->toBeNull()
        ->and(KitchenSpoonPresentation::appendToGrams('150g', $cauliflower, 150.0))->toBe('150g');
});
