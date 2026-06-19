<?php

use App\Enums\MealPlanSchemaType;
use App\Enums\MealPlanSlotType;
use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Services\BalancedCanonicalMealRecipeRefiner;
use App\Services\BalancedWeeklyMealPlanBuilder;
use App\Services\BalancedWeeklyRotationSchedule;
use App\Support\WholeFoodDietPolicy;

/**
 * @param  array{category?: RecipeCategory, meal_type?: MealType, calories?: float}  $overrides
 */
function weeklyPlanDeckMeal(string $name, array $overrides = []): Meal
{
    return Meal::factory()->create(array_merge([
        'name' => $name,
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
        'total_calories' => 360,
        'total_protein' => 30,
        'total_carbs' => 35,
        'total_fat' => 12,
        'library_sort_order' => 500,
    ], $overrides));
}

function seedBalancedWeeklyPlanDeck(): void
{
    Ingredient::query()->create([
        'name' => 'Bone Broth (Base)',
        'usda_food_category' => 'Base Ingredient',
        'calories' => 63,
        'protein' => 5.8,
        'carbs' => 0.6,
        'fat' => 4,
        'b6' => 0,
        'b9_folate' => 0,
        'b12' => 0,
        'iron' => 0,
        'magnesium' => 0,
        'micronutrients' => [],
        'is_verified' => true,
    ]);

    foreach (BalancedWeeklyRotationSchedule::allScheduledMealNames() as $name) {
        weeklyPlanDeckMeal($name);
    }
}

test('balanced weekly plan builder creates seven day rotating menus with twelve slots per day', function (): void {
    seedBalancedWeeklyPlanDeck();

    $result = app(BalancedWeeklyMealPlanBuilder::class)->build(refineRecipes: false);

    $plan = $result['plan'];
    $slotsPerDay = count(MealPlanSlotType::daySlotTemplate());

    expect($plan->schema_type)->toBe(MealPlanSchemaType::WeeklyStructured)
        ->and($plan->name)->toBe(BalancedWeeklyMealPlanBuilder::PLAN_NAME)
        ->and($result['slots'])->toBe(7 * $slotsPerDay)
        ->and($plan->dayMeals()->count())->toBe($result['slots'] * 2);

    $dayOneChia = $plan->dayMeals()
        ->where('day_number', 1)
        ->where('slot_type', MealPlanSlotType::Breakfast->value)
        ->where('slot_index', 1)
        ->where('is_option_b', false)
        ->first()
        ?->meal?->name;

    $dayTwoChia = $plan->dayMeals()
        ->where('day_number', 2)
        ->where('slot_type', MealPlanSlotType::Breakfast->value)
        ->where('slot_index', 1)
        ->where('is_option_b', false)
        ->first()
        ?->meal?->name;

    expect($dayOneChia)->toBe('Blueberry Walnut Chia Pudding')
        ->and($dayTwoChia)->toBe('Mango Pumpkin Seed Chia Pudding');

    foreach (range(1, 7) as $day) {
        $veganSoup = $plan->dayMeals()
            ->where('day_number', $day)
            ->where('slot_type', MealPlanSlotType::Soup->value)
            ->where('slot_index', 1)
            ->where('is_option_b', false)
            ->first()
            ?->meal?->name;

        $boneBroth = $plan->dayMeals()
            ->where('day_number', $day)
            ->where('slot_type', MealPlanSlotType::Soup->value)
            ->where('slot_index', 2)
            ->where('is_option_b', false)
            ->first()
            ?->meal?->name;

        expect($veganSoup)->toBe(BalancedWeeklyRotationSchedule::VEGAN_SOUP)
            ->and($boneBroth)->toBe('Bone Broth Cup');
    }
});

test('canonical meal refiner removes processed ingredients from deck meals', function (): void {
    seedBalancedWeeklyPlanDeck();

    $chia = Ingredient::query()->create([
        'name' => 'Chia Seeds',
        'usda_food_category' => 'Fats/Nuts',
        'calories' => 486,
        'protein' => 16.5,
        'carbs' => 42.1,
        'fat' => 30.7,
        'b6' => 0,
        'b9_folate' => 0,
        'b12' => 0,
        'iron' => 0,
        'magnesium' => 0,
        'micronutrients' => [],
    ]);

    Ingredient::query()->create([
        'name' => 'Coconut Water',
        'usda_food_category' => 'Liquids',
        'calories' => 19,
        'protein' => 0.7,
        'carbs' => 3.7,
        'fat' => 0.2,
        'b6' => 0,
        'b9_folate' => 0,
        'b12' => 0,
        'iron' => 0,
        'magnesium' => 0,
        'micronutrients' => [],
    ]);

    Ingredient::query()->create([
        'name' => 'Homemade Coconut Milk',
        'usda_food_category' => 'Base Ingredient',
        'calories' => 70.8,
        'protein' => 0.66,
        'carbs' => 3.04,
        'fat' => 6.7,
        'b6' => 0,
        'b9_folate' => 0,
        'b12' => 0,
        'iron' => 0,
        'magnesium' => 0,
        'micronutrients' => [],
        'is_verified' => true,
    ]);

    Ingredient::query()->create([
        'name' => 'Blueberries',
        'usda_food_category' => 'Fruits',
        'calories' => 57,
        'protein' => 0.7,
        'carbs' => 14.5,
        'fat' => 0.3,
        'b6' => 0,
        'b9_folate' => 0,
        'b12' => 0,
        'iron' => 0,
        'magnesium' => 0,
        'micronutrients' => [],
    ]);

    Ingredient::query()->create([
        'name' => 'Walnuts',
        'usda_food_category' => 'Fats/Nuts',
        'calories' => 654,
        'protein' => 15.2,
        'carbs' => 13.7,
        'fat' => 65.2,
        'b6' => 0,
        'b9_folate' => 0,
        'b12' => 0,
        'iron' => 0,
        'magnesium' => 0,
        'micronutrients' => [],
    ]);

    Ingredient::query()->create([
        'name' => 'Pumpkin Seeds',
        'usda_food_category' => 'Fats/Nuts',
        'calories' => 559,
        'protein' => 30.2,
        'carbs' => 10.7,
        'fat' => 49.1,
        'b6' => 0,
        'b9_folate' => 0,
        'b12' => 0,
        'iron' => 0,
        'magnesium' => 0,
        'micronutrients' => [],
    ]);

    Ingredient::query()->create([
        'name' => 'Fresh Mint',
        'usda_food_category' => 'Herbs',
        'calories' => 70,
        'protein' => 3.75,
        'carbs' => 14.89,
        'fat' => 0.94,
        'b6' => 0,
        'b9_folate' => 0,
        'b12' => 0,
        'iron' => 0,
        'magnesium' => 0,
        'micronutrients' => [],
    ]);

    Ingredient::query()->create([
        'name' => 'Cinnamon',
        'usda_food_category' => 'Spices',
        'calories' => 247,
        'protein' => 4,
        'carbs' => 80.6,
        'fat' => 1.2,
        'b6' => 0,
        'b9_folate' => 0,
        'b12' => 0,
        'iron' => 0,
        'magnesium' => 0,
        'micronutrients' => [],
    ]);

    $meal = Meal::query()->where('name', 'Blueberry Walnut Chia Pudding')->firstOrFail();
    $meal->ingredients()->sync([
        $chia->id => ['amount_grams' => 25],
    ]);

    app(BalancedCanonicalMealRecipeRefiner::class)->refine('Blueberry Walnut Chia Pudding');

    $meal->refresh()->load('ingredients');
    $names = $meal->ingredients->pluck('name')->all();

    expect($names)->not->toContain('Protein Powder (Isolate)')
        ->and($names)->not->toContain('Almond Milk (Unsweetened)')
        ->and($names)->toContain('Coconut Water')
        ->and($names)->toContain('Pumpkin Seeds')
        ->and($names)->toContain('Cinnamon');
});

test('whole food policy flags meals with banned ingredients or missing tags', function (): void {
    $oats = Ingredient::query()->create([
        'name' => 'Oats (Rolled)',
        'usda_food_category' => 'Grains',
        'calories' => 389,
        'protein' => 16.9,
        'carbs' => 66.3,
        'fat' => 6.9,
        'b6' => 0,
        'b9_folate' => 0,
        'b12' => 0,
        'iron' => 0,
        'magnesium' => 0,
        'micronutrients' => [],
    ]);

    $meal = Meal::factory()->create([
        'name' => 'Bad Breakfast',
        'diet_tags' => ['Vegan'],
    ]);
    $meal->ingredients()->sync([$oats->id => ['amount_grams' => 30]]);

    $violations = WholeFoodDietPolicy::violationsForMeal($meal->fresh(['ingredients']));

    expect($violations)->toContain('Bad Breakfast: banned ingredient «Oats (Rolled)»')
        ->and($violations)->toContain('Bad Breakfast: missing diet tag «Dairy-free»')
        ->and($violations)->toContain('Bad Breakfast: missing diet tag «Gluten-free»');
});

test('whole food policy rejects excessive olive portions', function (): void {
    $olives = Ingredient::query()->create([
        'name' => 'Kalamata Olives',
        'usda_food_category' => 'Pantry',
        'calories' => 239,
        'protein' => 1.1,
        'carbs' => 4.3,
        'fat' => 25,
        'b6' => 0,
        'b9_folate' => 0,
        'b12' => 0,
        'iron' => 0,
        'magnesium' => 0,
        'micronutrients' => [],
    ]);

    $meal = Meal::factory()->create([
        'name' => 'Heavy Olive Plate',
        'diet_tags' => ['Vegan', 'Dairy-free', 'Gluten-free'],
    ]);
    $meal->ingredients()->sync([$olives->id => ['amount_grams' => 20]]);

    $violations = WholeFoodDietPolicy::violationsForMeal($meal->fresh(['ingredients']));

    expect($violations)->toContain('Heavy Olive Plate: olive portion too high (20g; max 15g per meal)');
});

test('rebuilding balanced weekly plan replaces existing plan with same name', function (): void {
    seedBalancedWeeklyPlanDeck();

    $first = app(BalancedWeeklyMealPlanBuilder::class)->build(refineRecipes: false);
    $second = app(BalancedWeeklyMealPlanBuilder::class)->build(refineRecipes: false);

    expect(MealPlan::query()->where('name', BalancedWeeklyMealPlanBuilder::PLAN_NAME)->count())->toBe(1)
        ->and($second['plan']->id)->not->toBe($first['plan']->id);
});

test('balanced weekly plan stores day-level 40/40/20 macro targets from diet protocol preset', function (): void {
    seedBalancedWeeklyPlanDeck();

    $builder = app(BalancedWeeklyMealPlanBuilder::class);
    [$dailyProtein, $dailyCarbs, $dailyFat] = $builder->referenceDailyMacros();

    $result = $builder->build(refineRecipes: false);
    $plan = $result['plan'];

    expect($dailyProtein)->toBe(150.0)
        ->and($dailyCarbs)->toBe(150.0)
        ->and($dailyFat)->toBe(33.33)
        ->and((float) $plan->target_total_calories / 7)->toBe(BalancedWeeklyMealPlanBuilder::REFERENCE_DAILY_CALORIES)
        ->and((float) $plan->target_total_protein_g / 7)->toBe($dailyProtein)
        ->and((float) $plan->target_total_carbs_g / 7)->toBe($dailyCarbs)
        ->and((float) $plan->target_total_fat_g / 7)->toBe($dailyFat);
});
