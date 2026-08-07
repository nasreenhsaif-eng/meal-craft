<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\PeriPeriChickenLiverRecipeRefiner;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @param  array{calories?: float, protein?: float, carbs?: float, fat?: float}  $macros
 */
function periPeriIngredient(string $name, array $macros = []): Ingredient
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

test('peri peri chicken liver meal includes fifteen grams of pumpkin seeds', function () {
    foreach ([
        'Chicken Liver' => ['calories' => 119, 'protein' => 16.9, 'carbs' => 0.7, 'fat' => 4.8],
        'Zucchini Almond Bread (Base)' => ['calories' => 257.87, 'protein' => 10.28, 'carbs' => 9.05, 'fat' => 21.71],
        'Harissa Paste (Base)' => ['calories' => 107.75, 'protein' => 1.39, 'carbs' => 6.79, 'fat' => 8.92],
        'Olive Oil (Extra Virgin)' => ['calories' => 884, 'protein' => 0, 'carbs' => 0, 'fat' => 100],
        'Bell Pepper (Red)' => ['calories' => 31, 'protein' => 1, 'carbs' => 6, 'fat' => 0.3],
        'Red Onion' => ['calories' => 40, 'protein' => 1.1, 'carbs' => 9.3, 'fat' => 0.1],
        'Tomato (Raw)' => ['calories' => 18, 'protein' => 0.9, 'carbs' => 3.9, 'fat' => 0.2],
        'Garlic (Raw)' => ['calories' => 149, 'protein' => 6.4, 'carbs' => 33, 'fat' => 0.5],
        'Lemon Juice' => ['calories' => 22, 'protein' => 0.4, 'carbs' => 6.9, 'fat' => 0.2],
        'Fresh Coriander' => ['calories' => 23, 'protein' => 2.1, 'carbs' => 3.7, 'fat' => 0.5],
        'Smoked Paprika' => ['calories' => 282, 'protein' => 14.1, 'carbs' => 54, 'fat' => 13],
        'Sea Salt' => ['calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0],
        'Black Pepper' => ['calories' => 251, 'protein' => 10.4, 'carbs' => 64, 'fat' => 3.3],
        'Pumpkin Seeds' => ['calories' => 559, 'protein' => 30.2, 'carbs' => 10.7, 'fat' => 49.1],
    ] as $name => $macros) {
        periPeriIngredient($name, $macros);
    }

    $updated = app(PeriPeriChickenLiverRecipeRefiner::class)->refine();

    $meal = Meal::query()->where('name', PeriPeriChickenLiverRecipeRefiner::MEAL_NAME)->firstOrFail();
    $pumpkinGrams = (float) $meal->ingredients->firstWhere('name', 'Pumpkin Seeds')->pivot->amount_grams;

    expect($updated)->toContain(PeriPeriChickenLiverRecipeRefiner::MEAL_NAME)
        ->and($pumpkinGrams)->toBe(15.0)
        ->and($meal->ingredients->pluck('name')->all())->toContain('Zucchini Almond Bread (Base)')
        ->and($meal->ingredients->pluck('name')->all())->toContain('Chicken Liver')
        ->and((float) $meal->ingredients->firstWhere('name', 'Chicken Liver')->pivot->amount_grams)->toBe(150.0)
        ->and($meal->instructions)->toContain('pumpkin seeds')
        ->and($meal->image_path)->toBe('images/meals/peri_peri_chicken_liver_w_zucchini_bread.png')
        ->and($meal->meal_type)->toBe(MealType::Main)
        ->and($meal->category)->toBe(RecipeCategory::Meal);
});

test('peri peri chicken liver refiner sets pumpkin seeds to fifteen grams on an existing meal', function () {
    foreach ([
        'Chicken Liver',
        'Zucchini Almond Bread (Base)',
        'Harissa Paste (Base)',
        'Olive Oil (Extra Virgin)',
        'Bell Pepper (Red)',
        'Red Onion',
        'Tomato (Raw)',
        'Garlic (Raw)',
        'Lemon Juice',
        'Fresh Coriander',
        'Smoked Paprika',
        'Sea Salt',
        'Black Pepper',
        'Pumpkin Seeds',
    ] as $name) {
        periPeriIngredient($name);
    }

    $meal = Meal::factory()->create([
        'name' => PeriPeriChickenLiverRecipeRefiner::MEAL_NAME,
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
        'library_edited_at' => null,
    ]);

    $liver = Ingredient::query()->where('name', 'Chicken Liver')->firstOrFail();
    $pumpkin = Ingredient::query()->where('name', 'Pumpkin Seeds')->firstOrFail();

    $meal->ingredients()->sync([
        $liver->id => ['amount_grams' => 150, 'amount' => 150, 'unit' => 'g'],
        $pumpkin->id => ['amount_grams' => 5, 'amount' => 5, 'unit' => 'g'],
    ]);

    app(PeriPeriChickenLiverRecipeRefiner::class)->refine();

    $meal->refresh()->load('ingredients');

    expect((float) $meal->ingredients->firstWhere('name', 'Pumpkin Seeds')->pivot->amount_grams)
        ->toBe(15.0);
});
