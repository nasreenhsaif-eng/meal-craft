<?php

use App\Enums\MealPlanLibraryCategory;
use App\Enums\MealPlanSchemaType;
use App\Enums\MealPlanSlotType;
use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\CustomerProfile;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\User;
use App\Services\MealPlanDefaultDaySelections;
use App\Services\Nutrition\FullCraftDayMenuBuilder;
use App\Support\PrimaryFullCraftMainSlots;

test('admin can save meal plan default day selections', function (): void {
    $user = User::factory()->create();

    $breakfast = Meal::factory()->create([
        'name' => 'Default Breakfast',
        'category' => RecipeCategory::Breakfast,
        'meal_type' => MealType::Breakfast,
    ]);
    $mainA = Meal::factory()->create([
        'name' => 'Default Main A',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
    ]);
    $mainB = Meal::factory()->create([
        'name' => 'Default Main B',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
    ]);
    $salad = Meal::factory()->create([
        'name' => 'Default Salad',
        'category' => RecipeCategory::SideSalad,
        'meal_type' => MealType::Salad,
    ]);

    $plan = MealPlan::query()->create([
        'name' => 'Defaults Plan',
        'goal' => 'Test defaults',
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
            'slot_index' => 5,
            'is_option_b' => false,
        ],
        [
            'meal_id' => $salad->id,
            'day_number' => 1,
            'slot_type' => MealPlanSlotType::Salad,
            'slot_index' => 1,
            'is_option_b' => false,
        ],
    ]);

    $selections = [
        1 => [
            'breakfasts' => [$breakfast->id],
            'meals' => [$mainB->id, $mainA->id],
            'sideSalads' => [$salad->id],
            'desserts' => [],
            'soup' => [],
        ],
    ];

    $this->actingAs($user)
        ->put(route('admin.meal-plan-library.default-selections', $plan), [
            'selections' => $selections,
        ])
        ->assertRedirect(route('admin.meal-plan-library.show', $plan));

    $plan->refresh();

    expect(MealPlanDefaultDaySelections::forDay($plan, 1)['meals'])->toBe([$mainB->id, $mainA->id])
        ->and(MealPlanDefaultDaySelections::isRecommendedMealId($plan, 1, 'meals', $mainB->id))->toBeTrue()
        ->and(MealPlanDefaultDaySelections::isRecommendedMealId($plan, 1, 'meals', $mainA->id))->toBeTrue();

    $this->actingAs($user)
        ->get(route('admin.meal-plan-library.show', $plan))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/MealPlanDetail')
            ->where('defaultDaySelections.1.meals.0', $mainB->id)
            ->where('defaultDaySelections.1.meals.1', $mainA->id)
        );
});

test('stored meal plan defaults override convention primary main slots', function (): void {
    $main1 = Meal::factory()->create([
        'name' => 'Slot One Main',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
    ]);
    $main3 = Meal::factory()->create([
        'name' => 'Slot Three Main',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
    ]);
    $main5 = Meal::factory()->create([
        'name' => 'Slot Five Main',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
    ]);

    $plan = MealPlan::query()->create([
        'name' => 'Override Defaults Plan',
        'goal' => 'Test',
        'schema_type' => MealPlanSchemaType::WeeklyStructured,
        'plan_category' => MealPlanLibraryCategory::NutrientDense,
        'target_total_calories' => 10500,
    ]);

    $plan->dayMeals()->createMany([
        [
            'meal_id' => $main1->id,
            'day_number' => 1,
            'slot_type' => MealPlanSlotType::Main,
            'slot_index' => 1,
            'is_option_b' => false,
        ],
        [
            'meal_id' => $main3->id,
            'day_number' => 1,
            'slot_type' => MealPlanSlotType::Main,
            'slot_index' => 3,
            'is_option_b' => false,
        ],
        [
            'meal_id' => $main5->id,
            'day_number' => 1,
            'slot_type' => MealPlanSlotType::Main,
            'slot_index' => 5,
            'is_option_b' => false,
        ],
    ]);

    MealPlanDefaultDaySelections::store($plan, [
        1 => [
            'breakfasts' => [],
            'meals' => [$main3->id, $main1->id],
            'sideSalads' => [],
            'desserts' => [],
            'soup' => [],
        ],
    ]);

    $profile = new CustomerProfile([
        'diet_protocol' => 'nutrient_dense',
        'daily_calorie_target' => 1500,
        'protein_percentage' => 32,
        'carb_percentage' => 28,
        'fat_percentage' => 40,
    ]);

    $selection = FullCraftDayMenuBuilder::defaultDaySelectionForRows(
        $profile,
        $plan->dayMeals()->where('is_option_b', false)->get(),
    );

    expect($selection['meals'])->toBe([$main3->id, $main1->id])
        ->and(PrimaryFullCraftMainSlots::NUTRIENT_DENSE)->toBe([1, 5]);
});
