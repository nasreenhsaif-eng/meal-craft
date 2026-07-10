<?php

use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\SaladDressingMealRefiner;
use App\Support\MealLibraryRefinerOverrides;
use App\Support\StandardMeatPortion;
use Tests\Support\IsolatesMenuDevelopmentCsv;

uses(IsolatesMenuDevelopmentCsv::class);

beforeEach(function (): void {
    $this->setUpIsolatedMenuDevelopmentCsvPaths();
});

test('turmeric chicken kale salad uses turmeric chicken base and listed salad ingredients', function (): void {
    foreach ([
        'Chicken Breast',
        'Turmeric',
        'Ginger (Raw)',
        'Garlic (Raw)',
        'Lemon Zest',
        'Sea Salt',
        'White Peppercorns',
        'Kale',
        'Avocado',
        'Broccoli',
        'Pumpkin Seeds',
        'Sesame Seeds',
        'Fresh Coriander',
        'Turmeric Lemon Dressing (Base)',
        'Turmeric Chicken (Base)',
    ] as $ingredientName) {
        Ingredient::query()->create([
            'name' => $ingredientName,
            'usda_food_category' => str_contains($ingredientName, '(Base)') ? 'Base Ingredient' : 'Vegetables',
            'calories' => 100,
            'protein' => 10,
            'carbs' => 5,
            'fat' => 5,
            'b6' => 0,
            'b9_folate' => 0,
            'b12' => 0,
            'iron' => 0,
            'magnesium' => 0,
            'micronutrients' => [],
            'is_verified' => true,
        ]);
    }

    Meal::query()->create([
        'name' => 'Turmeric Chicken Kale Salad',
        'category' => 'Meal',
        'instructions' => 'Old instructions.',
        'total_calories' => 300,
        'total_protein' => 30,
        'total_carbs' => 10,
        'total_fat' => 10,
        'nutrition_aggregates_synced' => false,
    ]);

    MealLibraryRefinerOverrides::put('Turmeric Chicken Kale Salad', [
        'ingredients' => [
            'Avocado' => 40.0,
            'Broccoli' => 60.0,
            'Fresh Coriander' => 8.0,
            'Kale' => 80.0,
            'Pumpkin Seeds' => 10.0,
            'Sesame Seeds' => 6.0,
            'Turmeric Chicken (Base)' => StandardMeatPortion::GRAMS,
            'Turmeric Lemon Dressing (Base)' => 14.0,
        ],
        'instructions' => "1. Grill or pan-sear Turmeric Chicken (Base) until golden then in the oven for 20 minutes exactly, then Rest and slice.\n2. Massage kale until tender; lightly steam or blanch broccoli until bright green.\n3. Toss kale, broccoli, avocado, coriander, pumpkin seeds, and sesame seeds.\n4. Top with warm turmeric chicken.\n5. Serve dressing on the side.",
        'diet_tags' => ['Dairy-free', 'Gluten-free'],
        'food_filter_tags' => ['sesame'],
        'short_description' => 'Golden turmeric chicken over massaged kale with avocado, broccoli, seeds, and turmeric lemon dressing.',
    ]);

    app(SaladDressingMealRefiner::class)->refine('Turmeric Chicken Kale Salad');

    $meal = Meal::query()->where('name', 'Turmeric Chicken Kale Salad')->with('ingredients')->firstOrFail();
    $names = $meal->ingredients->pluck('name')->sort()->values()->all();

    expect($names)->toBe([
        'Avocado',
        'Broccoli',
        'Fresh Coriander',
        'Kale',
        'Pumpkin Seeds',
        'Sesame Seeds',
        'Turmeric Chicken (Base)',
        'Turmeric Lemon Dressing (Base)',
    ])
        ->and((float) $meal->ingredients->firstWhere('name', 'Turmeric Chicken (Base)')->pivot->amount_grams)
        ->toBe(StandardMeatPortion::GRAMS)
        ->and($meal->instructions)->toContain('Turmeric Chicken (Base)')
        ->and($meal->instructions)->not->toContain('Cherry Tomatoes')
        ->and($meal->instructions)->not->toContain('Pomegranate');
});
