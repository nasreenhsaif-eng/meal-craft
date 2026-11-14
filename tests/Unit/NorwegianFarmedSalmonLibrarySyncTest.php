<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\NorwegianFarmedSalmonLibrarySync;
use App\Support\IngredientLibraryCategory;
use App\Support\NorwegianFarmedSalmonNutrition;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('vitamin D is the Matvaretabellen farmed raw value for both salmon labels', function (): void {
    expect(NorwegianFarmedSalmonNutrition::vitaminDMcgPer100g('Salmon'))->toBe(7.0)
        ->and(NorwegianFarmedSalmonNutrition::vitaminDMcgPer100g('Salmon (Raw)'))->toBe(7.0)
        ->and(NorwegianFarmedSalmonNutrition::vitaminDMcgPer100g('Mackerel'))->toBeNull();
});

test('library sync replaces USDA salmon vitamin D and refreshes meals and calorie tabs', function (): void {
    $salmon = Ingredient::factory()->create([
        'name' => 'Salmon',
        'usda_food_category' => 'Protein',
        'fdc_id' => 173686,
        'calories' => 208,
        'protein' => 20.4,
        'carbs' => 0,
        'fat' => 13.4,
        'micronutrients' => ['vitamin_d' => 11],
    ]);

    $meal = Meal::factory()->tiers()->create([
        'name' => 'Baked Salmon with Fermented Chimichurri & Roasted Vegetables',
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 500,
        'total_protein' => 40,
        'total_carbs' => 20,
        'total_fat' => 18,
        'total_vitamin_d' => 18.15,
    ]);
    $meal->ingredients()->attach($salmon->id, ['amount_grams' => 165]);

    $tier = $meal->calorieTiers()->create([
        'calorie_tier' => 500,
        'designed_calories' => 500,
        'total_calories' => 500,
        'total_protein' => 40,
        'total_carbs' => 20,
        'total_fat' => 18,
        'nutrition' => [
            'calories' => 500,
            'protein' => 40,
            'carbs' => 20,
            'fat' => 18,
            'vitamin_d' => 18.15,
        ],
    ]);
    $tier->ingredients()->attach($salmon->id, ['amount_grams' => 165]);

    $counts = app(NorwegianFarmedSalmonLibrarySync::class)->apply();

    $salmon->refresh();
    $meal->refresh();
    $tier->refresh();

    expect($counts['ingredients'])->toBe(1)
        ->and($salmon->fdc_id)->toBeNull()
        ->and((float) ($salmon->micronutrients['vitamin_d'] ?? 0))->toBe(7.0)
        ->and($salmon->description)->toBe(NorwegianFarmedSalmonNutrition::DESCRIPTION)
        ->and((float) $meal->total_vitamin_d)->toBe(11.55)
        ->and((float) ($tier->nutrition['vitamin_d'] ?? 0))->toBe(11.55);
});

test('library sync rerolls salmon parent bases before meal totals', function (): void {
    $salmon = Ingredient::factory()->create([
        'name' => 'Salmon (Raw)',
        'usda_food_category' => 'Proteins',
        'fdc_id' => 175167,
        'calories' => 208,
        'protein' => 20.4,
        'carbs' => 0,
        'fat' => 13.4,
        'micronutrients' => ['vitamin_d' => 11],
    ]);

    $base = Ingredient::factory()->create([
        'name' => 'Tandoori Salmon (Base)',
        'usda_food_category' => IngredientLibraryCategory::BaseIngredient,
        'fdc_id' => null,
        'calories' => 222,
        'protein' => 17,
        'carbs' => 3,
        'fat' => 15,
        'finished_weight_grams' => 372,
        'micronutrients' => ['vitamin_d' => 8.871],
    ]);
    $base->components()->attach($salmon->id, ['amount_grams' => 300]);

    $meal = Meal::factory()->create([
        'name' => 'Tandoori Salmon Plate',
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_vitamin_d' => 16.5,
    ]);
    $meal->ingredients()->attach($base->id, ['amount_grams' => 150]);

    app(NorwegianFarmedSalmonLibrarySync::class)->apply();

    $base->refresh();
    $meal->refresh();

    expect((float) ($base->micronutrients['vitamin_d'] ?? 0))->toBe(5.6452)
        ->and((float) $meal->total_vitamin_d)->toBe(8.4678);
});
