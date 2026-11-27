<?php

use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use App\Services\BalancedRotationMealRecipeRefiner;
use Inertia\Testing\AssertableInertia;

test('bulk dessert detail view shows kitchen batch egg count not a fraction of an egg', function () {
    $user = User::factory()->create();

    $egg = Ingredient::factory()->create([
        'name' => 'Eggs (Large)',
        'usda_food_category' => 'Protein',
        'calories' => 143,
        'protein' => 12.6,
        'carbs' => 0.7,
        'fat' => 9.5,
        'is_verified' => true,
    ]);
    $flour = Ingredient::factory()->create([
        'name' => 'Almond Flour (Base)',
        'usda_food_category' => 'Prepared',
        'calories' => 579,
        'protein' => 21,
        'carbs' => 22,
        'fat' => 50,
        'is_verified' => true,
    ]);

    $meal = Meal::factory()->create([
        'name' => BalancedRotationMealRecipeRefiner::CHOCOLATE_ORANGE_BROWNIE_NAME,
        'is_bulk' => true,
        'servings_count' => 16,
        'short_description' => 'Batch brownie test',
        'total_calories' => 169,
    ]);

    $meal->ingredients()->sync([
        $egg->id => ['amount_grams' => 150, 'amount' => 150, 'unit' => 'g'],
        $flour->id => ['amount_grams' => 95, 'amount' => 95, 'unit' => 'g'],
    ]);

    $this->actingAs($user)
        ->get(route('admin.meal-library'))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) {
            $page->component('Admin/MealLibrary')->has('meals', 1);

            /** @var array<int, mixed> $props */
            $props = $page->toArray()['props'];
            $lines = $props['meals'][0]['detailView']['ingredients'] ?? null;
            expect($lines)->toBeArray();

            $joined = implode(' | ', array_map(static fn ($line): string => (string) $line, $lines));

            expect($joined)
                ->toContain('3 eggs raw')
                ->and($joined)->not->toContain('1/4 egg')
                ->and($joined)->not->toContain('1/2 egg')
                ->and($joined)->toContain('95g');

            $note = (string) ($props['meals'][0]['detailView']['ingredientsPrepNote'] ?? '');
            expect($note)
                ->toContain('Batch recipe for 16 servings')
                ->toContain('full pan');
        });
});
