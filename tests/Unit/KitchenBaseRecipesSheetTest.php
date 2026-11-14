<?php

use App\Models\Ingredient;
use App\Services\CustomerCraftKitchenSheetService;
use App\Support\IngredientLibraryCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('kitchen sheet aggregates prepared base recipes with scaled component totals', function () {
    $tomato = Ingredient::factory()->create(['name' => 'Tomato', 'usda_food_category' => 'Vegetables']);
    $onion = Ingredient::factory()->create(['name' => 'Onion', 'usda_food_category' => 'Vegetables']);

    $marinara = Ingredient::factory()->create([
        'name' => 'Marinara Sauce (Base)',
        'usda_food_category' => IngredientLibraryCategory::BaseIngredient,
        'instructions' => '1. Simmer tomatoes.',
        'finished_weight_grams' => 500,
    ]);

    $marinara->components()->attach([
        $tomato->id => ['amount_grams' => 400],
        $onion->id => ['amount_grams' => 60],
    ]);

    $baseRecipes = CustomerCraftKitchenSheetService::baseRecipesFromKitchenRows([
        [
            'adapted_meals' => [
                [
                    'ingredients' => [
                        [
                            'id' => $marinara->id,
                            'name' => $marinara->name,
                            'adapted_amount_grams' => 100,
                            'is_prepared_base' => true,
                        ],
                    ],
                ],
                [
                    'ingredients' => [
                        [
                            'id' => $marinara->id,
                            'name' => $marinara->name,
                            'adapted_amount_grams' => 150,
                            'is_prepared_base' => true,
                        ],
                        [
                            'id' => $tomato->id,
                            'name' => 'Tomato',
                            'adapted_amount_grams' => 80,
                            'is_prepared_base' => false,
                        ],
                    ],
                ],
            ],
        ],
    ]);

    expect($baseRecipes)->toHaveCount(1)
        ->and($baseRecipes[0]['name'])->toBe('Marinara Sauce (Base)')
        ->and($baseRecipes[0]['total_adapted_grams'])->toEqual(250.0)
        ->and($baseRecipes[0]['usage_count'])->toBe(2)
        ->and($baseRecipes[0]['components'])->toHaveCount(2)
        ->and(collect($baseRecipes[0]['components'])->firstWhere('name', 'Tomato')['amount_grams'])->toEqual(400.0);
});
