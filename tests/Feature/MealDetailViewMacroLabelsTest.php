<?php

use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;

test('meal detail view nutritional summary lists total carbs before net carbs', function () {
    $user = User::factory()->customer()->create();

    $ingredient = Ingredient::factory()->create([
        'name' => 'Macro Label Oats',
        'calories' => 100,
        'protein' => 3,
        'carbs' => 18,
        'fat' => 2,
        'micronutrients' => [
            'fiber' => 4,
            'sugar' => 1,
        ],
    ]);

    $meal = Meal::factory()->create([
        'name' => 'Macro Label Detail Meal',
        'total_calories' => 100,
        'total_protein' => 3,
        'total_carbs' => 18,
        'total_fat' => 2,
        'nutrition_aggregates_synced' => false,
    ]);
    $meal->ingredients()->attach($ingredient->id, [
        'amount_grams' => 100,
        'amount' => 100,
        'unit' => 'g',
    ]);

    $rows = $this->actingAs($user)
        ->getJson(route('api.meals.detail-view', $meal))
        ->assertOk()
        ->json('detailView.nutritionalData.sections.0.rows');

    $labels = collect($rows)->pluck('label')->all();

    expect($labels)->toContain('Carbs (g)')
        ->and($labels)->toContain('Net carbs (g)');

    $carbsIndex = array_search('Carbs (g)', $labels, true);
    $netCarbsIndex = array_search('Net carbs (g)', $labels, true);

    expect($carbsIndex)->toBeInt()
        ->and($netCarbsIndex)->toBeInt()
        ->and($carbsIndex)->toBeLessThan($netCarbsIndex)
        ->and($rows[$carbsIndex]['valueClass'] ?? null)->toBe('text-[#8F55A8]');
});
