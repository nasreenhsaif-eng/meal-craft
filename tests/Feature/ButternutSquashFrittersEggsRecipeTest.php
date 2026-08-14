<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\BalancedEggBreakfastRecipeRefiner;
use App\Services\BalancedMealInstructionRefiner;
use App\Support\MealImagePath;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @param  array{calories?: float, protein?: float, carbs?: float, fat?: float}  $macros
 */
function frittersIngredient(string $name, array $macros = []): Ingredient
{
    return Ingredient::factory()->create(array_merge([
        'name' => $name,
        'usda_food_category' => 'Produce',
        'calories' => 50,
        'protein' => 2,
        'carbs' => 8,
        'fat' => 1,
    ], $macros));
}

test('butternut squash fritters recipe matches plated photo with poached eggs and marinara on top', function () {
    MealImagePath::resetPublicMealsSlugIndex();

    foreach ([
        'Butternut Squash' => ['calories' => 45, 'protein' => 1, 'carbs' => 12, 'fat' => 0.1],
        'Egg' => ['calories' => 155, 'protein' => 12.6, 'carbs' => 1.1, 'fat' => 10.6],
        'Quinoa Flour' => ['calories' => 368, 'protein' => 13, 'carbs' => 67, 'fat' => 6],
        'Basil' => ['calories' => 23, 'protein' => 3.2, 'carbs' => 2.7, 'fat' => 0.6],
        'Lemon Juice' => ['calories' => 22, 'protein' => 0.4, 'carbs' => 6.9, 'fat' => 0.2],
        'Garlic (Raw)' => ['calories' => 149, 'protein' => 6.4, 'carbs' => 33, 'fat' => 0.5],
        'Olive Oil' => ['calories' => 884, 'protein' => 0, 'carbs' => 0, 'fat' => 100],
        'Sea Salt' => ['calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0],
        'Black Pepper' => ['calories' => 251, 'protein' => 10.4, 'carbs' => 64, 'fat' => 3.3],
        'Chili Flakes' => ['calories' => 282, 'protein' => 12, 'carbs' => 50, 'fat' => 14],
        'Fennel Seeds' => ['calories' => 345, 'protein' => 16, 'carbs' => 52, 'fat' => 15],
        'Cumin Seeds' => ['calories' => 375, 'protein' => 18, 'carbs' => 44, 'fat' => 22],
        'Coriander Seeds' => ['calories' => 298, 'protein' => 12, 'carbs' => 55, 'fat' => 18],
        'Marinara Sauce (Base)' => ['calories' => 36, 'protein' => 1, 'carbs' => 5.4, 'fat' => 1.5],
        'Fresh Coriander' => ['calories' => 23, 'protein' => 2.1, 'carbs' => 3.7, 'fat' => 0.5],
    ] as $name => $macros) {
        frittersIngredient($name, $macros);
    }

    $meal = Meal::factory()->create([
        'name' => 'Butternut Squash Fritters & Eggs',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Breakfast,
        'image_path' => 'images/meals/butternut_squash_fritters_eggs_marinara.png',
        'short_description' => 'Old blended-egg fritter copy.',
        'instructions' => 'Blend squash with eggs. Serve marinara on the side.',
        'library_edited_at' => null,
    ]);

    $coriander = Ingredient::query()->where('name', 'Fresh Coriander')->firstOrFail();
    $egg = Ingredient::query()->where('name', 'Egg')->firstOrFail();
    $squash = Ingredient::query()->where('name', 'Butternut Squash')->firstOrFail();

    $meal->ingredients()->sync([
        $squash->id => ['amount_grams' => 200, 'amount' => 200, 'unit' => 'g'],
        $egg->id => ['amount_grams' => 100, 'amount' => 100, 'unit' => 'g'],
        $coriander->id => ['amount_grams' => 10, 'amount' => 10, 'unit' => 'g'],
    ]);

    app(BalancedEggBreakfastRecipeRefiner::class)->refine();
    app(BalancedMealInstructionRefiner::class)->refine();

    $meal->refresh()->load('ingredients');
    $names = $meal->ingredients->pluck('name')->all();
    $eggGrams = (float) $meal->ingredients->firstWhere('name', 'Egg')->pivot->amount_grams;
    $instructions = (string) $meal->instructions;

    expect($names)->toContain('Basil')
        ->and($names)->toContain('Black Pepper')
        ->and($names)->toContain('Marinara Sauce (Base)')
        ->and($names)->toContain('Quinoa Flour')
        ->and($names)->not->toContain('Fresh Coriander')
        ->and($eggGrams)->toBe(100.0)
        ->and($meal->short_description)->toContain('soft-poached eggs')
        ->and($meal->short_description)->toContain('marinara')
        ->and($instructions)->toContain('Soft-poach the eggs')
        ->and($instructions)->toContain('spoon generously over the fritters')
        ->and($instructions)->toContain('2 large fritters')
        ->and($instructions)->not->toContain('Blend')
        ->and($instructions)->not->toContain('on the side')
        ->and($meal->image_path)->toBe('images/meals/butternut_squash_fritters_eggs_marinara.png')
        ->and(MealImagePath::resolveUrl($meal->image_path, $meal->name))
        ->toContain('butternut_squash_fritters_eggs_marinara.png');
});
