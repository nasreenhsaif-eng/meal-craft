<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\BalancedComplexCarbRecipeRefiner;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @param  array{calories: float, protein: float, carbs: float, fat: float}  $macros
 */
function complexCarbIngredient(string $name, array $macros): Ingredient
{
    return Ingredient::query()->create(array_merge([
        'name' => $name,
        'usda_food_category' => 'Grains',
        'b6' => 0,
        'b9_folate' => 0,
        'b12' => 0,
        'iron' => 0,
        'magnesium' => 0,
        'micronutrients' => [],
        'is_verified' => true,
    ], $macros));
}

function seedComplexCarbRefinerFixtures(): void
{
    $ingredientMacros = [
        'Salmon (Raw)' => ['calories' => 208, 'protein' => 20.4, 'carbs' => 0, 'fat' => 13.4],
        'Sweet Potato' => ['calories' => 86, 'protein' => 1.6, 'carbs' => 20.1, 'fat' => 0.1],
        'Wild Rice (Cooked)' => ['calories' => 101, 'protein' => 4, 'carbs' => 21.3, 'fat' => 0.3],
        'Pumpkin' => ['calories' => 26, 'protein' => 1, 'carbs' => 6.5, 'fat' => 0.1],
        'Avocado' => ['calories' => 160, 'protein' => 2, 'carbs' => 8.5, 'fat' => 14.7],
        'Cashew Nuts' => ['calories' => 553, 'protein' => 18.2, 'carbs' => 30.2, 'fat' => 43.8],
        'Turmeric Rice (Base)' => ['calories' => 15.62, 'protein' => 0.39, 'carbs' => 4.32, 'fat' => 0.03],
        'Cooked Quinoa (Base)' => ['calories' => 42.86, 'protein' => 1.57, 'carbs' => 7.61, 'fat' => 0.68],
        'Cooked Brown Basmati Rice (Base)' => ['calories' => 64.8, 'protein' => 1.64, 'carbs' => 13.4, 'fat' => 0.44],
        'Quinoa Bread (Base)' => ['calories' => 258.96, 'protein' => 8.08, 'carbs' => 39.14, 'fat' => 7.92],
        'Steamed Basmati Rice (Base)' => ['calories' => 118.27, 'protein' => 2.36, 'carbs' => 26.11, 'fat' => 0.23],
        'Beef Sirloin' => ['calories' => 162, 'protein' => 27, 'carbs' => 0, 'fat' => 6],
        'Beef Ground Lean' => ['calories' => 182, 'protein' => 26, 'carbs' => 0, 'fat' => 8],
        'Beef Chuck Roast' => ['calories' => 176, 'protein' => 20, 'carbs' => 0, 'fat' => 10.1],
        'Chicken Breast' => ['calories' => 165, 'protein' => 31, 'carbs' => 0, 'fat' => 3.6],
        'Ratatouille (Base)' => ['calories' => 99.67, 'protein' => 2.29, 'carbs' => 17.34, 'fat' => 5.39],
        'Cannellini Beans' => ['calories' => 139, 'protein' => 9.7, 'carbs' => 25.1, 'fat' => 0.4],
        'Fermented Chimichurri (Base)' => ['calories' => 104.83, 'protein' => 1.62, 'carbs' => 8.16, 'fat' => 8.09],
        'Asparagus' => ['calories' => 20, 'protein' => 2.2, 'carbs' => 3.9, 'fat' => 0.1],
        'Broccoli' => ['calories' => 34, 'protein' => 2.8, 'carbs' => 6.6, 'fat' => 0.4],
        'Mango' => ['calories' => 60, 'protein' => 0.82, 'carbs' => 15, 'fat' => 0.38],
        'Bell Pepper (Red)' => ['calories' => 31, 'protein' => 1, 'carbs' => 6, 'fat' => 0.3],
        'Cucumber' => ['calories' => 15, 'protein' => 0.65, 'carbs' => 3.6, 'fat' => 0.11],
        'Lime Juice' => ['calories' => 25, 'protein' => 0.42, 'carbs' => 8.4, 'fat' => 0.07],
        'Fresh Coriander' => ['calories' => 23, 'protein' => 2.13, 'carbs' => 3.67, 'fat' => 0.52],
        'Lemon Juice' => ['calories' => 22, 'protein' => 0.35, 'carbs' => 6.9, 'fat' => 0.24],
        'Orange Juice' => ['calories' => 45, 'protein' => 0.7, 'carbs' => 10.4, 'fat' => 0.2],
        'Dill (Fresh)' => ['calories' => 43, 'protein' => 3.5, 'carbs' => 7, 'fat' => 1.1],
        'Parsley' => ['calories' => 36, 'protein' => 3, 'carbs' => 6.3, 'fat' => 0.8],
        'Olive Oil (Extra Virgin)' => ['calories' => 884, 'protein' => 0, 'carbs' => 0, 'fat' => 100],
        'Olive Oil' => ['calories' => 884, 'protein' => 0, 'carbs' => 0, 'fat' => 100],
        'Sea Salt' => ['calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0],
        'Black Pepper' => ['calories' => 251, 'protein' => 10.4, 'carbs' => 64, 'fat' => 3.3],
        'Spinach (Fresh)' => ['calories' => 23, 'protein' => 2.9, 'carbs' => 3.6, 'fat' => 0.4],
        'Carrots' => ['calories' => 41, 'protein' => 0.9, 'carbs' => 9.6, 'fat' => 0.2],
        'Zucchini' => ['calories' => 17, 'protein' => 1.2, 'carbs' => 3.1, 'fat' => 0.3],
        'Garlic (Raw)' => ['calories' => 149, 'protein' => 6.4, 'carbs' => 33, 'fat' => 0.5],
        'Sesame Seeds' => ['calories' => 573, 'protein' => 17.7, 'carbs' => 23.4, 'fat' => 49.7],
        'Spring Onion' => ['calories' => 32, 'protein' => 1.8, 'carbs' => 7.3, 'fat' => 0.2],
        'White Onion' => ['calories' => 40, 'protein' => 1.1, 'carbs' => 9.3, 'fat' => 0.1],
        'Tomato (Raw)' => ['calories' => 18, 'protein' => 0.9, 'carbs' => 3.9, 'fat' => 0.2],
        'Chili Powder' => ['calories' => 282, 'protein' => 13.5, 'carbs' => 49.7, 'fat' => 14.3],
        'Bok Choy' => ['calories' => 13, 'protein' => 1.5, 'carbs' => 2.2, 'fat' => 0.2],
        'Egg' => ['calories' => 143, 'protein' => 12.6, 'carbs' => 0.7, 'fat' => 9.5],
        'Eggplant' => ['calories' => 25, 'protein' => 1, 'carbs' => 6, 'fat' => 0.2],
        'Fresh Basil' => ['calories' => 23, 'protein' => 3.2, 'carbs' => 2.7, 'fat' => 0.6],
        'Chard' => ['calories' => 19, 'protein' => 1.8, 'carbs' => 3.7, 'fat' => 0.2],
        'Potato' => ['calories' => 77, 'protein' => 2, 'carbs' => 17, 'fat' => 0.1],
        'Purslane' => ['calories' => 16, 'protein' => 1.3, 'carbs' => 3.4, 'fat' => 0.1],
        'Sunflower Seeds' => ['calories' => 584, 'protein' => 20.8, 'carbs' => 20, 'fat' => 51.5],
        'Beef Liver' => ['calories' => 135, 'protein' => 20.4, 'carbs' => 3.9, 'fat' => 3.6],
        'Fermented Beetroot (Base)' => ['calories' => 40, 'protein' => 1.5, 'carbs' => 8, 'fat' => 0.2],
        'Saffron Rice (Base)' => ['calories' => 127.14, 'protein' => 2.54, 'carbs' => 28.07, 'fat' => 0.25],
        'Bell Pepper (Red)' => ['calories' => 31, 'protein' => 1, 'carbs' => 6, 'fat' => 0.3],
    ];

    foreach ($ingredientMacros as $name => $macros) {
        complexCarbIngredient($name, $macros);
    }

    foreach ([
        'Citrus Herb Salmon with Asparagus & Sweet Potato',
        'Grilled Salmon Mango Salsa',
        'Grilled Beef Steak Ratatouille & Saffron rice',
        'Beef Bibimbap',
        'Persian Herb Beef Stew',
        'Chili Beef Stuffed Peppers',
        'Grilled Chicken Chimichurri',
    ] as $mealName) {
        Meal::factory()->create([
            'name' => $mealName,
            'category' => RecipeCategory::Meal,
            'meal_type' => MealType::Main,
            'library_sort_order' => 500,
            'total_calories' => 350,
            'total_protein' => 30,
            'total_carbs' => 30,
            'total_fat' => 12,
        ]);
    }
}

test('balanced rotation mains use varied complex carbs instead of steamed white basmati', function (): void {
    seedComplexCarbRefinerFixtures();

    app(BalancedComplexCarbRecipeRefiner::class)->refine();

    $expectedCarbSources = [
        'Citrus Herb Salmon with Asparagus & Sweet Potato' => 'Sweet Potato',
        'Grilled Salmon Mango Salsa' => 'Pumpkin',
        'Grilled Beef Steak Ratatouille & Saffron rice' => 'Saffron Rice (Base)',
        'Beef Bibimbap' => 'Cooked Quinoa (Base)',
        'Persian Herb Beef Stew' => 'Steamed Basmati Rice (Base)',
        'Chili Beef Stuffed Peppers' => 'Cooked Quinoa (Base)',
        'Grilled Chicken Chimichurri' => 'Sweet Potato',
    ];

    foreach ($expectedCarbSources as $mealName => $carbIngredient) {
        $meal = Meal::query()->where('name', $mealName)->firstOrFail();
        $ingredientNames = $meal->fresh(['ingredients'])->ingredients->pluck('name')->all();

        expect($ingredientNames)->toContain($carbIngredient);

        if ($mealName !== 'Persian Herb Beef Stew') {
            expect($ingredientNames)->not->toContain('Steamed Basmati Rice (Base)');
        }
    }
});
