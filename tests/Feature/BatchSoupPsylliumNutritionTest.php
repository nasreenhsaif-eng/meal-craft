<?php

use App\Enums\MealType;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\BalancedCanonicalMealRecipeRefiner;
use App\Services\BalancedMealLibraryConfigurator;
use App\Support\BoneBrothBaseRecipe;

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
        'cumin powder',
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
        BalancedCanonicalMealRecipeRefiner::BUTTERNUT_SQUASH_SOUP_NAME,
        'Tomato Basil Soup',
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

    Meal::factory()->create([
        'name' => BalancedCanonicalMealRecipeRefiner::VEGAN_MUSHROOM_SOUP_NAME,
        'meal_type' => MealType::Soup,
    ]);

    Meal::factory()->create([
        'name' => BalancedCanonicalMealRecipeRefiner::MISO_MUSHROOM_SOUP_NAME,
        'meal_type' => MealType::Soup,
    ]);

    Meal::factory()->create([
        'name' => BalancedMealLibraryConfigurator::BONE_BROTH_MEAL_NAME,
        'meal_type' => MealType::Soup,
    ]);

    Meal::factory()->create([
        'name' => BalancedCanonicalMealRecipeRefiner::RED_LENTIL_TURMERIC_SOUP_NAME,
        'meal_type' => MealType::Soup,
    ]);

    Meal::factory()->create([
        'name' => BalancedCanonicalMealRecipeRefiner::CAULIFLOWER_GINGER_SOUP_NAME,
        'meal_type' => MealType::Soup,
    ]);

    Meal::factory()->create([
        'name' => BalancedCanonicalMealRecipeRefiner::LENTIL_CARROT_SOUP_NAME,
        'meal_type' => MealType::Soup,
    ]);

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

    $mushroom = Meal::queryForMealLibrary()
        ->where('name', BalancedCanonicalMealRecipeRefiner::VEGAN_MUSHROOM_SOUP_NAME)
        ->with('ingredients')
        ->first();

    expect($mushroom)->not->toBeNull()
        ->and($mushroom->is_bulk)->toBeTrue()
        ->and((float) $mushroom->servings_count)->toBe((float) BalancedCanonicalMealRecipeRefiner::VEGAN_MUSHROOM_SOUP_BATCH_SERVINGS_COUNT)
        ->and((float) $mushroom->ingredients->firstWhere('name', 'Mushrooms')->pivot->amount_grams)->toBe(600.0)
        ->and((float) $mushroom->ingredients->firstWhere('name', 'Bone Broth (Base)')->pivot->amount_grams)->toBe(250.0)
        ->and((float) $mushroom->ingredients->firstWhere('name', 'Psyllium Husks')->pivot->amount_grams)
        ->toBe(BalancedCanonicalMealRecipeRefiner::VEGAN_MUSHROOM_SOUP_PSYLLIUM_BATCH_GRAMS);

    $misoMushroom = Meal::queryForMealLibrary()
        ->where('name', BalancedCanonicalMealRecipeRefiner::MISO_MUSHROOM_SOUP_NAME)
        ->with('ingredients')
        ->first();

    expect($misoMushroom)->not->toBeNull()
        ->and($misoMushroom->is_bulk)->toBeTrue()
        ->and((float) $misoMushroom->servings_count)->toBe((float) BalancedCanonicalMealRecipeRefiner::MISO_MUSHROOM_SOUP_BATCH_SERVINGS_COUNT)
        ->and((float) $misoMushroom->ingredients->firstWhere('name', 'Mushrooms')->pivot->amount_grams)->toBe(700.0)
        ->and((float) $misoMushroom->ingredients->firstWhere('name', 'Water (Filtered)')->pivot->amount_grams)->toBe(2000.0)
        ->and((float) $misoMushroom->ingredients->firstWhere('name', 'Miso Paste')->pivot->amount_grams)->toBe(100.0)
        ->and((float) $misoMushroom->ingredients->firstWhere('name', 'Psyllium Husks')->pivot->amount_grams)
        ->toBe(BalancedCanonicalMealRecipeRefiner::MISO_MUSHROOM_SOUP_PSYLLIUM_BATCH_GRAMS);

    $boneBroth = Meal::queryForMealLibrary()
        ->where('name', BalancedMealLibraryConfigurator::BONE_BROTH_MEAL_NAME)
        ->with('ingredients')
        ->first();

    expect($boneBroth)->not->toBeNull()
        ->and($boneBroth->is_bulk)->toBeTrue()
        ->and((float) $boneBroth->servings_count)->toBe((float) BalancedMealLibraryConfigurator::BONE_BROTH_BATCH_SERVINGS_COUNT)
        ->and((float) $boneBroth->ingredients->firstWhere('name', BoneBrothBaseRecipe::NAME)->pivot->amount_grams)
        ->toBe(BalancedMealLibraryConfigurator::BONE_BROTH_SERVING_GRAMS * BalancedMealLibraryConfigurator::BONE_BROTH_BATCH_SERVINGS_COUNT)
        ->and($boneBroth->ingredients->firstWhere('name', 'Psyllium Husks'))->toBeNull();

    $redLentil = Meal::queryForMealLibrary()
        ->where('name', BalancedCanonicalMealRecipeRefiner::RED_LENTIL_TURMERIC_SOUP_NAME)
        ->with('ingredients')
        ->first();

    expect($redLentil)->not->toBeNull()
        ->and($redLentil->is_bulk)->toBeTrue()
        ->and((float) $redLentil->servings_count)->toBe((float) BalancedCanonicalMealRecipeRefiner::RED_LENTIL_TURMERIC_SOUP_BATCH_SERVINGS_COUNT)
        ->and((float) $redLentil->ingredients->firstWhere('name', 'Lentils (Red)')->pivot->amount_grams)->toBe(250.0)
        ->and((float) $redLentil->ingredients->firstWhere('name', 'Carrots')->pivot->amount_grams)->toBe(600.0)
        ->and((float) $redLentil->ingredients->firstWhere('name', 'Water (Filtered)')->pivot->amount_grams)->toBe(3800.0)
        ->and($redLentil->ingredients->firstWhere('name', 'Psyllium Husks'))->toBeNull();

    $cauliflower = Meal::queryForMealLibrary()
        ->where('name', BalancedCanonicalMealRecipeRefiner::CAULIFLOWER_GINGER_SOUP_NAME)
        ->with('ingredients')
        ->first();

    expect($cauliflower)->not->toBeNull()
        ->and($cauliflower->is_bulk)->toBeTrue()
        ->and((float) $cauliflower->servings_count)->toBe((float) BalancedCanonicalMealRecipeRefiner::CAULIFLOWER_GINGER_SOUP_BATCH_SERVINGS_COUNT)
        ->and((float) $cauliflower->ingredients->firstWhere('name', 'Cauliflower Florets')->pivot->amount_grams)->toBe(2200.0)
        ->and((float) $cauliflower->ingredients->firstWhere('name', 'Homemade Coconut Milk')->pivot->amount_grams)->toBe(450.0)
        ->and((float) $cauliflower->ingredients->firstWhere('name', 'Vegetable Broth (Base)')->pivot->amount_grams)->toBe(20.0)
        ->and($cauliflower->ingredients->firstWhere('name', 'Psyllium Husks'))->toBeNull();

    $lentilCarrot = Meal::queryForMealLibrary()
        ->where('name', BalancedCanonicalMealRecipeRefiner::LENTIL_CARROT_SOUP_NAME)
        ->with('ingredients')
        ->first();

    expect($lentilCarrot)->not->toBeNull()
        ->and($lentilCarrot->is_bulk)->toBeTrue()
        ->and((float) $lentilCarrot->servings_count)->toBe((float) BalancedCanonicalMealRecipeRefiner::LENTIL_CARROT_SOUP_BATCH_SERVINGS_COUNT)
        ->and((float) $lentilCarrot->ingredients->firstWhere('name', 'French Lentils')->pivot->amount_grams)->toBe(220.0)
        ->and((float) $lentilCarrot->ingredients->firstWhere('name', 'Carrots')->pivot->amount_grams)->toBe(1200.0)
        ->and((float) $lentilCarrot->ingredients->firstWhere('name', 'Water (Filtered)')->pivot->amount_grams)->toBe(3600.0)
        ->and($lentilCarrot->ingredients->firstWhere('name', 'Psyllium Husks'))->toBeNull();
});
