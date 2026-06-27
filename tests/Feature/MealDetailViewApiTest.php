<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\CustomerProfile;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use App\Services\Nutrition\AdaptedMenuBuilder;
use App\Services\Nutrition\CraftCaloriePlanner;
use App\Services\Nutrition\ProductionWeeklyMenuSchedule;
use App\Services\Nutrition\UserPlanCalculator;
use App\Support\AdminConsultationPreviewProfile;

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

    $ingredient = Ingredient::factory()->create(['name' => 'Scaled Rice']);
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

    $profile = CustomerProfile::query()->where('user_id', $user->id)->first();
    $plan = UserPlanCalculator::calculateUserPlan($profile, ['craft_key' => CraftCaloriePlanner::CRAFT_FULL]);
    $multiplier = AdaptedMenuBuilder::mealScalingMultiplier($meal, 'breakfast', $plan);
    $expectedGrams = round(100 * $multiplier, 2);

    $this->actingAs($user)
        ->getJson(route('api.meals.detail-view', $meal).'?'.http_build_query([
            'craft_key' => CraftCaloriePlanner::CRAFT_FULL,
        ]))
        ->assertOk()
        ->assertJsonPath('detailView.nutritionSubheading', 'Adapted for your plan')
        ->assertJsonPath('detailView.ingredients.0', "{$expectedGrams}g Scaled Rice");
});

test('meal detail view api formats liquid ingredients in milliliters', function () {
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
        ->assertJsonPath('detailView.ingredients.0', '6ml Olive Oil (Extra Virgin)');
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
