<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use App\Services\CollapsedPrimaryProteinHealer;
use App\Support\StandardMeatPortion;

test('healer restores one-gram rosemary chicken on every weekday-style meal and detail view shows 150g', function (): void {
    $user = User::factory()->create();

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

    $names = [
        'Rosemary Chicken Rocca Salad',
        'Mediterranean Crunch Salad',
        'Rosemary Garlic Chicken w Mushroom, Spinach & Roasted Sweet Potato',
        'Rosemary Garlic Chicken w Pomegranate Glaze, Beetroot & Rocca',
    ];

    $meals = [];
    foreach ($names as $name) {
        $meal = Meal::factory()->create([
            'name' => $name,
            'meal_type' => MealType::Main,
            'category' => RecipeCategory::Meal,
            'total_calories' => 130,
            'total_protein' => 4,
            'total_carbs' => 10.2,
            'total_fat' => 9.4,
            'nutrition_aggregates_synced' => true,
            'library_edited_at' => now(),
        ]);
        $meal->ingredients()->sync([
            $chicken->id => ['amount_grams' => 1.0, 'amount' => 1.0, 'unit' => 'g'],
            $rocca->id => ['amount_grams' => 40, 'amount' => 40, 'unit' => 'g'],
        ]);
        $meals[] = $meal;
    }

    $healed = app(CollapsedPrimaryProteinHealer::class)->healAll();
    expect($healed)->toContain('Rosemary Chicken Rocca Salad');

    foreach ($meals as $meal) {
        $meal->refresh()->load('ingredients');
        expect((float) $meal->ingredients->firstWhere('name', 'Rosemary Garlic Chicken (Base)')->pivot->amount_grams)
            ->toBe(StandardMeatPortion::GRAMS, $meal->name)
            ->and((float) $meal->total_protein)->toBeGreaterThan(20.0, $meal->name);
    }

    $roccaMeal = $meals[0]->fresh();
    $response = $this->actingAs($user)->getJson(route('api.meals.detail-view', $roccaMeal));
    $response->assertOk();

    $ingredients = $response->json('detailView.ingredients');
    expect($ingredients)->toBeArray();

    $chickenLine = collect($ingredients)->first(
        fn ($line): bool => is_string($line) && str_contains($line, 'Rosemary Garlic Chicken (Base)'),
    );

    expect($chickenLine)->toBeString()
        ->and($chickenLine)->not->toStartWith('1g ')
        ->and((float) preg_replace('/^([0-9.]+)g.*/', '$1', (string) $chickenLine))->toBeGreaterThanOrEqual(150.0);
});
