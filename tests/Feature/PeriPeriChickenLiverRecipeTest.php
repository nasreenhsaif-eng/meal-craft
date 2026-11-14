<?php

use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\NutrientDenseLiverMealRecipeRefiner;
use App\Support\StandardMeatPortion;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('peri peri chicken liver refiner creates the rotation liver meal with zucchini bread', function (): void {
    foreach ([
        'Bay Leaves',
        'Bell Pepper (Red)',
        'Black Pepper',
        'Cashew Cream (Base)',
        'Chicken Liver',
        'cumin powder',
        'Fire Roasted Tomatoes (Base)',
        'Fresh Parsley',
        'Garlic (Raw)',
        'Grass Fed Butter',
        'Lemon Juice',
        'Olive Oil (Extra Virgin)',
        'Oregano',
        'Red Chili',
        'Sea Salt',
        'Smoked Paprika',
        'White Onion',
        'Worcestershire',
        'Zucchini Almond Bread (Base)',
    ] as $name) {
        Ingredient::factory()->create([
            'name' => $name,
            'usda_food_category' => str_contains($name, '(Base)') ? 'Base Ingredient' : 'Vegetables',
            'calories' => 100,
            'protein' => 10,
            'carbs' => 5,
            'fat' => 5,
            'is_verified' => true,
        ]);
    }

    $refined = app(NutrientDenseLiverMealRecipeRefiner::class)
        ->refine(NutrientDenseLiverMealRecipeRefiner::PERI_PERI_CHICKEN_LIVER_NAME);

    expect($refined)->toContain(NutrientDenseLiverMealRecipeRefiner::PERI_PERI_CHICKEN_LIVER_NAME);

    $meal = Meal::query()
        ->where('name', NutrientDenseLiverMealRecipeRefiner::PERI_PERI_CHICKEN_LIVER_NAME)
        ->with('ingredients')
        ->firstOrFail();

    $names = $meal->ingredients->pluck('name')->sort()->values()->all();

    expect($names)->toContain('Chicken Liver')
        ->and($names)->toContain('Zucchini Almond Bread (Base)')
        ->and($names)->toContain('Cashew Cream (Base)')
        ->and($names)->toContain('Worcestershire')
        ->and((float) $meal->ingredients->firstWhere('name', 'Chicken Liver')->pivot->amount_grams)
        ->toBe(StandardMeatPortion::GRAMS)
        ->and($meal->instructions)->toContain('Zucchini Almond Bread (Base)')
        ->and($meal->instructions)->toContain('Cashew Cream (Base)')
        ->and($meal->food_filter_tags)->toContain('fish')
        ->and($meal->food_filter_tags)->toContain('nightshades');
});
