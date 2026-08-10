<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\CustomerProfile;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use App\Services\Nutrition\AdaptedMenuBuilder;
use App\Services\Nutrition\UserPlanCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @param  array<string, array{calories?: float, protein?: float, carbs?: float, fat?: float}>  $macrosByName
 * @param  array<string, float>  $gramsByName
 */
function tierPlateMeal(string $name, array $macrosByName, array $gramsByName): Meal
{
    $meal = Meal::factory()->create([
        'name' => $name,
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 400,
        'total_protein' => 35,
        'total_carbs' => 30,
        'total_fat' => 15,
        'library_sort_order' => 10,
    ]);

    $attach = [];

    foreach ($gramsByName as $ingredientName => $grams) {
        $macros = $macrosByName[$ingredientName] ?? [
            'calories' => 100,
            'protein' => 10,
            'carbs' => 5,
            'fat' => 2,
        ];

        $ingredient = Ingredient::factory()->create([
            'name' => $ingredientName,
            'calories' => $macros['calories'] ?? 100,
            'protein' => $macros['protein'] ?? 10,
            'carbs' => $macros['carbs'] ?? 5,
            'fat' => $macros['fat'] ?? 2,
        ]);

        $attach[$ingredient->id] = [
            'amount_grams' => $grams,
            'amount' => $grams,
            'unit' => 'g',
        ];
    }

    $meal->ingredients()->attach($attach);

    return $meal->fresh(['ingredients']);
}

function adaptedIngredientGrams(array $adapted, string $name): float
{
    $row = collect($adapted['ingredients'])->firstWhere('name', $name);

    return (float) ($row['adapted_amount_grams'] ?? 0);
}

test('user plan calculator returns tier protein and starch tables', function () {
    expect(UserPlanCalculator::tierPrimaryProteinGrams(1200))->toBe(120.0)
        ->and(UserPlanCalculator::tierPrimaryProteinGrams(1500))->toBe(150.0)
        ->and(UserPlanCalculator::tierComplexCarbGrams(1200))->toBe(100.0)
        ->and(UserPlanCalculator::tierComplexCarbGrams(1500))->toBe(125.0)
        ->and(UserPlanCalculator::fattyProteinStarchFactor(165))->toBe(1.0)
        ->and(UserPlanCalculator::fattyProteinStarchFactor(250))->toBeLessThan(1.0)
        ->and(UserPlanCalculator::roundToKitchenPortion(83.3))->toBe(85.0);
});

test('chicken main at 1200 pins 120g protein and 100g starch without dropping spices', function () {
    $user = User::factory()->create();
    $profile = CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 1200,
        'protein_percentage' => 40,
        'carb_percentage' => 30,
        'fat_percentage' => 30,
    ]);

    $meal = tierPlateMeal('Chicken Test Plate', [
        'Chicken Breast' => ['calories' => 165, 'protein' => 31, 'carbs' => 0, 'fat' => 3.6],
        'Sweet Potato' => ['calories' => 86, 'protein' => 1.6, 'carbs' => 20, 'fat' => 0.1],
        'Broccoli' => ['calories' => 34, 'protein' => 2.8, 'carbs' => 7, 'fat' => 0.4],
        'Garlic (Raw)' => ['calories' => 149, 'protein' => 6, 'carbs' => 33, 'fat' => 0.5],
        'Black Pepper' => ['calories' => 251, 'protein' => 10, 'carbs' => 64, 'fat' => 3],
        'Olive Oil (Extra Virgin)' => ['calories' => 884, 'protein' => 0, 'carbs' => 0, 'fat' => 100],
    ], [
        'Chicken Breast' => 150,
        'Sweet Potato' => 90,
        'Broccoli' => 80,
        'Garlic (Raw)' => 5,
        'Black Pepper' => 1,
        'Olive Oil (Extra Virgin)' => 4,
    ]);

    $adapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $meal, ['snap_to_tier' => true]);
    $mainTarget = (float) UserPlanCalculator::calculateUserPlan($profile)['scalable_slot_targets']['main_each']['calories'];

    expect(adaptedIngredientGrams($adapted, 'Chicken Breast'))->toBe(120.0)
        ->and(adaptedIngredientGrams($adapted, 'Sweet Potato'))->toBe(100.0)
        ->and(adaptedIngredientGrams($adapted, 'Garlic (Raw)'))->toBe(5.0)
        ->and(adaptedIngredientGrams($adapted, 'Black Pepper'))->toBe(1.0)
        ->and(adaptedIngredientGrams($adapted, 'Olive Oil (Extra Virgin)'))->toBe(4.0)
        ->and(count($adapted['ingredients']))->toBe(6)
        ->and((float) $adapted['adapted_nutrition']['calories'])->toBeGreaterThan($mainTarget - 40)
        ->and((float) $adapted['adapted_nutrition']['calories'])->toBeLessThan($mainTarget + 80);
});

test('salmon at 1200 uses less starch than chicken and keeps seasonings', function () {
    $user = User::factory()->create();
    $profile = CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 1200,
        'protein_percentage' => 40,
        'carb_percentage' => 30,
        'fat_percentage' => 30,
    ]);

    $sharedSides = [
        'Sweet Potato' => ['calories' => 86, 'protein' => 1.6, 'carbs' => 20, 'fat' => 0.1],
        'Broccoli' => ['calories' => 34, 'protein' => 2.8, 'carbs' => 7, 'fat' => 0.4],
        'Garlic (Raw)' => ['calories' => 149, 'protein' => 6, 'carbs' => 33, 'fat' => 0.5],
        'Black Pepper' => ['calories' => 251, 'protein' => 10, 'carbs' => 64, 'fat' => 3],
    ];

    $chicken = tierPlateMeal('Lean Chicken Plate', [
        'Chicken Breast' => ['calories' => 165, 'protein' => 31, 'carbs' => 0, 'fat' => 3.6],
        ...$sharedSides,
    ], [
        'Chicken Breast' => 150,
        'Sweet Potato' => 90,
        'Broccoli' => 80,
        'Garlic (Raw)' => 5,
        'Black Pepper' => 1,
    ]);

    $salmon = tierPlateMeal('Salmon Plate', [
        'Salmon' => ['calories' => 208, 'protein' => 20, 'carbs' => 0, 'fat' => 13],
        ...$sharedSides,
    ], [
        'Salmon' => 150,
        'Sweet Potato' => 90,
        'Broccoli' => 80,
        'Garlic (Raw)' => 5,
        'Black Pepper' => 1,
    ]);

    $adaptedChicken = AdaptedMenuBuilder::adaptMealForProfile($profile, $chicken, ['snap_to_tier' => true]);
    $adaptedSalmon = AdaptedMenuBuilder::adaptMealForProfile($profile, $salmon, ['snap_to_tier' => true]);

    expect(adaptedIngredientGrams($adaptedSalmon, 'Salmon'))->toBe(120.0)
        ->and(adaptedIngredientGrams($adaptedSalmon, 'Sweet Potato'))
        ->toBeLessThan(adaptedIngredientGrams($adaptedChicken, 'Sweet Potato'))
        ->and(adaptedIngredientGrams($adaptedSalmon, 'Broccoli'))->toBeGreaterThanOrEqual(80.0)
        ->and(adaptedIngredientGrams($adaptedSalmon, 'Garlic (Raw)'))->toBe(5.0)
        ->and(adaptedIngredientGrams($adaptedSalmon, 'Black Pepper'))->toBe(1.0);
});

test('fatty beef at 1500 cuts starch versus lean beef and keeps spice lines', function () {
    $user = User::factory()->create();
    $profile = CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 1500,
        'protein_percentage' => 40,
        'carb_percentage' => 30,
        'fat_percentage' => 30,
    ]);

    $lean = tierPlateMeal('Lean Beef Plate', [
        'Beef Sirloin' => ['calories' => 160, 'protein' => 30, 'carbs' => 0, 'fat' => 4],
        'Basmati Rice (Brown)' => ['calories' => 110, 'protein' => 2.5, 'carbs' => 23, 'fat' => 0.9],
        'Zucchini' => ['calories' => 17, 'protein' => 1.2, 'carbs' => 3.1, 'fat' => 0.3],
        'Oregano' => ['calories' => 265, 'protein' => 9, 'carbs' => 69, 'fat' => 4],
    ], [
        'Beef Sirloin' => 150,
        'Basmati Rice (Brown)' => 100,
        'Zucchini' => 70,
        'Oregano' => 1,
    ]);

    $fatty = tierPlateMeal('Fatty Beef Plate', [
        'Beef Ribeye' => ['calories' => 291, 'protein' => 24, 'carbs' => 0, 'fat' => 21],
        'Basmati Rice (Brown)' => ['calories' => 110, 'protein' => 2.5, 'carbs' => 23, 'fat' => 0.9],
        'Zucchini' => ['calories' => 17, 'protein' => 1.2, 'carbs' => 3.1, 'fat' => 0.3],
        'Oregano' => ['calories' => 265, 'protein' => 9, 'carbs' => 69, 'fat' => 4],
    ], [
        'Beef Ribeye' => 150,
        'Basmati Rice (Brown)' => 100,
        'Zucchini' => 70,
        'Oregano' => 1,
    ]);

    $adaptedLean = AdaptedMenuBuilder::adaptMealForProfile($profile, $lean, ['snap_to_tier' => true]);
    $adaptedFatty = AdaptedMenuBuilder::adaptMealForProfile($profile, $fatty, ['snap_to_tier' => true]);

    expect(adaptedIngredientGrams($adaptedFatty, 'Beef Ribeye'))->toBe(150.0)
        ->and(adaptedIngredientGrams($adaptedLean, 'Beef Sirloin'))->toBe(150.0)
        ->and(adaptedIngredientGrams($adaptedFatty, 'Basmati Rice (Brown)'))
        ->toBeLessThan(adaptedIngredientGrams($adaptedLean, 'Basmati Rice (Brown)'))
        ->and(adaptedIngredientGrams($adaptedFatty, 'Zucchini'))->toBeGreaterThanOrEqual(70.0)
        ->and(adaptedIngredientGrams($adaptedFatty, 'Oregano'))->toBe(1.0)
        ->and(adaptedIngredientGrams($adaptedLean, 'Oregano'))->toBe(1.0);
});

test('protein mains across tiers use the protein gram table', function (int $tier, float $proteinGrams) {
    $user = User::factory()->create();
    $profile = CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => $tier,
        'protein_percentage' => 40,
        'carb_percentage' => 30,
        'fat_percentage' => 30,
    ]);

    $meal = tierPlateMeal('Tier Chicken Plate', [
        'Chicken Breast' => ['calories' => 165, 'protein' => 31, 'carbs' => 0, 'fat' => 3.6],
        'Sweet Potato' => ['calories' => 86, 'protein' => 1.6, 'carbs' => 20, 'fat' => 0.1],
        'Broccoli' => ['calories' => 34, 'protein' => 2.8, 'carbs' => 7, 'fat' => 0.4],
        'Garlic (Raw)' => ['calories' => 149, 'protein' => 6, 'carbs' => 33, 'fat' => 0.5],
    ], [
        'Chicken Breast' => 150,
        'Sweet Potato' => 90,
        'Broccoli' => 80,
        'Garlic (Raw)' => 5,
    ]);

    $adapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $meal, ['snap_to_tier' => true]);

    expect(adaptedIngredientGrams($adapted, 'Chicken Breast'))->toBe($proteinGrams)
        ->and(adaptedIngredientGrams($adapted, 'Garlic (Raw)'))->toBe(5.0)
        ->and(count($adapted['ingredients']))->toBe(4);
})->with([
    [1000, 100.0],
    [1200, 120.0],
    [1500, 150.0],
    [1800, 180.0],
    [2000, 200.0],
]);
