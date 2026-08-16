<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\CollapsedPrimaryProteinHealer;
use App\Support\MealLibraryEditGuard;
use App\Support\StandardMeatPortion;

/**
 * @param  array{calories?: float, protein?: float, carbs?: float, fat?: float}  $macros
 */
function turmericKaleIngredient(string $name, array $macros = []): Ingredient
{
    return Ingredient::factory()->create(array_merge([
        'name' => $name,
        'usda_food_category' => str_contains($name, '(Base)') ? 'Base Ingredient' : 'Produce',
        'calories' => 100,
        'protein' => 10,
        'carbs' => 5,
        'fat' => 5,
        'is_verified' => true,
    ], $macros));
}

test('turmeric chicken kale salad restores chicken dressing and seeds on a ui-locked incomplete recipe', function () {
    foreach ([
        'Chicken Breast' => ['calories' => 120, 'protein' => 23, 'carbs' => 0, 'fat' => 2.6],
        'Kale' => ['calories' => 35, 'protein' => 2.9, 'carbs' => 4.4, 'fat' => 1.5],
        'Broccoli' => ['calories' => 34, 'protein' => 2.8, 'carbs' => 6.6, 'fat' => 0.4],
        'Avocado' => ['calories' => 160, 'protein' => 2, 'carbs' => 8.5, 'fat' => 14.7],
        'Fresh Coriander' => ['calories' => 23, 'protein' => 2.1, 'carbs' => 3.7, 'fat' => 0.5],
        'Pumpkin Seeds' => ['calories' => 559, 'protein' => 30.2, 'carbs' => 10.7, 'fat' => 49.1],
        'Turmeric Lemon Dressing (Base)' => ['calories' => 422, 'protein' => 1, 'carbs' => 7.8, 'fat' => 44.5],
    ] as $name => $macros) {
        turmericKaleIngredient($name, $macros);
    }

    $meal = Meal::factory()->create([
        'name' => 'Turmeric Chicken Kale Salad',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
        'library_edited_at' => now(),
        'short_description' => 'Golden turmeric chicken over massaged kale with avocado, broccoli, seeds, and turmeric lemon dressing.',
    ]);

    $kale = Ingredient::query()->where('name', 'Kale')->firstOrFail();
    $broccoli = Ingredient::query()->where('name', 'Broccoli')->firstOrFail();
    $avocado = Ingredient::query()->where('name', 'Avocado')->firstOrFail();
    $coriander = Ingredient::query()->where('name', 'Fresh Coriander')->firstOrFail();

    $meal->ingredients()->sync([
        $broccoli->id => ['amount_grams' => 60, 'amount' => 60, 'unit' => 'g'],
        $kale->id => ['amount_grams' => 55, 'amount' => 55, 'unit' => 'g'],
        $coriander->id => ['amount_grams' => 5, 'amount' => 5, 'unit' => 'g'],
        $avocado->id => ['amount_grams' => 40, 'amount' => 40, 'unit' => 'g'],
    ]);

    expect(MealLibraryEditGuard::mealHasCollapsedOrMissingPrimaryMeat($meal->fresh(['ingredients'])))->toBeTrue();

    $updated = app(CollapsedPrimaryProteinHealer::class)->healAll();

    $meal->refresh()->load('ingredients');
    $names = $meal->ingredients->pluck('name')->all();

    expect($updated)->toContain('Turmeric Chicken Kale Salad')
        ->and($names)->toContain('Chicken Breast')
        ->and($names)->toContain('Turmeric Lemon Dressing (Base)')
        ->and($names)->toContain('Pumpkin Seeds')
        ->and($names)->toContain('Avocado')
        ->and($names)->toContain('Broccoli')
        ->and($names)->toContain('Kale')
        ->and((float) $meal->ingredients->firstWhere('name', 'Chicken Breast')->pivot->amount_grams)
        ->toBe(StandardMeatPortion::GRAMS)
        ->and((float) $meal->ingredients->firstWhere('name', 'Turmeric Lemon Dressing (Base)')->pivot->amount_grams)
        ->toBe(20.0)
        ->and((float) $meal->ingredients->firstWhere('name', 'Pumpkin Seeds')->pivot->amount_grams)
        ->toBe(10.0);
});

test('healAll restores screenshot bug shapes for rosemary plates and turmeric salad', function () {
    $rosemary = Ingredient::factory()->create([
        'name' => 'Rosemary Garlic Chicken (Base)',
        'calories' => 200,
        'protein' => 24,
        'carbs' => 3,
        'fat' => 10,
    ]);

    foreach ([
        'Sweet Potato', 'Garlic (Raw)', 'Mushrooms', 'Spinach (Fresh)', 'Black Pepper',
        'Quinoa Flatbread (Base)', 'Beetroot', 'Broccoli', 'Kale', 'Fresh Coriander', 'Avocado',
        'Chicken Breast', 'Pumpkin Seeds', 'Turmeric Lemon Dressing (Base)',
    ] as $name) {
        Ingredient::factory()->create([
            'name' => $name,
            'calories' => 50,
            'protein' => 5,
            'carbs' => 5,
            'fat' => 2,
        ]);
    }

    $plate = Meal::factory()->create([
        'name' => 'Rosemary Garlic Chicken w Mushroom, Spinach & Roasted Sweet Potato',
        'library_edited_at' => now(),
    ]);
    $plate->ingredients()->sync([
        Ingredient::query()->where('name', 'Rosemary Garlic Chicken (Base)')->value('id') => [
            'amount_grams' => 2, 'amount' => 2, 'unit' => 'g',
        ],
        Ingredient::query()->where('name', 'Sweet Potato')->value('id') => [
            'amount_grams' => 100, 'amount' => 100, 'unit' => 'g',
        ],
        Ingredient::query()->where('name', 'Spinach (Fresh)')->value('id') => [
            'amount_grams' => 100, 'amount' => 100, 'unit' => 'g',
        ],
    ]);

    $pomegranate = Meal::factory()->create([
        'name' => 'Rosemary Garlic Chicken w Pomegranate Glaze, Beetroot & Rocca',
        'library_edited_at' => now(),
    ]);
    $pomegranate->ingredients()->sync([
        $rosemary->id => ['amount_grams' => 2, 'amount' => 2, 'unit' => 'g'],
        Ingredient::query()->where('name', 'Quinoa Flatbread (Base)')->value('id') => [
            'amount_grams' => 20, 'amount' => 20, 'unit' => 'g',
        ],
        Ingredient::query()->where('name', 'Beetroot')->value('id') => [
            'amount_grams' => 100, 'amount' => 100, 'unit' => 'g',
        ],
    ]);

    $turmeric = Meal::factory()->create([
        'name' => 'Turmeric Chicken Kale Salad',
        'library_edited_at' => now(),
    ]);
    $turmeric->ingredients()->sync([
        Ingredient::query()->where('name', 'Broccoli')->value('id') => [
            'amount_grams' => 60, 'amount' => 60, 'unit' => 'g',
        ],
        Ingredient::query()->where('name', 'Kale')->value('id') => [
            'amount_grams' => 55, 'amount' => 55, 'unit' => 'g',
        ],
        Ingredient::query()->where('name', 'Fresh Coriander')->value('id') => [
            'amount_grams' => 5, 'amount' => 5, 'unit' => 'g',
        ],
        Ingredient::query()->where('name', 'Avocado')->value('id') => [
            'amount_grams' => 40, 'amount' => 40, 'unit' => 'g',
        ],
    ]);

    app(CollapsedPrimaryProteinHealer::class)->healAll();

    expect((float) $plate->fresh(['ingredients'])->ingredients->firstWhere('name', 'Rosemary Garlic Chicken (Base)')->pivot->amount_grams)
        ->toBe(StandardMeatPortion::GRAMS)
        ->and((float) $pomegranate->fresh(['ingredients'])->ingredients->firstWhere('name', 'Rosemary Garlic Chicken (Base)')->pivot->amount_grams)
        ->toBe(StandardMeatPortion::GRAMS)
        ->and((float) $turmeric->fresh(['ingredients'])->ingredients->firstWhere('name', 'Chicken Breast')->pivot->amount_grams)
        ->toBe(StandardMeatPortion::GRAMS);
});

test('meal library edit guard detects missing chicken on a chicken salad name', function () {
    $kale = turmericKaleIngredient('Kale');

    $meal = Meal::factory()->create([
        'name' => 'Turmeric Chicken Kale Salad',
    ]);

    $meal->ingredients()->sync([
        $kale->id => ['amount_grams' => 55, 'amount' => 55, 'unit' => 'g'],
    ]);

    expect(MealLibraryEditGuard::mealHasCollapsedOrMissingPrimaryMeat($meal->fresh(['ingredients'])))
        ->toBeTrue()
        ->and(MealLibraryEditGuard::mealNameExpectsPrimaryMeat('Turmeric Chicken Kale Salad'))
        ->toBeTrue()
        ->and(MealLibraryEditGuard::mealNameExpectsPrimaryMeat('Shaved Fennel Rocca Salad'))
        ->toBeFalse();
});
