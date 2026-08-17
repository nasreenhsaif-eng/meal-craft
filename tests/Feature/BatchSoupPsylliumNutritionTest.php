<?php

use App\Enums\MealType;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\BalancedCanonicalMealRecipeRefiner;
use App\Services\BalancedMealLibraryConfigurator;

test('every library soup is a bulk batch with fifteen grams psyllium husk per serving', function () {
    $ingredientNames = [
        'Psyllium Husks',
        'Bone Broth (Base)',
        'Mushrooms',
        'Water (Filtered)',
        'Miso Paste',
        'Spring Onion',
        'Ginger (Raw)',
        'White Onion',
        'Homemade Coconut Milk',
        'Vegetable Stock',
        'Garlic',
        'Olive Oil',
        'Turmeric Powder',
        'Thyme (Fresh)',
        'Tomato (Raw)',
        'Fresh Basil',
        'Vegetable Broth (Base)',
        'Smoked Paprika',
        'Lentils (Red)',
        'Carrots',
        'Spinach (Fresh)',
        'Cumin Seeds',
        'Lemon Juice',
        'Cauliflower Florets',
        'Black Pepper',
        'French Lentils',
        'Coriander Seeds',
        'Fresh Parsley',
        'Sweet Potato',
        'Fennel Bulb',
        'Butternut Squash',
        'Nutmeg',
        'Pumpkin Seeds',
        'Garlic (Raw)',
        'Seaweed (Nori)',
        'Shichimi Togarashi (Base)',
        'Sea Salt',
        'Olive Oil (Extra Virgin)',
        'Sesame Oil',
        'Vegetable Broth (Base)',
    ];

    foreach ($ingredientNames as $name) {
        Ingredient::factory()->create([
            'name' => $name,
            'usda_food_category' => 'Pantry',
            'calories' => 100,
            'protein' => 5,
            'carbs' => 10,
            'fat' => 2,
        ]);
    }

    $soupNames = [
        'Miso Mushroom Soup',
        BalancedMealLibraryConfigurator::BONE_BROTH_MEAL_NAME,
        'Vegan Mushroom Soup',
        BalancedCanonicalMealRecipeRefiner::BUTTERNUT_SQUASH_SOUP_NAME,
        'Tomato Basil Soup',
        'Red Lentil Turmeric Soup',
        'Cauliflower Ginger Soup',
        'Lentil Carrot Soup',
        'Sweet Potato Fennel Soup',
        'Miso Carrot Ginger Soup',
        'Carrot Cumin Soup',
    ];

    foreach ($soupNames as $name) {
        Meal::factory()->create([
            'name' => $name,
            'meal_type' => MealType::Soup,
        ]);
    }

    app(BalancedCanonicalMealRecipeRefiner::class)->refine();

    $expectedBatchPsyllium = BalancedCanonicalMealRecipeRefiner::BATCH_SOUP_PSYLLIUM_TABLESPOON_GRAMS
        * BalancedCanonicalMealRecipeRefiner::BATCH_SOUP_SERVINGS_COUNT;

    foreach ($soupNames as $name) {
        $meal = Meal::queryForMealLibrary()->where('name', $name)->with('ingredients')->first();

        expect($meal)->not->toBeNull()
            ->and($meal->is_bulk)->toBeTrue()
            ->and((float) $meal->servings_count)->toBe((float) BalancedCanonicalMealRecipeRefiner::BATCH_SOUP_SERVINGS_COUNT);

        $psyllium = $meal->ingredients->firstWhere('name', 'Psyllium Husks');

        expect($psyllium)->not->toBeNull()
            ->and((float) $psyllium->pivot->amount_grams)->toBe($expectedBatchPsyllium);
    }
});
