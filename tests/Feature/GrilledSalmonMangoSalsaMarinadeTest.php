<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\BalancedComplexCarbRecipeRefiner;
use App\Services\BalancedMealInstructionRefiner;
use App\Support\StandardMeatPortion;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('grilled salmon mango salsa includes lemon herb marinade and citrus herb sauce', function (): void {
    foreach ([
        'Avocado',
        'Bell Pepper (Red)',
        'Cashew Nuts',
        'Citrus Herb Sauce (Base)',
        'Cucumber',
        'Fresh Coriander',
        'Lemon Herb Salmon Marinade (Base)',
        'Mango',
        'Pumpkin',
        'Purslane',
        'Salmon (Raw)',
    ] as $name) {
        Ingredient::factory()->create([
            'name' => $name,
            'usda_food_category' => str_contains($name, '(Base)') ? 'Base Ingredient' : 'Vegetables',
            'calories' => 100,
            'protein' => 10,
            'carbs' => 5,
            'fat' => 5,
            'is_verified' => true,
        ]);
    }

    $meal = Meal::factory()->create([
        'name' => 'Grilled Salmon Mango Salsa',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
        'instructions' => 'old',
    ]);

    $refiner = app(BalancedComplexCarbRecipeRefiner::class);
    $definitionsMethod = (new ReflectionClass($refiner))->getMethod('recipeDefinitions');
    $definitionsMethod->setAccessible(true);
    /** @var array<string, array{ingredients: array<string, float>, diet_tags?: list<string>}> $recipes */
    $recipes = $definitionsMethod->invoke($refiner);

    expect($recipes['Grilled Salmon Mango Salsa']['ingredients']['Lemon Herb Salmon Marinade (Base)'])->toBe(20.0)
        ->and($recipes['Grilled Salmon Mango Salsa']['ingredients']['Citrus Herb Sauce (Base)'])->toBe(30.0)
        ->and($recipes['Grilled Salmon Mango Salsa']['ingredients']['Salmon (Raw)'])->toBe(StandardMeatPortion::GRAMS)
        ->and($recipes['Grilled Salmon Mango Salsa']['ingredients'])->not->toHaveKey('Lime Juice');

    $syncMeal = (new ReflectionClass($refiner))->getMethod('syncMeal');
    $syncMeal->setAccessible(true);
    $syncMeal->invoke(
        $refiner,
        $meal,
        $recipes['Grilled Salmon Mango Salsa']['ingredients'],
        $recipes['Grilled Salmon Mango Salsa']['diet_tags'] ?? [],
    );

    $meal->refresh()->load('ingredients');

    expect($meal->ingredients->pluck('name')->all())
        ->toContain('Lemon Herb Salmon Marinade (Base)', 'Citrus Herb Sauce (Base)');

    $instructionRefiner = app(BalancedMealInstructionRefiner::class);
    $instructionMethod = (new ReflectionClass($instructionRefiner))->getMethod('instructionDefinitions');
    $instructionMethod->setAccessible(true);
    /** @var array<string, string> $instructions */
    $instructions = $instructionMethod->invoke($instructionRefiner);

    expect($instructions['Grilled Salmon Mango Salsa'])
        ->toContain('Lemon Herb Salmon Marinade (Base)')
        ->toContain('Citrus Herb Sauce (Base)')
        ->toContain('moist and glossy');
});
