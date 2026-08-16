<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\CustomerProfile;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use App\Services\Nutrition\AdaptedMenuBuilder;
use App\Support\StandardMeatPortion;
use Illuminate\Support\Facades\Cache;

test('adapted menu build heals library-wide one-gram rosemary chicken when enabled', function (): void {
    config(['customer_nutrition.heal_collapsed_protein_on_adapted_menu_build' => true]);
    Cache::forget(AdaptedMenuBuilder::COLLAPSED_PROTEIN_HEAL_CACHE_KEY);

    $user = User::factory()->create();
    $profile = CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 1500,
        'protein_percentage' => 40,
        'carb_percentage' => 30,
        'fat_percentage' => 30,
    ]);

    $chicken = Ingredient::factory()->create([
        'name' => 'Rosemary Garlic Chicken (Base)',
        'calories' => 200,
        'protein' => 24,
        'carbs' => 3,
        'fat' => 10,
    ]);
    $rocca = Ingredient::factory()->create([
        'name' => 'Rocca',
        'calories' => 25,
        'protein' => 2.6,
        'carbs' => 3.7,
        'fat' => 0.4,
    ]);

    $meal = Meal::factory()->create([
        'name' => 'Rosemary Chicken Rocca Salad',
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 130,
        'total_protein' => 4,
        'total_carbs' => 10.2,
        'total_fat' => 9.4,
        'library_edited_at' => now(),
    ]);
    $meal->ingredients()->sync([
        $chicken->id => ['amount_grams' => 1.0, 'amount' => 1.0, 'unit' => 'g'],
        $rocca->id => ['amount_grams' => 50, 'amount' => 50, 'unit' => 'g'],
    ]);

    $menu = AdaptedMenuBuilder::build($profile, ['craft_key' => 'full']);
    $card = collect($menu['scalable_meals'])->firstWhere('name', 'Rosemary Chicken Rocca Salad');

    expect($card)->not->toBeNull()
        ->and((float) $card['adapted_nutrition']['protein'])->toBeGreaterThan(20.0)
        ->and((float) $card['adapted_nutrition']['calories'])->toBeGreaterThan(200.0);

    $meal->refresh()->load('ingredients');
    expect((float) $meal->ingredients->firstWhere('name', 'Rosemary Garlic Chicken (Base)')->pivot->amount_grams)
        ->toBe(StandardMeatPortion::GRAMS)
        ->and((float) $meal->total_protein)->toBeGreaterThan(20.0);
});
