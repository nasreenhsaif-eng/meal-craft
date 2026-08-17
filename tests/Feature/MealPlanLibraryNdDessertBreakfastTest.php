<?php

use App\Enums\MealPlanLibraryCategory;
use App\Enums\MealPlanSchemaType;
use App\Enums\MealPlanSlotType;
use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\User;
use App\Support\NutrientDenseBreakfastOptions;
use Inertia\Testing\AssertableInertia as Assert;

test('tbd weekly protocol admin detail moves chia from desserts to breakfasts', function (): void {
    $user = User::factory()->create();

    $omelette = Meal::factory()->create([
        'name' => NutrientDenseBreakfastOptions::OMELETTE_NAME,
        'category' => RecipeCategory::Breakfast,
        'meal_type' => MealType::Breakfast,
    ]);

    $baked = Meal::factory()->create([
        'name' => 'Chocolate Orange Brownie',
        'category' => RecipeCategory::Dessert,
        'meal_type' => MealType::Dessert,
    ]);

    $fruit = Meal::factory()->create([
        'name' => 'Fruit Salad Bowl',
        'category' => RecipeCategory::Dessert,
        'meal_type' => MealType::Dessert,
    ]);

    $chia = Meal::factory()->create([
        'name' => 'Blueberry Walnut Greek Yogurt Chia Pudding',
        'category' => RecipeCategory::Dessert,
        'meal_type' => MealType::Dessert,
    ]);

    $plan = MealPlan::query()->create([
        'name' => 'TBD Weekly Protocol Detail',
        'goal' => 'Nutrient dense rotation.',
        'schema_type' => MealPlanSchemaType::WeeklyStructured,
        'plan_category' => MealPlanLibraryCategory::NutrientDense,
        'target_total_calories' => 14000,
    ]);

    $plan->dayMeals()->createMany([
        [
            'meal_id' => $omelette->id,
            'day_number' => 1,
            'slot_type' => MealPlanSlotType::Breakfast,
            'slot_index' => 1,
            'is_option_b' => false,
        ],
        [
            'meal_id' => $baked->id,
            'day_number' => 1,
            'slot_type' => MealPlanSlotType::Dessert,
            'slot_index' => 1,
            'is_option_b' => false,
        ],
        [
            'meal_id' => $fruit->id,
            'day_number' => 1,
            'slot_type' => MealPlanSlotType::Dessert,
            'slot_index' => 2,
            'is_option_b' => false,
        ],
        [
            'meal_id' => $chia->id,
            'day_number' => 1,
            'slot_type' => MealPlanSlotType::Dessert,
            'slot_index' => 3,
            'is_option_b' => false,
        ],
    ]);

    $this->actingAs($user)
        ->get(route('admin.meal-plan-library.show', $plan))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/MealPlanDetail')
            ->has('days.0.categories.breakfasts', 2)
            ->has('days.0.categories.desserts', 2)
            ->where('days.0.categories.breakfasts.0.title', NutrientDenseBreakfastOptions::OMELETTE_NAME)
            ->where('days.0.categories.breakfasts.1.title', 'Blueberry Walnut Greek Yogurt Chia Pudding')
            ->where('days.0.categories.desserts.0.title', 'Chocolate Orange Brownie')
            ->where('days.0.categories.desserts.1.title', 'Fruit Salad Bowl'));

    $previewDay = $this->actingAs($user)
        ->getJson(route('admin.meal-plan-library.tier-preview', [
            'mealPlan' => $plan,
            'plan_tier' => 1500,
        ]))
        ->assertOk()
        ->json('days.0');

    expect($previewDay['categories']['desserts'])->toHaveCount(2)
        ->and(collect($previewDay['categories']['desserts'])->pluck('title')->all())
        ->toBe(['Chocolate Orange Brownie', 'Fruit Salad Bowl'])
        ->and(collect($previewDay['categories']['breakfasts'])->pluck('title')->all())
        ->toContain('Blueberry Walnut Greek Yogurt Chia Pudding');
});
