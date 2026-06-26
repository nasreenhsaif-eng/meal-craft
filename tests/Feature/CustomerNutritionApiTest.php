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
use App\Services\Nutrition\UserPlanCalculator;

test('guests cannot post onboarding', function () {
    $this->postJson('/api/onboarding', [])->assertUnauthorized();
});

test('guests cannot fetch adapted menu', function () {
    $this->getJson('/api/menu/adapted')->assertUnauthorized();
});

test('authenticated user can complete onboarding and receive a plan', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/onboarding', [
        'weight_kg' => 80,
        'height_cm' => 180,
        'age' => 30,
        'sex' => 'male',
        'activity_level' => 'moderate',
        'macro_split_style' => 'high_protein',
    ]);

    $response->assertCreated()
        ->assertJsonPath('profile.macro_split_style', 'high_protein')
        ->assertJsonPath('plan.fixed.calories', 345)
        ->assertJsonStructure([
            'plan' => [
                'scaling_multiplier',
                'remaining' => ['calories', 'macros'],
            ],
        ]);

    $this->assertDatabaseHas('customer_profiles', [
        'user_id' => $user->id,
        'protein_percentage' => 45.0,
        'carb_percentage' => 25.0,
        'fat_percentage' => 30.0,
    ]);
});

test('adapted menu requires a calorie target on the customer profile', function () {
    $user = User::factory()->customer()->create();

    $this->actingAs($user)
        ->getJson('/api/menu/adapted')
        ->assertUnprocessable();
});

test('adapted menu auto provisions a preview profile for admin staff without one', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->getJson('/api/menu/adapted')
        ->assertSuccessful()
        ->assertJsonPath('daily_calorie_target', 2000);

    expect($admin->fresh()->customerProfile?->daily_calorie_target)->toBe(2000);
});

test('admin staff can preview adapted menu at a chosen plan tier', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->getJson('/api/menu/adapted?plan_tier=1500')
        ->assertSuccessful()
        ->assertJsonPath('plan.plan_tier', 1500)
        ->assertJsonPath('daily_calorie_target', 1500);

    expect($admin->fresh()->customerProfile?->daily_calorie_target)->toBe(1500);
});

test('customers cannot override plan tier via adapted menu query', function () {
    $user = User::factory()->customer()->create();
    CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 2000,
    ]);

    $this->actingAs($user)
        ->getJson('/api/menu/adapted?plan_tier=1000')
        ->assertSuccessful()
        ->assertJsonPath('plan.plan_tier', 2000);

    expect($user->fresh()->customerProfile?->daily_calorie_target)->toBe(2000);
});

test('actual fixed portion calories shrink scalable slot targets while day total stays at tier', function () {
    $user = User::factory()->create();
    CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 1200,
        'protein_percentage' => 40,
        'carb_percentage' => 30,
        'fat_percentage' => 30,
    ]);

    $withMidpoints = UserPlanCalculator::calculateUserPlan($profile = $user->customerProfile);
    $withActualFixed = UserPlanCalculator::calculateUserPlan($profile, [
        'side_salad_calories' => 220,
        'dessert_calories' => 210,
        'include_soup' => true,
        'soup_calories' => 107,
    ]);

    expect($withActualFixed['day_total_calories'])->toBe(1200.0)
        ->and($withActualFixed['scalable_slot_targets']['breakfast']['calories'])
        ->toBeLessThan($withMidpoints['scalable_slot_targets']['breakfast']['calories']);
});

test('adapted menu is available during onboarding when daily calorie target is set', function () {
    $user = User::factory()->create();
    CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 1500,
        'onboarding_completed_at' => null,
    ]);

    $this->actingAs($user)
        ->getJson('/api/menu/adapted')
        ->assertSuccessful()
        ->assertJsonPath('plan.plan_tier', 1500);
});

test('adapted menu scales breakfast and main meals by per-meal multiplier toward slot target', function () {
    $user = User::factory()->create();
    CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 2000,
        'protein_percentage' => 30,
        'carb_percentage' => 40,
        'fat_percentage' => 30,
    ]);

    $ingredient = Ingredient::factory()->create([
        'calories' => 100,
        'protein' => 10,
        'carbs' => 10,
        'fat' => 5,
    ]);

    $breakfast = Meal::factory()->create([
        'name' => 'Test Breakfast',
        'meal_type' => MealType::Breakfast,
        'category' => RecipeCategory::Breakfast,
        'total_calories' => 250,
        'total_protein' => 20,
        'total_carbs' => 25,
        'total_fat' => 8,
        'library_sort_order' => 1,
    ]);
    $breakfast->ingredients()->attach($ingredient->id, ['amount_grams' => 100]);

    $main = Meal::factory()->create([
        'name' => 'Test Main',
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 375,
        'total_protein' => 30,
        'total_carbs' => 35,
        'total_fat' => 12,
        'library_sort_order' => 2,
    ]);
    $main->ingredients()->attach($ingredient->id, ['amount_grams' => 150]);

    $profile = CustomerProfile::query()->where('user_id', $user->id)->first();
    $plan = UserPlanCalculator::calculateUserPlan($profile);
    $expectedBreakfastMultiplier = AdaptedMenuBuilder::mealScalingMultiplier($breakfast, 'breakfast', $plan);

    $response = $this->actingAs($user)->getJson('/api/menu/adapted');

    $response->assertSuccessful()
        ->assertJsonPath('plan.scaling_multiplier', $plan['scaling_multiplier']);

    $scalable = collect($response->json('scalable_meals'));
    $scaledBreakfast = $scalable->firstWhere('name', 'Test Breakfast');

    expect($scaledBreakfast)->not->toBeNull()
        ->and($scaledBreakfast['is_scaled'])->toBeTrue()
        ->and($scaledBreakfast['scaling_multiplier'])->toEqual($expectedBreakfastMultiplier)
        ->and((float) $scaledBreakfast['ingredients'][0]['adapted_amount_grams'])
        ->toEqual(round(100 * $expectedBreakfastMultiplier, 2));
});

test('adapted menu returns fixed portion meals with unscaled recipe nutrition', function () {
    $user = User::factory()->create();
    CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 1500,
        'protein_percentage' => 30,
        'carb_percentage' => 40,
        'fat_percentage' => 30,
    ]);

    $salad = Meal::factory()->create([
        'name' => 'Test Side Salad',
        'meal_type' => MealType::Salad,
        'category' => RecipeCategory::SideSalad,
        'total_calories' => 180,
        'total_protein' => 6,
        'total_carbs' => 12,
        'total_fat' => 10,
        'library_sort_order' => 3,
    ]);

    $response = $this->actingAs($user)->getJson('/api/menu/adapted');

    $response->assertSuccessful();

    $fixed = collect($response->json('fixed_portion_meals'));
    $sideSalad = $fixed->firstWhere('name', 'Test Side Salad');

    expect($sideSalad)->not->toBeNull()
        ->and($sideSalad['portion_behavior'])->toBe('fixed_portion')
        ->and($sideSalad['is_scaled'])->toBeFalse()
        ->and($sideSalad['scaling_multiplier'])->toEqual(1.0)
        ->and($sideSalad['counts_toward_core_tier'])->toBeTrue()
        ->and((float) $sideSalad['baseline_nutrition']['calories'])->toBe(180.0)
        ->and((float) $sideSalad['adapted_nutrition']['calories'])->toBe(180.0);
});

test('adapted menu lists soups as optional add-ons and include_soup keeps day total at tier', function () {
    $user = User::factory()->create();
    CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 1500,
        'protein_percentage' => 30,
        'carb_percentage' => 40,
        'fat_percentage' => 30,
    ]);

    Meal::factory()->create([
        'name' => 'Test Soup',
        'meal_type' => MealType::Soup,
        'category' => RecipeCategory::Soup,
        'total_calories' => 140,
        'total_protein' => 8,
        'total_carbs' => 16,
        'total_fat' => 4,
        'library_sort_order' => 4,
    ]);

    $withoutSoup = $this->actingAs($user)->getJson('/api/menu/adapted');
    $withSoup = $this->actingAs($user)->getJson('/api/menu/adapted?include_soup=1');

    $withoutSoup->assertSuccessful();
    $withSoup->assertSuccessful()
        ->assertJsonPath('include_soup', true)
        ->assertJsonPath('plan.include_soup', true)
        ->assertJsonPath('plan.day_total_calories', 1500)
        ->assertJsonPath('plan.scalable_slot_targets.breakfast.calories', 201);

    $soups = collect($withSoup->json('optional_add_on_meals'));
    $soup = $soups->firstWhere('name', 'Test Soup');

    expect($soup)->not->toBeNull()
        ->and($soup['portion_behavior'])->toBe('optional_add_on')
        ->and($soup['counts_toward_core_tier'])->toBeTrue()
        ->and((float) $soup['adapted_nutrition']['calories'])->toBe(140.0)
        ->and((float) $withoutSoup->json('plan.day_total_calories'))->toEqual(1500.0);
});

test('adapted menu exposes admin-scheduled soups per weekday from production meal plan', function () {
    $user = User::factory()->create();
    CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 1500,
    ]);

    $veganSoup = Meal::factory()->create([
        'name' => 'Monday Vegan Soup',
        'meal_type' => MealType::Soup,
        'category' => RecipeCategory::Soup,
        'total_calories' => 150,
    ]);

    $boneBroth = Meal::factory()->create([
        'name' => 'Monday Bone Broth',
        'meal_type' => MealType::Soup,
        'category' => RecipeCategory::Soup,
        'total_calories' => 120,
    ]);

    $tuesdayVeganSoup = Meal::factory()->create([
        'name' => 'Tuesday Vegan Soup',
        'meal_type' => MealType::Soup,
        'category' => RecipeCategory::Soup,
        'total_calories' => 145,
    ]);

    $plan = MealPlan::query()->create([
        'name' => 'Production Weekly',
        'goal' => 'Customer production schedule',
        'schema_type' => MealPlanSchemaType::WeeklyStructured,
        'plan_category' => 'balanced',
    ]);

    foreach ([1 => [$veganSoup, $boneBroth], 2 => [$tuesdayVeganSoup, $boneBroth]] as $dayNumber => $meals) {
        foreach ($meals as $slotIndex => $meal) {
            MealPlanDayMeal::query()->create([
                'meal_plan_id' => $plan->id,
                'meal_id' => $meal->id,
                'day_number' => $dayNumber,
                'slot_type' => MealPlanSlotType::Soup->value,
                'slot_index' => $slotIndex + 1,
                'is_option_b' => false,
            ]);
        }
    }

    config(['customer_nutrition.production_meal_plan_id' => $plan->id]);

    $response = $this->actingAs($user)->getJson('/api/menu/adapted');

    $response->assertSuccessful()
        ->assertJsonPath('production_meal_plan_id', $plan->id)
        ->assertJsonPath('scheduled_soups_by_weekday.1.0.name', 'Monday Vegan Soup')
        ->assertJsonPath('scheduled_soups_by_weekday.1.1.name', 'Monday Bone Broth')
        ->assertJsonPath('scheduled_soups_by_weekday.2.0.name', 'Tuesday Vegan Soup')
        ->assertJsonPath('scheduled_soups_by_weekday.2.1.name', 'Monday Bone Broth');

    expect($response->json('scheduled_soups_by_weekday.3'))->toBeNull();
});

test('adapted menu exposes full craft day options with two breakfasts and four mains per weekday', function () {
    $user = User::factory()->create();
    CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 1500,
    ]);

    $breakfastOne = Meal::factory()->create([
        'name' => 'Chia Day One',
        'meal_type' => MealType::Breakfast,
        'category' => RecipeCategory::Breakfast,
        'total_calories' => 300,
    ]);
    $breakfastTwo = Meal::factory()->create([
        'name' => 'Egg Day One',
        'meal_type' => MealType::Breakfast,
        'category' => RecipeCategory::Breakfast,
        'total_calories' => 320,
    ]);

    $mains = collect(range(1, 4))->map(fn (int $index) => Meal::factory()->create([
        'name' => "Main {$index} Day One",
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 400 + $index,
    ]));

    $saladOne = Meal::factory()->create([
        'name' => 'Rotating Salad',
        'meal_type' => MealType::Salad,
        'category' => RecipeCategory::SideSalad,
        'total_calories' => 120,
    ]);
    $saladTwo = Meal::factory()->create([
        'name' => 'Classic Garden Salad',
        'meal_type' => MealType::Salad,
        'category' => RecipeCategory::SideSalad,
        'total_calories' => 90,
    ]);

    $dessertOne = Meal::factory()->create([
        'name' => 'Rotating Dessert',
        'meal_type' => MealType::Dessert,
        'category' => RecipeCategory::Dessert,
        'total_calories' => 200,
    ]);
    $dessertTwo = Meal::factory()->create([
        'name' => 'Fruit Salad Bowl',
        'meal_type' => MealType::Dessert,
        'category' => RecipeCategory::Dessert,
        'total_calories' => 150,
    ]);

    $plan = MealPlan::query()->create([
        'name' => 'Production Weekly Full Craft',
        'goal' => 'Customer production schedule',
        'schema_type' => MealPlanSchemaType::WeeklyStructured,
        'plan_category' => 'balanced',
    ]);

    $slots = [
        [MealPlanSlotType::Breakfast, 1, $breakfastOne],
        [MealPlanSlotType::Breakfast, 2, $breakfastTwo],
        [MealPlanSlotType::Main, 1, $mains[0]],
        [MealPlanSlotType::Main, 2, $mains[1]],
        [MealPlanSlotType::Main, 3, $mains[2]],
        [MealPlanSlotType::Main, 4, $mains[3]],
        [MealPlanSlotType::Salad, 1, $saladOne],
        [MealPlanSlotType::Salad, 2, $saladTwo],
        [MealPlanSlotType::Dessert, 1, $dessertOne],
        [MealPlanSlotType::Dessert, 2, $dessertTwo],
    ];

    foreach ($slots as [$slotType, $slotIndex, $meal]) {
        MealPlanDayMeal::query()->create([
            'meal_plan_id' => $plan->id,
            'meal_id' => $meal->id,
            'day_number' => 1,
            'slot_type' => $slotType->value,
            'slot_index' => $slotIndex,
            'is_option_b' => false,
        ]);
    }

    config(['customer_nutrition.production_meal_plan_id' => $plan->id]);

    $response = $this->actingAs($user)->getJson('/api/menu/adapted?craft_key=full');

    $response->assertSuccessful()
        ->assertJsonCount(2, 'scheduled_full_craft_by_weekday.1.breakfasts')
        ->assertJsonCount(4, 'scheduled_full_craft_by_weekday.1.meals')
        ->assertJsonCount(2, 'scheduled_full_craft_by_weekday.1.sideSalads')
        ->assertJsonCount(2, 'scheduled_full_craft_by_weekday.1.desserts')
        ->assertJsonPath('scheduled_full_craft_by_weekday.1.breakfasts.0.name', 'Chia Day One')
        ->assertJsonPath('scheduled_full_craft_by_weekday.1.breakfasts.1.name', 'Egg Day One')
        ->assertJsonPath('scheduled_full_craft_by_weekday.1.sideSalads.1.name', 'Classic Garden Salad');
});

test('adapted menu applies craft-specific calorie budgets when craft_key is provided', function () {
    $user = User::factory()->create();
    CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 1500,
    ]);

    $response = $this->actingAs($user)->getJson('/api/menu/adapted?craft_key=business');

    $response->assertSuccessful()
        ->assertJsonPath('plan.craft_key', 'business')
        ->assertJsonPath('plan.craft_day_calories', 500);
});

test('adapted menu day craft subtracts one main meal from the plan tier', function () {
    $user = User::factory()->create();
    CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 2000,
    ]);

    $response = $this->actingAs($user)->getJson('/api/menu/adapted?craft_key=day');

    $response->assertSuccessful()
        ->assertJsonPath('plan.craft_key', 'day')
        ->assertJsonPath('plan.craft_day_calories', 1338);
});
