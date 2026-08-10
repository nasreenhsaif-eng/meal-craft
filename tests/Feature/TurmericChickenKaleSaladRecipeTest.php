<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\SaladDressingMealRefiner;
use App\Support\MealLibraryEditGuard;
use App\Support\StandardMeatPortion;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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

    // Live bug shape: greens + avocado only — chicken, dressing, and seeds missing.
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

    $updated = app(SaladDressingMealRefiner::class)->refine('Turmeric Chicken Kale Salad');

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
        ->toBe(10.0)
        ->and($meal->short_description)->toContain('turmeric chicken')
        ->and($meal->instructions)->toContain('pumpkin seeds')
        ->and($meal->instructions)->toContain('Serve dressing on the side');
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
