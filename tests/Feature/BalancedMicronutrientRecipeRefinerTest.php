<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\CustomerProfile;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\BalancedMicronutrientRecipeRefiner;
use App\Services\BalancedWeeklyRotationSchedule;
use App\Services\MicronutrientBoostCatalog;
use App\Services\Nutrition\DayMicronutrientCoverageAnalyzer;
use App\Services\RecipeNutritionCalculator;
use App\Support\NutrientDailyRdi;

test('isocaloric boost preserves meal calories while increasing target micronutrient', function () {
    $spinach = Ingredient::query()->firstOrCreate(
        ['name' => 'Spinach (Fresh)'],
        [
            'calories' => 23,
            'protein' => 2.9,
            'carbs' => 3.6,
            'fat' => 0.4,
            'iron' => 2.7,
            'b9_folate' => 194,
            'magnesium' => 79,
            'micronutrients' => [
                'potassium' => 558,
                'fiber' => 2.2,
                'vitamin_c' => 28.1,
                'vitamin_a' => 469,
            ],
            'is_verified' => true,
        ],
    );

    $zucchini = Ingredient::factory()->create([
        'name' => 'Test Refiner Zucchini '.uniqid(),
        'calories' => 17,
        'protein' => 1.2,
        'carbs' => 3.1,
        'fat' => 0.3,
        'iron' => 0.4,
        'micronutrients' => ['potassium' => 261],
        'is_verified' => true,
    ]);

    $meal = Meal::factory()->create([
        'name' => 'Test Refiner Side Salad '.uniqid(),
        'meal_type' => MealType::Salad,
        'category' => RecipeCategory::SideSalad,
    ]);

    $meal->ingredients()->sync([
        $zucchini->id => ['amount_grams' => 120, 'amount' => 120, 'unit' => 'g'],
        $spinach->id => ['amount_grams' => 20, 'amount' => 20, 'unit' => 'g'],
    ]);

    $meal->load('ingredients');
    $before = RecipeNutritionCalculator::fromMeal($meal);
    $beforeIron = (float) $before['iron'];

    $refiner = app(BalancedMicronutrientRecipeRefiner::class);
    $changed = $refiner->applyIsocaloricBoost($meal->fresh(['ingredients']), 'iron');

    expect($changed)->toBeTrue();

    $after = RecipeNutritionCalculator::fromMeal($meal->fresh(['ingredients']));

    expect((float) $after['calories'])->toEqualWithDelta((float) $before['calories'], 0.5)
        ->and((float) $after['iron'])->toBeGreaterThan($beforeIron);
});

test('micronutrient refiner runs against rotation meals when library is present', function () {
    $sideSalad = Meal::queryForMealLibrary()
        ->where('name', BalancedWeeklyRotationSchedule::VEGAN_SIDE_SALADS[0])
        ->first();

    if ($sideSalad === null) {
        $this->markTestSkipped('Balanced rotation side salads are not seeded.');
    }

    $updated = app(BalancedMicronutrientRecipeRefiner::class)->refine();

    expect($updated)->toBeArray();
});

test('isocaloric tahini boost preserves meal calories while increasing calcium', function () {
    $tahini = Ingredient::query()->firstOrCreate(
        ['name' => 'Tahini'],
        [
            'calories' => 595,
            'protein' => 17,
            'carbs' => 21.2,
            'fat' => 53.8,
            'micronutrients' => [
                'calcium' => 426,
                'vitamin_k2' => 8,
                'sodium' => 115,
            ],
            'is_verified' => true,
        ],
    );

    $zucchini = Ingredient::factory()->create([
        'name' => 'Test Refiner Zucchini '.uniqid(),
        'calories' => 17,
        'protein' => 1.2,
        'carbs' => 3.1,
        'fat' => 0.3,
        'micronutrients' => ['potassium' => 261],
        'is_verified' => true,
    ]);

    $meal = Meal::factory()->create([
        'name' => 'Test Refiner Tahini Meal '.uniqid(),
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
    ]);

    $meal->ingredients()->sync([
        $zucchini->id => ['amount_grams' => 200, 'amount' => 200, 'unit' => 'g'],
        $tahini->id => ['amount_grams' => 10, 'amount' => 10, 'unit' => 'g'],
    ]);

    $meal->load('ingredients');
    $before = RecipeNutritionCalculator::fromMeal($meal);
    $beforeCalcium = (float) $before['calcium'];

    $refiner = app(BalancedMicronutrientRecipeRefiner::class);
    $changed = $refiner->applyIsocaloricBoost($meal->fresh(['ingredients']), 'calcium', 4.0);

    expect($changed)->toBeTrue();

    $after = RecipeNutritionCalculator::fromMeal($meal->fresh(['ingredients']));

    expect((float) $after['calories'])->toEqualWithDelta((float) $before['calories'], 0.5)
        ->and((float) $after['calcium'])->toBeGreaterThan($beforeCalcium);
});

test('isocaloric salmon boost preserves meal calories while increasing b12', function () {
    $salmon = Ingredient::query()->firstOrCreate(
        ['name' => 'Salmon'],
        [
            'calories' => 208,
            'protein' => 20.4,
            'carbs' => 0,
            'fat' => 13.4,
            'b12' => 3.2,
            'micronutrients' => [
                'vitamin_k2' => 1.5,
            ],
            'is_verified' => true,
        ],
    );

    $zucchini = Ingredient::factory()->create([
        'name' => 'Test Refiner Zucchini '.uniqid(),
        'calories' => 17,
        'protein' => 1.2,
        'carbs' => 3.1,
        'fat' => 0.3,
        'is_verified' => true,
    ]);

    $meal = Meal::factory()->create([
        'name' => 'Test Refiner Salmon Meal '.uniqid(),
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
    ]);

    $meal->ingredients()->sync([
        $zucchini->id => ['amount_grams' => 150, 'amount' => 150, 'unit' => 'g'],
        $salmon->id => ['amount_grams' => 100, 'amount' => 100, 'unit' => 'g'],
    ]);

    $meal->load('ingredients');
    $before = RecipeNutritionCalculator::fromMeal($meal);
    $beforeSalmonGrams = (float) $meal->ingredients->firstWhere('id', $salmon->id)->pivot->amount_grams;

    $refiner = app(BalancedMicronutrientRecipeRefiner::class);
    $changed = $refiner->applyIsocaloricBoost($meal->fresh(['ingredients']), 'b12', 4.0);

    expect($changed)->toBeTrue();

    $fresh = $meal->fresh(['ingredients']);
    $afterSalmonGrams = (float) $fresh->ingredients->firstWhere('id', $salmon->id)->pivot->amount_grams;
    $after = RecipeNutritionCalculator::fromMeal($fresh);

    expect((float) $after['calories'])->toEqualWithDelta((float) $before['calories'], 0.5)
        ->and($afterSalmonGrams)->toBeGreaterThan($beforeSalmonGrams);
});

test('seeded library day one reports stronger calcium and b12 after refinements', function () {
    $profile = CustomerProfile::factory()->create([
        'daily_calorie_target' => 1500,
        'protein_percentage' => 40,
        'carb_percentage' => 30,
        'fat_percentage' => 30,
    ]);

    $sideSalad = Meal::queryForMealLibrary()
        ->where('name', BalancedWeeklyRotationSchedule::VEGAN_SIDE_SALADS[0])
        ->first();

    if ($sideSalad === null) {
        $this->markTestSkipped('Balanced rotation meals are not seeded.');
    }

    $report = DayMicronutrientCoverageAnalyzer::simulateFullCraftDay(
        $profile,
        1,
        1500.0,
        ['side_salad', 'dessert'],
    );

    $calcium = collect($report['nutrients'])->firstWhere('key', 'calcium');
    $b12 = collect($report['nutrients'])->firstWhere('key', 'b12');

    expect($calcium)->not->toBeNull()
        ->and($b12)->not->toBeNull()
        ->and((float) $calcium['percent'])->toBeGreaterThan(65.0)
        ->and((float) $b12['percent'])->toBeGreaterThan(NutrientDailyRdi::FLOOR_TARGET_PERCENT - 1);
});

test('reference full craft day simulation can be audited at enforced tier', function () {
    $profile = CustomerProfile::factory()->create([
        'daily_calorie_target' => 1500,
        'protein_percentage' => 40,
        'carb_percentage' => 30,
        'fat_percentage' => 30,
    ]);

    $breakfast = Meal::queryForMealLibrary()->where('name', BalancedWeeklyRotationSchedule::EGG_BREAKFASTS[0])->first();

    if ($breakfast === null) {
        $this->markTestSkipped('Balanced rotation meals are not seeded.');
    }

    $report = DayMicronutrientCoverageAnalyzer::simulateFullCraftDay(
        $profile,
        1,
        1500.0,
        ['side_salad', 'dessert'],
    );

    expect($report)->toHaveKeys(['nutrients', 'day_calories', 'enforced', 'passes'])
        ->and($report['enforced'])->toBeTrue()
        ->and($report['day_calories'])->toBeGreaterThan(0);
});

test('purslane is registered in boost catalog and ingredient library', function () {
    expect(MicronutrientBoostCatalog::isGreenBoostIngredient('Purslane'))->toBeTrue()
        ->and(in_array('Purslane', MicronutrientBoostCatalog::BOOST_INGREDIENTS, true))->toBeTrue()
        ->and(MicronutrientBoostCatalog::isChiaAllowedBoost('Walnuts'))->toBeTrue()
        ->and(MicronutrientBoostCatalog::isChiaAllowedBoost('Chickpeas'))->toBeFalse();

    $purslane = Ingredient::query()->firstOrCreate(
        ['name' => 'Purslane'],
        [
            'calories' => 20,
            'protein' => 2,
            'carbs' => 3.4,
            'fat' => 0.4,
            'iron' => 2,
            'b9_folate' => 15,
            'magnesium' => 68,
            'micronutrients' => [
                'calcium' => 65,
                'potassium' => 494,
                'vitamin_c' => 21,
                'vitamin_a' => 260,
            ],
            'is_verified' => true,
        ],
    );

    expect($purslane->exists)->toBeTrue();
});

test('beef liver boost is allowed only for ground beef or dedicated liver meals', function () {
    expect(MicronutrientBoostCatalog::allowsBeefLiverBoost(
        'Seared Beef Liver w Caramelized Onion, Spinach & Chimichurri',
        [],
    ))->toBeTrue()
        ->and(MicronutrientBoostCatalog::allowsBeefLiverBoost(
            'Beef Bibimbap',
            ['Beef Ground Lean' => 85.0],
        ))->toBeTrue()
        ->and(MicronutrientBoostCatalog::allowsBeefLiverBoost(
            'Persian Herb Beef Stew',
            ['Beef Chuck Roast' => 124.0],
        ))->toBeFalse()
        ->and(MicronutrientBoostCatalog::allowsBeefLiverBoost(
            'Grilled Beef Steak Ratatouille & Saffron rice',
            ['Beef Sirloin' => 85.0],
        ))->toBeFalse();
});

test('chia breakfast isocaloric boost uses seeds not leafy greens', function () {
    $tahini = Ingredient::query()->firstOrCreate(
        ['name' => 'Tahini'],
        [
            'calories' => 595,
            'protein' => 17,
            'carbs' => 21.2,
            'fat' => 53.8,
            'micronutrients' => ['calcium' => 426, 'sodium' => 115],
            'is_verified' => true,
        ],
    );

    Ingredient::query()->firstOrCreate(
        ['name' => 'Purslane'],
        [
            'calories' => 20,
            'protein' => 2,
            'carbs' => 3.4,
            'fat' => 0.4,
            'iron' => 2,
            'micronutrients' => ['calcium' => 65, 'potassium' => 494],
            'is_verified' => true,
        ],
    );

    $base = Ingredient::query()->firstOrCreate(
        ['name' => 'Coconut Chia Pudding (Base)'],
        [
            'calories' => 120,
            'protein' => 3,
            'carbs' => 10,
            'fat' => 8,
            'is_verified' => true,
        ],
    );

    $meal = Meal::factory()->create([
        'name' => BalancedWeeklyRotationSchedule::CHIA_BREAKFASTS[0],
        'meal_type' => MealType::Breakfast,
        'category' => RecipeCategory::Breakfast,
    ]);

    $meal->ingredients()->sync([
        $base->id => ['amount_grams' => 75, 'amount' => 75, 'unit' => 'g'],
        $tahini->id => ['amount_grams' => 20, 'amount' => 20, 'unit' => 'g'],
    ]);

    $refiner = app(BalancedMicronutrientRecipeRefiner::class);
    $changed = $refiner->applyIsocaloricBoost($meal->fresh(['ingredients']), 'calcium');

    expect($changed)->toBeTrue();

    $fresh = $meal->fresh(['ingredients']);
    $names = $fresh->ingredients->pluck('name')->all();

    $allowedBoostPresent = collect($names)->contains(
        fn (string $n): bool => MicronutrientBoostCatalog::isChiaAllowedBoost($n),
    );

    expect(collect($names)->contains(fn (string $n): bool => MicronutrientBoostCatalog::isGreenBoostIngredient($n)))->toBeFalse()
        ->and($names)->not->toContain('Chickpeas')
        ->and($allowedBoostPresent)->toBeTrue();
});

test('spinach boost is skipped when recipe already exceeds cap', function () {
    $spinach = Ingredient::query()->firstOrCreate(
        ['name' => 'Spinach (Fresh)'],
        [
            'calories' => 23,
            'protein' => 2.9,
            'carbs' => 3.6,
            'fat' => 0.4,
            'iron' => 2.7,
            'b9_folate' => 194,
            'magnesium' => 79,
            'micronutrients' => ['potassium' => 558, 'fiber' => 2.2],
            'is_verified' => true,
        ],
    );

    $purslane = Ingredient::query()->firstOrCreate(
        ['name' => 'Purslane'],
        [
            'calories' => 20,
            'protein' => 2,
            'carbs' => 3.4,
            'fat' => 0.4,
            'iron' => 2,
            'micronutrients' => ['potassium' => 494, 'calcium' => 65],
            'is_verified' => true,
        ],
    );

    $zucchini = Ingredient::factory()->create([
        'name' => 'Test Refiner Zucchini '.uniqid(),
        'calories' => 17,
        'protein' => 1.2,
        'carbs' => 3.1,
        'fat' => 0.3,
        'iron' => 0.4,
        'micronutrients' => ['potassium' => 261],
        'is_verified' => true,
    ]);

    $meal = Meal::factory()->create([
        'name' => 'Test Spinach Cap Meal '.uniqid(),
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
    ]);

    $meal->ingredients()->sync([
        $zucchini->id => ['amount_grams' => 120, 'amount' => 120, 'unit' => 'g'],
        $spinach->id => ['amount_grams' => 50, 'amount' => 50, 'unit' => 'g'],
    ]);

    $beforeSpinach = 50.0;

    $refiner = app(BalancedMicronutrientRecipeRefiner::class);
    $refiner->applyIsocaloricBoost($meal->fresh(['ingredients']), 'iron');

    $fresh = $meal->fresh(['ingredients']);
    $afterSpinach = (float) $fresh->ingredients->firstWhere('id', $spinach->id)?->pivot->amount_grams;
    $purslaneGrams = (float) $fresh->ingredients->firstWhere('id', $purslane->id)?->pivot->amount_grams;

    expect($afterSpinach)->toEqual($beforeSpinach)
        ->and($purslaneGrams)->toBeGreaterThan(0);
});
