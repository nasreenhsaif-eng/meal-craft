<?php

use App\Enums\MealPlanSchemaType;
use App\Enums\MealPlanSlotType;
use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\CustomerProfile;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\MealPlanDayMeal;
use App\Models\User;
use App\Services\Nutrition\AdaptedMenuBuilder;
use App\Services\Nutrition\CraftCaloriePlanner;
use App\Services\Nutrition\ProductionWeeklyMenuSchedule;
use App\Services\Nutrition\UserPlanCalculator;
use App\Services\RecipeNutritionCalculator;
use App\Support\AdminConsultationPreviewProfile;
use App\Support\KitchenPortionRounding;

test('meal detail view api returns short description for the details modal', function () {
    $user = User::factory()->customer()->create();

    $ingredient = Ingredient::factory()->create(['name' => 'Lean Beef']);
    $meal = Meal::factory()->create([
        'name' => 'API Detail Bibimbap Style Bowl',
        'short_description' => 'Korean-style bowl with seasoned lean ground beef over quinoa.',
        'highlight' => 'Korean-style bowl with seasoned lean ground beef over quinoa.',
    ]);
    $meal->ingredients()->attach($ingredient->id, ['amount_grams' => 150, 'amount' => 150, 'unit' => 'g']);

    $this->actingAs($user)
        ->getJson(route('api.meals.detail-view', $meal))
        ->assertOk()
        ->assertJsonPath(
            'detailView.shortDescription',
            'Korean-style bowl with seasoned lean ground beef over quinoa.',
        );
});

test('meal detail view api formats salmon with raw before cooking label', function () {
    $user = User::factory()->customer()->create();

    $salmon = Ingredient::factory()->create(['name' => 'Salmon']);
    $meal = Meal::factory()->create(['name' => 'API Detail Baked Salmon']);
    $meal->ingredients()->attach($salmon->id, ['amount_grams' => 125, 'amount' => 125, 'unit' => 'g']);

    $this->actingAs($user)
        ->getJson(route('api.meals.detail-view', $meal))
        ->assertOk()
        ->assertJsonPath('detailView.ingredients.0', '125g Salmon (raw, before cooking)');
});

test('meal detail view api formats egg ingredients with large egg counts', function () {
    $user = User::factory()->customer()->create();

    $egg = Ingredient::factory()->create(['name' => 'Egg']);
    $meal = Meal::factory()->create(['name' => 'API Detail Hummus Egg Stack']);
    $meal->ingredients()->attach($egg->id, ['amount_grams' => 100, 'amount' => 100, 'unit' => 'g']);

    $this->actingAs($user)
        ->getJson(route('api.meals.detail-view', $meal))
        ->assertOk()
        ->assertJsonPath('detailView.ingredients.0', '2 large eggs (100g)');
});

test('meal detail view api returns persisted instructions and ingredients', function () {
    $user = User::factory()->customer()->create();

    $barberries = Ingredient::factory()->create(['name' => 'Barberries']);
    $meal = Meal::factory()->create([
        'name' => 'API Detail Sweet Potato Hash',
        'instructions' => '1. Roast sweet potato with rosemary, thyme, sea salt, and black pepper.',
        'description' => '1. Roast sweet potato with rosemary, thyme, sea salt, and black pepper.',
    ]);
    $meal->ingredients()->attach($barberries->id, ['amount_grams' => 5, 'amount' => 5, 'unit' => 'g']);

    $this->actingAs($user)
        ->getJson(route('api.meals.detail-view', $meal))
        ->assertOk()
        ->assertJsonPath('detailView.instructions.0', fn (string $step): bool => str_contains($step, 'rosemary'))
        ->assertJsonPath('editForm.ingredientRows.0.selectedName', 'Barberries');
});

test('meal detail view api scales ingredient amounts for customer plan and craft', function () {
    $user = User::factory()->customer()->create();
    CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 1200,
        'protein_percentage' => 30,
        'carb_percentage' => 40,
        'fat_percentage' => 30,
    ]);

    $ingredient = Ingredient::factory()->create([
        'name' => 'Scaled Rice',
        'calories' => 130,
        'protein' => 2.5,
        'carbs' => 28,
        'fat' => 0.3,
        'usda_food_category' => 'Grains',
    ]);
    $meal = Meal::factory()->create([
        'name' => 'API Detail Scaled Breakfast',
        'meal_type' => MealType::Breakfast,
        'category' => RecipeCategory::Breakfast,
        'total_calories' => 250,
        'total_protein' => 20,
        'total_carbs' => 25,
        'total_fat' => 8,
    ]);
    $meal->ingredients()->attach($ingredient->id, ['amount_grams' => 100, 'amount' => 100, 'unit' => 'g']);

    // Keep stored totals aligned with live ingredient nutrition so scaling matches adaptation.
    $live = RecipeNutritionCalculator::fromMeal($meal->fresh(['ingredients']));
    $meal->update([
        'total_calories' => $live['calories'],
        'total_protein' => $live['protein'],
        'total_carbs' => $live['carbs'],
        'total_fat' => $live['fat'],
    ]);
    $meal->refresh();

    $profile = CustomerProfile::query()->where('user_id', $user->id)->first();
    $plan = UserPlanCalculator::calculateUserPlan($profile, ['craft_key' => CraftCaloriePlanner::CRAFT_FULL]);
    $multiplier = AdaptedMenuBuilder::mealScalingMultiplier($meal, 'breakfast', $plan);
    $expectedGrams = KitchenPortionRounding::snapGramsForIngredient(
        $ingredient,
        round(100 * $multiplier, 4),
    );
    $expectedGrams = round($expectedGrams, 2);

    $this->actingAs($user)
        ->getJson(route('api.meals.detail-view', $meal).'?'.http_build_query([
            'craft_key' => CraftCaloriePlanner::CRAFT_FULL,
        ]))
        ->assertOk()
        ->assertJsonPath('detailView.nutritionSubheading', 'Adapted for your plan')
        ->assertJsonPath('detailView.ingredients.0', "{$expectedGrams}g Scaled Rice");
});

test('meal detail view api formats liquid ingredients in milliliters', function () {
    // Non-admin without a calorie target so consultation adaptation does not snap portions.
    $user = User::factory()->customer()->create();

    $oil = Ingredient::factory()->create([
        'name' => 'Olive Oil (Extra Virgin)',
        'usda_food_category' => 'Fats',
        'density' => 1.0,
    ]);
    $meal = Meal::factory()->create(['name' => 'API Detail Omelet With Oil']);
    $meal->ingredients()->attach($oil->id, ['amount_grams' => 6, 'amount' => 6, 'unit' => 'g']);

    $this->actingAs($user)
        ->getJson(route('api.meals.detail-view', $meal))
        ->assertOk()
        ->assertJsonPath('detailView.ingredients.0', '5ml Olive Oil (Extra Virgin)');
});

test('meal detail view api matches scheduled savory breakfast calories for full craft day', function () {
    $meal = Meal::query()->where('name', 'Mediterranean Omelet')->first();

    if ($meal === null) {
        $this->markTestSkipped('Mediterranean Omelet is not in the meal library.');
    }

    $user = User::factory()->create();

    $scheduled = ProductionWeeklyMenuSchedule::scheduledFullCraftByWeekday(
        AdminConsultationPreviewProfile::resolve($user),
        null,
        ['plan_tier' => 1000, 'craft_key' => CraftCaloriePlanner::CRAFT_FULL],
    );

    $expectedCalories = null;

    foreach ($scheduled[1]['breakfasts'] ?? [] as $breakfast) {
        if (($breakfast['name'] ?? '') === 'Mediterranean Omelet') {
            $expectedCalories = (int) round((float) ($breakfast['adapted_nutrition']['calories'] ?? 0));

            break;
        }
    }

    if ($expectedCalories === null) {
        $this->markTestSkipped('Sunday production schedule does not include Mediterranean Omelet.');
    }

    $this->actingAs($user)
        ->getJson(route('api.meals.detail-view', $meal).'?'.http_build_query([
            'craft_key' => CraftCaloriePlanner::CRAFT_FULL,
            'plan_tier' => 1000,
            'day_of_week' => 1,
        ]))
        ->assertOk()
        ->assertJsonPath('detailView.nutritionSubheading', 'Adapted for your plan')
        ->assertJsonPath('detailView.macros.calories', $expectedCalories);
});

test('meal detail view api matches scheduled main meal calories micros and ingredient grams for full craft day', function () {
    $user = User::factory()->create();
    $profile = AdminConsultationPreviewProfile::resolve($user);

    $buildOptions = [
        'plan_tier' => 2000,
        'craft_key' => CraftCaloriePlanner::CRAFT_FULL,
        'day_of_week' => 2,
    ];

    $scheduled = ProductionWeeklyMenuSchedule::scheduledFullCraftByWeekday(
        $profile,
        null,
        $buildOptions,
    );

    $expected = $scheduled[2]['meals'][0] ?? null;

    if ($expected === null) {
        $this->markTestSkipped('Tuesday production schedule has no main meals.');
    }

    $meal = Meal::query()->find((int) ($expected['id'] ?? 0));

    if ($meal === null) {
        $this->markTestSkipped('Scheduled main meal is not in the meal library.');
    }

    $buildOptions['selected_main_meal_ids'] = [$meal->id];

    $scheduledWithSelection = ProductionWeeklyMenuSchedule::scheduledFullCraftByWeekday(
        $profile,
        null,
        $buildOptions,
    );

    $expected = ProductionWeeklyMenuSchedule::findAdaptedMealInDayMenu($scheduledWithSelection[2], (int) $meal->id);

    if ($expected === null) {
        $this->markTestSkipped('Selected main meal was not found in reconciled Tuesday schedule.');
    }

    $expectedCalories = (int) round((float) ($expected['adapted_nutrition']['calories'] ?? 0));
    $expectedVitaminD = round((float) ($expected['adapted_nutrition']['vitamin_d'] ?? 0), 1);
    $firstIngredient = $expected['ingredients'][0] ?? null;

    if (! is_array($firstIngredient)) {
        $this->markTestSkipped('Scheduled main meal payload has no ingredient rows.');
    }

    $expectedIngredientName = strtolower((string) ($firstIngredient['name'] ?? ''));
    $expectedIngredientGrams = round((float) ($firstIngredient['adapted_amount_grams'] ?? 0), 2);

    $response = $this->actingAs($user)
        ->getJson(route('api.meals.detail-view', $meal).'?'.http_build_query([
            'craft_key' => CraftCaloriePlanner::CRAFT_FULL,
            'plan_tier' => 2000,
            'day_of_week' => 2,
            'selected_main_meal_ids' => [$meal->id],
        ]))
        ->assertOk()
        ->assertJsonPath('detailView.nutritionSubheading', 'Adapted for your plan')
        ->assertJsonPath('detailView.macros.calories', $expectedCalories);

    $detailNutrition = $response->json('detailView.nutrition');
    expect(round((float) ($detailNutrition['vitamin_d'] ?? 0), 1))->toBe($expectedVitaminD);

    $ingredientLines = $response->json('detailView.ingredients');
    expect($ingredientLines)->toBeArray();

    $matchingLine = collect($ingredientLines)->first(
        static fn (mixed $line): bool => is_string($line) && str_contains(strtolower($line), $expectedIngredientName),
    );

    expect($matchingLine)->toBeString()
        ->and($matchingLine)->toContain((string) $expectedIngredientGrams);
});

test('meal detail view api uses reconciled weekday schedule when craft and day are provided', function () {
    $user = User::factory()->create();
    CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 2000,
        'protein_percentage' => 35,
        'carb_percentage' => 35,
        'fat_percentage' => 30,
    ]);

    $chicken = Ingredient::factory()->create(['name' => 'API Detail View Chicken']);
    $main = Meal::factory()->create([
        'name' => 'API Detail View Main Plate',
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 420,
        'total_protein' => 35,
        'total_carbs' => 30,
        'total_fat' => 15,
    ]);
    $main->ingredients()->attach($chicken->id, [
        'amount_grams' => 150,
        'amount' => 150,
        'unit' => 'g',
    ]);

    $breakfast = Meal::factory()->create([
        'name' => 'API Detail View Breakfast',
        'meal_type' => MealType::Breakfast,
        'category' => RecipeCategory::Breakfast,
        'total_calories' => 300,
    ]);
    $salad = Meal::factory()->create([
        'name' => 'API Detail View Side Salad',
        'meal_type' => MealType::Salad,
        'category' => RecipeCategory::SideSalad,
        'total_calories' => 140,
    ]);
    $dessert = Meal::factory()->create([
        'name' => 'Chia API Detail View Dessert',
        'meal_type' => MealType::Dessert,
        'category' => RecipeCategory::Dessert,
        'total_calories' => 150,
    ]);

    $plan = MealPlan::query()->create([
        'name' => 'API detail view weekly plan',
        'goal' => 'API detail view tests',
        'schema_type' => MealPlanSchemaType::WeeklyStructured,
        'plan_category' => 'balanced',
    ]);

    foreach (
        [
            [MealPlanSlotType::Breakfast, 1, $breakfast],
            [MealPlanSlotType::Main, 1, $main],
            [MealPlanSlotType::Main, 2, $main],
            [MealPlanSlotType::Salad, 1, $salad],
            [MealPlanSlotType::Dessert, 1, $dessert],
        ] as [$slotType, $slotIndex, $meal]
    ) {
        MealPlanDayMeal::query()->create([
            'meal_plan_id' => $plan->id,
            'meal_id' => $meal->id,
            'day_number' => 2,
            'slot_type' => $slotType->value,
            'slot_index' => $slotIndex,
            'is_option_b' => false,
        ]);
    }

    config(['customer_nutrition.production_meal_plan_id' => $plan->id]);

    $profile = AdminConsultationPreviewProfile::resolve($user);
    $buildOptions = [
        'craft_key' => CraftCaloriePlanner::CRAFT_FULL,
        'day_of_week' => 2,
        'plan_tier' => 2000,
        'selected_main_meal_ids' => [$main->id],
    ];

    $scheduled = ProductionWeeklyMenuSchedule::adaptedMealFromScheduledDay(
        $profile,
        (int) $main->id,
        $buildOptions,
    );

    expect($scheduled)->not->toBeNull();

    $expectedCalories = (int) round((float) ($scheduled['adapted_nutrition']['calories'] ?? 0));
    $expectedVitaminD = round((float) ($scheduled['adapted_nutrition']['vitamin_d'] ?? 0), 1);
    $expectedChickenGrams = round((float) ($scheduled['ingredients'][0]['adapted_amount_grams'] ?? 0), 2);

    $response = $this->actingAs($user)
        ->getJson(route('api.meals.detail-view', $main).'?'.http_build_query([
            'craft_key' => CraftCaloriePlanner::CRAFT_FULL,
            'plan_tier' => 2000,
            'day_of_week' => 2,
            'selected_main_meal_ids' => [$main->id],
        ]))
        ->assertOk()
        ->assertJsonPath('detailView.macros.calories', $expectedCalories);

    $detailNutrition = $response->json('detailView.nutrition');
    expect(round((float) ($detailNutrition['vitamin_d'] ?? 0), 1))->toBe($expectedVitaminD);

    $chickenLine = collect($response->json('detailView.ingredients'))->first(
        static fn (mixed $line): bool => is_string($line) && str_contains(strtolower($line), 'chicken'),
    );

    expect($chickenLine)->toBeString()
        ->and($chickenLine)->toContain((string) $expectedChickenGrams);
});
