<?php

use App\Enums\DietProtocol;
use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\CustomerProfile;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\BalancedCanonicalMealRecipeRefiner;
use App\Services\BalancedMealLibraryConfigurator;
use App\Services\Nutrition\AdaptedMenuBuilder;

/**
 * @param  array{category?: RecipeCategory, meal_type?: MealType, calories?: float}  $overrides
 */
function balancedDeckMeal(string $name, array $overrides = []): Meal
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

function seedBalancedDeckMealsForTest(): void
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

    balancedDeckMeal('Blueberry Walnut Chia Pudding', [
        'category' => RecipeCategory::Breakfast,
        'meal_type' => MealType::Breakfast,
        'total_calories' => 280,
    ]);
    balancedDeckMeal('Mediterranean Omelet', [
        'category' => RecipeCategory::Breakfast,
        'meal_type' => MealType::Breakfast,
        'total_calories' => 276,
    ]);
    balancedDeckMeal('Tamarind Honey & Sesame Chicken w Garlicky Green Beans');
    balancedDeckMeal('Grilled Rosemary Garlic Chicken Salad w Rocca & Red Pepper Dressing');
    balancedDeckMeal(BalancedCanonicalMealRecipeRefiner::BAKED_SALMON_NAME);
    balancedDeckMeal('Vegan Butternut Squash, Lentil & Nut Stew w Brown Rice');
    balancedDeckMeal('Marinated Pineapple, Peppers, Red Onion & Cilantro Side Salad', [
        'category' => RecipeCategory::SideSalad,
        'meal_type' => MealType::Salad,
        'total_calories' => 175,
    ]);
    balancedDeckMeal('Classic Garden Salad', [
        'category' => RecipeCategory::SideSalad,
        'meal_type' => MealType::Salad,
        'total_calories' => 175,
    ]);
    balancedDeckMeal('Carrot Walnut Spice Cake', [
        'category' => RecipeCategory::Dessert,
        'meal_type' => MealType::Dessert,
        'total_calories' => 170,
    ]);
    balancedDeckMeal('Fruit Salad Bowl', [
        'category' => RecipeCategory::Dessert,
        'meal_type' => MealType::Dessert,
        'total_calories' => 170,
    ]);
    balancedDeckMeal('Vegan Mushroom Soup', [
        'category' => RecipeCategory::Soup,
        'meal_type' => MealType::Soup,
        'total_calories' => 150,
    ]);

    balancedDeckMeal('Legacy Extra Meal', ['total_calories' => 400]);
}

test('balanced configurator orders canonical deck meals first', function (): void {
    seedBalancedDeckMealsForTest();

    app(BalancedMealLibraryConfigurator::class)->configure();

    foreach (BalancedMealLibraryConfigurator::canonicalSlots() as $slot) {
        $meal = Meal::queryForMealLibrary()->where('name', $slot['name'])->first();

        expect($meal)->not->toBeNull()
            ->and($meal->library_sort_order)->toBe($slot['sort'])
            ->and($meal->meal_plan_tags)->toContain('Balanced');
    }

    $legacy = Meal::queryForMealLibrary()->where('name', 'Legacy Extra Meal')->firstOrFail();

    expect($legacy->library_sort_order)->toBeGreaterThanOrEqual(BalancedMealLibraryConfigurator::NON_CANONICAL_SORT_BASE);
});

test('balanced configurator creates bone broth cup soup meal', function (): void {
    seedBalancedDeckMealsForTest();
    Meal::queryForMealLibrary()->where('name', BalancedMealLibraryConfigurator::BONE_BROTH_MEAL_NAME)->forceDelete();

    app(BalancedMealLibraryConfigurator::class)->configure();

    $meal = Meal::queryForMealLibrary()->where('name', BalancedMealLibraryConfigurator::BONE_BROTH_MEAL_NAME)->first();

    expect($meal)->not->toBeNull()
        ->and($meal->meal_type)->toBe(MealType::Soup)
        ->and((float) $meal->total_calories)->toBeGreaterThan(100.0)
        ->and((float) $meal->total_calories)->toBeLessThan(200.0);
});

test('adapted menu lists canonical breakfasts and mains before demoted library meals', function (): void {
    seedBalancedDeckMealsForTest();
    app(BalancedMealLibraryConfigurator::class)->configure();

    $profile = CustomerProfile::factory()->create([
        'diet_protocol' => DietProtocol::Balanced->value,
        'daily_calorie_target' => 1500,
        'protein_percentage' => 40,
        'carb_percentage' => 40,
        'fat_percentage' => 20,
    ]);

    $menu = AdaptedMenuBuilder::build($profile, ['snap_to_tier' => true]);

    $breakfastNames = collect($menu['scalable_meals'] ?? [])
        ->filter(fn (array $m): bool => ($m['slot'] ?? '') === 'breakfast')
        ->pluck('name')
        ->take(2)
        ->values()
        ->all();

    expect($breakfastNames)->toBe([
        'Blueberry Walnut Chia Pudding',
        'Mediterranean Omelet',
    ]);

    $mainNames = collect($menu['scalable_meals'] ?? [])
        ->filter(fn (array $m): bool => ($m['slot'] ?? '') === 'main')
        ->pluck('name')
        ->take(4)
        ->values()
        ->all();

    expect($mainNames)->toBe([
        'Tamarind Honey & Sesame Chicken w Garlicky Green Beans',
        'Grilled Rosemary Garlic Chicken Salad w Rocca & Red Pepper Dressing',
        BalancedCanonicalMealRecipeRefiner::BAKED_SALMON_NAME,
        'Vegan Butternut Squash, Lentil & Nut Stew w Brown Rice',
    ]);
});
