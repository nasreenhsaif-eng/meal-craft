<?php

use App\Enums\MealPlanLibraryCategory;
use App\Enums\MealPlanSchemaType;
use App\Enums\MealPlanSlotType;
use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\User;
use App\Services\Nutrition\UserPlanCalculator;
use App\Support\SavoryEggBreakfastMeals;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $day
 * @param  array<string, list<int>>  $selections
 */
function sumSelectedDayCalories(array $day, array $selections): float
{
    $total = 0.0;

    foreach (['breakfasts', 'meals', 'sideSalads', 'desserts', 'soup'] as $categoryKey) {
        $meals = $day['categories'][$categoryKey] ?? [];
        $selectedIds = array_map('intval', $selections[$categoryKey] ?? []);

        if ($selectedIds === []) {
            foreach ($meals as $meal) {
                $total += (float) ($meal['macros']['calories'] ?? 0);
            }

            continue;
        }

        $selectedSet = array_flip($selectedIds);

        foreach ($meals as $meal) {
            if (isset($selectedSet[(int) ($meal['id'] ?? 0)])) {
                $total += (float) ($meal['macros']['calories'] ?? 0);
            }
        }
    }

    return $total;
}

/**
 * @param  array<string, mixed>  $day
 * @param  array<string, list<int>>  $selections
 */
function sumSelectedDayIron(array $day, array $selections): float
{
    $total = 0.0;

    foreach (['breakfasts', 'meals', 'sideSalads', 'desserts', 'soup'] as $categoryKey) {
        $meals = $day['categories'][$categoryKey] ?? [];
        $selectedIds = array_map('intval', $selections[$categoryKey] ?? []);

        if ($selectedIds === []) {
            foreach ($meals as $meal) {
                $total += (float) ($meal['detailView']['nutrition']['iron'] ?? 0);
            }

            continue;
        }

        $selectedSet = array_flip($selectedIds);

        foreach ($meals as $meal) {
            if (isset($selectedSet[(int) ($meal['id'] ?? 0)])) {
                $total += (float) ($meal['detailView']['nutrition']['iron'] ?? 0);
            }
        }
    }

    return $total;
}

test('guests cannot view a meal plan detail page', function (): void {
    $plan = MealPlan::query()->create([
        'name' => 'Guest Blocked Plan',
        'goal' => 'Test',
        'schema_type' => MealPlanSchemaType::WeeklyStructured,
        'target_total_calories' => 14000,
    ]);

    $this->get(route('admin.meal-plan-library.show', $plan))->assertRedirect();
});

test('authenticated users can view meal plan detail with day categories', function (): void {
    $user = User::factory()->create();

    $breakfast = Meal::factory()->create([
        'name' => 'Detail Breakfast',
        'category' => RecipeCategory::Breakfast,
        'meal_type' => MealType::Breakfast,
    ]);

    $main = Meal::factory()->create([
        'name' => 'Detail Main',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
    ]);

    $plan = MealPlan::query()->create([
        'name' => 'Detail Plan',
        'goal' => 'Weekly balanced rotation.',
        'schema_type' => MealPlanSchemaType::WeeklyStructured,
        'target_total_calories' => 14000,
    ]);

    $plan->dayMeals()->createMany([
        [
            'meal_id' => $breakfast->id,
            'day_number' => 1,
            'slot_type' => MealPlanSlotType::Breakfast,
            'slot_index' => 1,
            'is_option_b' => false,
        ],
        [
            'meal_id' => $main->id,
            'day_number' => 1,
            'slot_type' => MealPlanSlotType::Main,
            'slot_index' => 1,
            'is_option_b' => false,
        ],
    ]);

    $this->actingAs($user)
        ->get(route('admin.meal-plan-library.show', $plan))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/MealPlanDetail')
            ->where('mealPlan.name', 'Detail Plan')
            ->where('mealPlan.goal', 'Weekly balanced rotation.')
            ->has('days', 7)
            ->where('days.0.dayNumber', 1)
            ->where('days.0.label', 'Sun')
            ->has('days.0.categories.breakfasts', 1)
            ->has('days.0.categories.meals', 1)
            ->where('days.0.categories.breakfasts.0.title', 'Detail Breakfast')
            ->where('days.0.categories.meals.0.title', 'Detail Main')
            ->has('days.0.categories.breakfasts.0.detailView')
            ->has('ingredientProfiles')
            ->has('libraryUrl')
            ->has('planTiers')
            ->has('defaultPlanTier')
            ->has('tierPreviewUrl')
            ->where('dietProtocol', 'balanced'));
});

test('nutrient dense meal plans expose nutrient_dense diet protocol to the admin picker', function (): void {
    $user = User::factory()->create();

    $plan = MealPlan::query()->create([
        'name' => 'Nutrient Density Protocol',
        'goal' => 'Weekly nutrient density rotation.',
        'schema_type' => MealPlanSchemaType::WeeklyStructured,
        'plan_category' => MealPlanLibraryCategory::NutrientDense,
        'target_total_calories' => 10500,
    ]);

    $this->actingAs($user)
        ->get(route('admin.meal-plan-library.show', $plan))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/MealPlanDetail')
            ->where('dietProtocol', 'nutrient_dense'));
});

test('tbd weekly protocol plans use the customer onboarding nutrient dense picker', function (): void {
    $user = User::factory()->create();

    $plan = MealPlan::query()->create([
        'name' => 'TBD Weekly Protocol',
        'goal' => 'Weekly nutrient density rotation.',
        'schema_type' => MealPlanSchemaType::WeeklyStructured,
        'plan_category' => MealPlanLibraryCategory::Balanced,
        'target_total_calories' => 10500,
    ]);

    $this->actingAs($user)
        ->get(route('admin.meal-plan-library.show', $plan))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/MealPlanDetail')
            ->where('dietProtocol', 'nutrient_dense'));
});

test('meal plan tier preview returns tier-scaled days for admin', function (): void {
    $user = User::factory()->create();

    $egg = Ingredient::factory()->create([
        'name' => 'Egg',
        'calories' => 155,
        'protein' => 12.6,
        'carbs' => 1.1,
        'fat' => 10.6,
    ]);

    $breakfast = Meal::factory()->create([
        'name' => SavoryEggBreakfastMeals::mealNames()[0],
        'category' => RecipeCategory::Breakfast,
        'meal_type' => MealType::Breakfast,
        'total_calories' => 305,
        'total_protein' => 15,
        'total_carbs' => 8,
        'total_fat' => 22,
    ]);
    $breakfast->ingredients()->attach($egg->id, [
        'amount_grams' => 100,
        'amount' => 100,
        'unit' => 'g',
    ]);

    $plan = MealPlan::query()->create([
        'name' => 'Tier Preview Plan',
        'goal' => 'Nutrient density review.',
        'schema_type' => MealPlanSchemaType::WeeklyStructured,
        'plan_category' => MealPlanLibraryCategory::NutrientDense,
        'target_total_calories' => 10500,
    ]);

    $plan->dayMeals()->create([
        'meal_id' => $breakfast->id,
        'day_number' => 1,
        'slot_type' => MealPlanSlotType::Breakfast,
        'slot_index' => 1,
        'is_option_b' => false,
    ]);

    $at1500 = $this->actingAs($user)
        ->getJson(route('admin.meal-plan-library.tier-preview', [
            'mealPlan' => $plan,
            'plan_tier' => 1500,
        ]))
        ->assertOk()
        ->json('days.0.categories.breakfasts.0.macros.calories');

    $at2000 = $this->actingAs($user)
        ->getJson(route('admin.meal-plan-library.tier-preview', [
            'mealPlan' => $plan,
            'plan_tier' => 2000,
        ]))
        ->assertOk()
        ->json('days.0.categories.breakfasts.0.macros.calories');

    expect($at1500)->toBeNumeric()
        ->and($at2000)->toBeNumeric()
        ->and((float) $at2000)->toBeGreaterThan((float) $at1500);
});

test('meal plan tier preview reconciles a nutrient-dense day to the selected tier', function (): void {
    $user = User::factory()->create();

    $carbIngredient = Ingredient::factory()->create([
        'name' => 'Cooked Quinoa (Base)',
        'calories' => 120,
        'protein' => 4,
        'carbs' => 21,
        'fat' => 2,
        'usda_food_category' => 'Grains',
    ]);

    $proteinIngredient = Ingredient::factory()->create([
        'name' => 'Salmon (Raw)',
        'calories' => 208,
        'protein' => 20,
        'carbs' => 0,
        'fat' => 13,
        'usda_food_category' => 'Proteins',
    ]);

    $breakfast = Meal::factory()->create([
        'name' => 'Mediterranean Omelet',
        'category' => RecipeCategory::Breakfast,
        'meal_type' => MealType::Breakfast,
        'total_calories' => 444,
        'total_protein' => 35,
        'total_carbs' => 8,
        'total_fat' => 30,
    ]);
    $breakfast->ingredients()->attach($proteinIngredient->id, ['amount_grams' => 200]);

    $mainA = Meal::factory()->create([
        'name' => 'Salmon Plate',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
        'total_calories' => 360,
        'total_protein' => 42,
        'total_carbs' => 18,
        'total_fat' => 12,
    ]);
    $mainA->ingredients()->attach($proteinIngredient->id, ['amount_grams' => 150]);

    $mainB = Meal::factory()->create([
        'name' => 'Salmon Quinoa Bowl',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
        'total_calories' => 360,
        'total_protein' => 42,
        'total_carbs' => 35,
        'total_fat' => 11,
    ]);
    $mainB->ingredients()->attach($proteinIngredient->id, ['amount_grams' => 120]);
    $mainB->ingredients()->attach($carbIngredient->id, ['amount_grams' => 80]);

    $salad = Meal::factory()->create([
        'name' => 'Reconcile Side Salad',
        'category' => RecipeCategory::SideSalad,
        'meal_type' => MealType::Salad,
        'total_calories' => 117,
    ]);
    $salad->ingredients()->attach($carbIngredient->id, ['amount_grams' => 50]);

    $dessert = Meal::factory()->create([
        'name' => 'Chia Dessert',
        'category' => RecipeCategory::Dessert,
        'meal_type' => MealType::Dessert,
        'total_calories' => 201,
    ]);
    $dessert->ingredients()->attach($carbIngredient->id, ['amount_grams' => 60]);

    $plan = MealPlan::query()->create([
        'name' => 'Reconciled Preview Plan',
        'goal' => 'Tier reconciliation review.',
        'schema_type' => MealPlanSchemaType::WeeklyStructured,
        'plan_category' => MealPlanLibraryCategory::NutrientDense,
        'target_total_calories' => 10500,
    ]);

    $plan->dayMeals()->createMany([
        [
            'meal_id' => $breakfast->id,
            'day_number' => 1,
            'slot_type' => MealPlanSlotType::Breakfast,
            'slot_index' => 1,
            'is_option_b' => false,
        ],
        [
            'meal_id' => $mainA->id,
            'day_number' => 1,
            'slot_type' => MealPlanSlotType::Main,
            'slot_index' => 1,
            'is_option_b' => false,
        ],
        [
            'meal_id' => $mainB->id,
            'day_number' => 1,
            'slot_type' => MealPlanSlotType::Main,
            'slot_index' => 2,
            'is_option_b' => false,
        ],
        [
            'meal_id' => $salad->id,
            'day_number' => 1,
            'slot_type' => MealPlanSlotType::Salad,
            'slot_index' => 1,
            'is_option_b' => false,
        ],
        [
            'meal_id' => $dessert->id,
            'day_number' => 1,
            'slot_type' => MealPlanSlotType::Dessert,
            'slot_index' => 1,
            'is_option_b' => false,
        ],
    ]);

    $selections = [
        1 => [
            'breakfasts' => [$breakfast->id],
            'meals' => [$mainA->id, $mainB->id],
            'sideSalads' => [$salad->id],
            'desserts' => [$dessert->id],
        ],
    ];

    $payload = $this->actingAs($user)
        ->getJson(route('admin.meal-plan-library.tier-preview', [
            'mealPlan' => $plan,
            'plan_tier' => 1500,
            'selections' => json_encode($selections),
        ]))
        ->assertOk()
        ->json();

    $day = $payload['days'][0];
    $dayCalories = sumSelectedDayCalories($day, $selections[1]);

    expect($dayCalories)->toBeGreaterThan(0)
        ->and(abs($dayCalories - 1500))->toBeLessThanOrEqual(UserPlanCalculator::dayCalorieTolerance())
        ->and($day)->toHaveKey('reconciliationWarnings');

    $mainRow = collect($day['categories']['meals'] ?? [])->firstWhere('id', $mainA->id);

    expect($mainRow)->not->toBeNull()
        ->and($mainRow['kitchenIngredientRows'] ?? [])->not->toBeEmpty();

    $kitchenGrams = (float) ($mainRow['kitchenIngredientRows'][0]['amount'] ?? 0);
    $libraryGrams = (float) ($mainRow['editForm']['ingredientRows'][0]['amount'] ?? 0);

    expect($kitchenGrams)->toBeGreaterThan(0)
        ->and($libraryGrams)->toBeGreaterThan(0)
        ->and($kitchenGrams)->not->toEqual($libraryGrams);
});

test('meal plan tier preview closes calorie surplus from heavy dessert and protein mains at 1500', function (): void {
    $user = User::factory()->create();

    $carbIngredient = Ingredient::factory()->create([
        'name' => 'Cooked Quinoa (Base)',
        'calories' => 120,
        'protein' => 4,
        'carbs' => 21,
        'fat' => 2,
        'usda_food_category' => 'Grains',
    ]);

    $proteinIngredient = Ingredient::factory()->create([
        'name' => 'Salmon (Raw)',
        'calories' => 208,
        'protein' => 20,
        'carbs' => 0,
        'fat' => 13,
        'usda_food_category' => 'Proteins',
    ]);

    $breakfast = Meal::factory()->create([
        'name' => 'Mediterranean Omelet',
        'category' => RecipeCategory::Breakfast,
        'meal_type' => MealType::Breakfast,
        'total_calories' => 444,
        'total_protein' => 35,
        'total_carbs' => 8,
        'total_fat' => 30,
    ]);
    $breakfast->ingredients()->attach($proteinIngredient->id, ['amount_grams' => 200]);

    $mainA = Meal::factory()->create([
        'name' => 'Salmon Plate',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
        'total_calories' => 360,
        'total_protein' => 42,
        'total_carbs' => 18,
        'total_fat' => 12,
    ]);
    $mainA->ingredients()->attach($proteinIngredient->id, ['amount_grams' => 150]);

    $mainB = Meal::factory()->create([
        'name' => 'Salmon Quinoa Bowl',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
        'total_calories' => 360,
        'total_protein' => 42,
        'total_carbs' => 35,
        'total_fat' => 11,
    ]);
    $mainB->ingredients()->attach($proteinIngredient->id, ['amount_grams' => 120]);
    $mainB->ingredients()->attach($carbIngredient->id, ['amount_grams' => 80]);

    $salad = Meal::factory()->create([
        'name' => 'Reconcile Side Salad',
        'category' => RecipeCategory::SideSalad,
        'meal_type' => MealType::Salad,
        'total_calories' => 117,
    ]);
    $salad->ingredients()->attach($carbIngredient->id, ['amount_grams' => 50]);

    $dessert = Meal::factory()->create([
        'name' => 'Greek Yogurt Chia Dessert',
        'category' => RecipeCategory::Dessert,
        'meal_type' => MealType::Dessert,
        'total_calories' => 300,
        'total_protein' => 17,
        'total_carbs' => 22,
        'total_fat' => 14,
    ]);
    $dessert->ingredients()->attach($carbIngredient->id, ['amount_grams' => 90]);
    $dessert->ingredients()->attach($proteinIngredient->id, ['amount_grams' => 40]);

    $plan = MealPlan::query()->create([
        'name' => 'Heavy Dessert Surplus Plan',
        'goal' => 'Tier surplus trim review.',
        'schema_type' => MealPlanSchemaType::WeeklyStructured,
        'plan_category' => MealPlanLibraryCategory::NutrientDense,
        'target_total_calories' => 10500,
    ]);

    $plan->dayMeals()->createMany([
        [
            'meal_id' => $breakfast->id,
            'day_number' => 1,
            'slot_type' => MealPlanSlotType::Breakfast,
            'slot_index' => 1,
            'is_option_b' => false,
        ],
        [
            'meal_id' => $mainA->id,
            'day_number' => 1,
            'slot_type' => MealPlanSlotType::Main,
            'slot_index' => 1,
            'is_option_b' => false,
        ],
        [
            'meal_id' => $mainB->id,
            'day_number' => 1,
            'slot_type' => MealPlanSlotType::Main,
            'slot_index' => 2,
            'is_option_b' => false,
        ],
        [
            'meal_id' => $salad->id,
            'day_number' => 1,
            'slot_type' => MealPlanSlotType::Salad,
            'slot_index' => 1,
            'is_option_b' => false,
        ],
        [
            'meal_id' => $dessert->id,
            'day_number' => 1,
            'slot_type' => MealPlanSlotType::Dessert,
            'slot_index' => 1,
            'is_option_b' => false,
        ],
    ]);

    $selections = [
        1 => [
            'breakfasts' => [$breakfast->id],
            'meals' => [$mainA->id, $mainB->id],
            'sideSalads' => [$salad->id],
            'desserts' => [$dessert->id],
        ],
    ];

    $payload = $this->actingAs($user)
        ->getJson(route('admin.meal-plan-library.tier-preview', [
            'mealPlan' => $plan,
            'plan_tier' => 1500,
            'selections' => json_encode($selections),
        ]))
        ->assertOk()
        ->json();

    $day = $payload['days'][0];
    $dayCalories = sumSelectedDayCalories($day, $selections[1]);
    $warnings = is_array($day['reconciliationWarnings'] ?? null) ? $day['reconciliationWarnings'] : [];
    $withinTolerance = abs($dayCalories - 1500) <= UserPlanCalculator::dayCalorieTolerance();
    $hasSurplusWarning = collect($warnings)->contains(
        fn (string $warning): bool => str_contains($warning, 'kcal above target'),
    );

    expect($dayCalories)->toBeGreaterThan(0)
        ->and($withinTolerance || $hasSurplusWarning)->toBeTrue();
});

test('meal plan tier preview micronutrients follow selected mains', function (): void {
    $user = User::factory()->create();

    $highIron = Ingredient::factory()->create([
        'name' => 'High Iron Protein',
        'calories' => 120,
        'protein' => 22,
        'carbs' => 0,
        'fat' => 3,
        'iron' => 15,
    ]);

    $lowIron = Ingredient::factory()->create([
        'name' => 'Low Iron Protein',
        'calories' => 120,
        'protein' => 22,
        'carbs' => 0,
        'fat' => 3,
        'iron' => 1,
    ]);

    $breakfast = Meal::factory()->create([
        'name' => SavoryEggBreakfastMeals::mealNames()[0],
        'category' => RecipeCategory::Breakfast,
        'meal_type' => MealType::Breakfast,
        'total_calories' => 305,
    ]);
    $breakfast->ingredients()->attach($lowIron->id, ['amount_grams' => 80]);

    $mainA = Meal::factory()->create([
        'name' => 'Iron Main A',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
        'total_calories' => 420,
    ]);
    $mainA->ingredients()->attach($highIron->id, ['amount_grams' => 180]);

    $mainB = Meal::factory()->create([
        'name' => 'Iron Main B',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
        'total_calories' => 420,
    ]);
    $mainB->ingredients()->attach($lowIron->id, ['amount_grams' => 180]);

    $mainC = Meal::factory()->create([
        'name' => 'Iron Main C',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
        'total_calories' => 420,
    ]);
    $mainC->ingredients()->attach($highIron->id, ['amount_grams' => 180]);

    $plan = MealPlan::query()->create([
        'name' => 'Micronutrient Preview Plan',
        'goal' => 'Selection-sensitive micro totals.',
        'schema_type' => MealPlanSchemaType::WeeklyStructured,
        'plan_category' => MealPlanLibraryCategory::NutrientDense,
        'target_total_calories' => 10500,
    ]);

    $plan->dayMeals()->createMany([
        [
            'meal_id' => $breakfast->id,
            'day_number' => 1,
            'slot_type' => MealPlanSlotType::Breakfast,
            'slot_index' => 1,
            'is_option_b' => false,
        ],
        [
            'meal_id' => $mainA->id,
            'day_number' => 1,
            'slot_type' => MealPlanSlotType::Main,
            'slot_index' => 1,
            'is_option_b' => false,
        ],
        [
            'meal_id' => $mainB->id,
            'day_number' => 1,
            'slot_type' => MealPlanSlotType::Main,
            'slot_index' => 2,
            'is_option_b' => false,
        ],
        [
            'meal_id' => $mainC->id,
            'day_number' => 1,
            'slot_type' => MealPlanSlotType::Main,
            'slot_index' => 3,
            'is_option_b' => false,
        ],
    ]);

    $lowIronSelection = [
        1 => [
            'breakfasts' => [$breakfast->id],
            'meals' => [$mainA->id, $mainB->id],
        ],
    ];

    $highIronSelection = [
        1 => [
            'breakfasts' => [$breakfast->id],
            'meals' => [$mainA->id, $mainC->id],
        ],
    ];

    $lowIronDay = $this->actingAs($user)
        ->getJson(route('admin.meal-plan-library.tier-preview', [
            'mealPlan' => $plan,
            'plan_tier' => 1500,
            'selections' => json_encode($lowIronSelection),
        ]))
        ->assertOk()
        ->json('days.0');

    $highIronDay = $this->actingAs($user)
        ->getJson(route('admin.meal-plan-library.tier-preview', [
            'mealPlan' => $plan,
            'plan_tier' => 1500,
            'selections' => json_encode($highIronSelection),
        ]))
        ->assertOk()
        ->json('days.0');

    $lowIronTotal = sumSelectedDayIron($lowIronDay, $lowIronSelection[1]);
    $highIronTotal = sumSelectedDayIron($highIronDay, $highIronSelection[1]);

    expect($highIronTotal)->toBeGreaterThan($lowIronTotal);
});
