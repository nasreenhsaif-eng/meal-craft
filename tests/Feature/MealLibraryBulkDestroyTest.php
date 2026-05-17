<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Meal;
use App\Models\User;

test('guest cannot bulk destroy meals from meal library', function () {
    $meal = Meal::query()->create([
        'name' => 'Guest Guard Meal',
        'category' => RecipeCategory::Meal,
        'total_calories' => 100,
        'total_protein' => 1,
        'total_carbs' => 1,
        'total_fat' => 1,
        'total_b6' => 0,
        'total_folate' => 0,
        'total_b12' => 0,
        'total_iron' => 0,
        'total_magnesium' => 0,
        'total_fiber' => 0,
        'total_sugar' => 0,
        'total_calcium' => 0,
        'total_potassium' => 0,
        'total_sodium' => 0,
        'total_zinc' => 0,
        'total_vitamin_c' => 0,
        'total_vitamin_a' => 0,
        'total_vitamin_e' => 0,
        'total_vitamin_d' => 0,
        'total_vitamin_k' => 0,
    ]);

    $this->post(route('admin.meal-library.bulk-destroy'), ['ids' => [$meal->id]])
        ->assertRedirect();

    expect(Meal::query()->find($meal->id))->not->toBeNull();
});

test('authenticated user can permanently delete meals from meal library', function () {
    $user = User::factory()->create();

    $keep = Meal::query()->create([
        'name' => 'Keep Meal',
        'category' => RecipeCategory::Meal,
        'total_calories' => 100,
        'total_protein' => 1,
        'total_carbs' => 1,
        'total_fat' => 1,
        'total_b6' => 0,
        'total_folate' => 0,
        'total_b12' => 0,
        'total_iron' => 0,
        'total_magnesium' => 0,
        'total_fiber' => 0,
        'total_sugar' => 0,
        'total_calcium' => 0,
        'total_potassium' => 0,
        'total_sodium' => 0,
        'total_zinc' => 0,
        'total_vitamin_c' => 0,
        'total_vitamin_a' => 0,
        'total_vitamin_e' => 0,
        'total_vitamin_d' => 0,
        'total_vitamin_k' => 0,
    ]);

    $remove = Meal::query()->create([
        'name' => 'Remove Meal',
        'category' => RecipeCategory::Meal,
        'total_calories' => 50,
        'total_protein' => 1,
        'total_carbs' => 1,
        'total_fat' => 1,
        'total_b6' => 0,
        'total_folate' => 0,
        'total_b12' => 0,
        'total_iron' => 0,
        'total_magnesium' => 0,
        'total_fiber' => 0,
        'total_sugar' => 0,
        'total_calcium' => 0,
        'total_potassium' => 0,
        'total_sodium' => 0,
        'total_zinc' => 0,
        'total_vitamin_c' => 0,
        'total_vitamin_a' => 0,
        'total_vitamin_e' => 0,
        'total_vitamin_d' => 0,
        'total_vitamin_k' => 0,
    ]);

    $this->actingAs($user)
        ->post(route('admin.meal-library.bulk-destroy'), ['ids' => [$remove->id]])
        ->assertRedirect(route('admin.meal-library'))
        ->assertSessionHas('success');

    expect(Meal::queryForMealLibrary()->pluck('id')->all())
        ->toContain($keep->id)
        ->not->toContain($remove->id);

    expect(Meal::withTrashed()->find($remove->id))->toBeNull();
});

test('bulk destroy removes duplicate soft deleted meals with the same name', function () {
    $user = User::factory()->create();

    $older = Meal::query()->create([
        'name' => 'Smashed white Beans',
        'category' => RecipeCategory::Meal,
        'total_calories' => 50,
        'total_protein' => 1,
        'total_carbs' => 1,
        'total_fat' => 1,
        'total_b6' => 0,
        'total_folate' => 0,
        'total_b12' => 0,
        'total_iron' => 0,
        'total_magnesium' => 0,
        'total_fiber' => 0,
        'total_sugar' => 0,
        'total_calcium' => 0,
        'total_potassium' => 0,
        'total_sodium' => 0,
        'total_zinc' => 0,
        'total_vitamin_c' => 0,
        'total_vitamin_a' => 0,
        'total_vitamin_e' => 0,
        'total_vitamin_d' => 0,
        'total_vitamin_k' => 0,
    ]);
    $older->delete();

    $active = Meal::query()->create([
        'name' => 'Smashed white Beans',
        'category' => RecipeCategory::Meal,
        'total_calories' => 60,
        'total_protein' => 1,
        'total_carbs' => 1,
        'total_fat' => 1,
        'total_b6' => 0,
        'total_folate' => 0,
        'total_b12' => 0,
        'total_iron' => 0,
        'total_magnesium' => 0,
        'total_fiber' => 0,
        'total_sugar' => 0,
        'total_calcium' => 0,
        'total_potassium' => 0,
        'total_sodium' => 0,
        'total_zinc' => 0,
        'total_vitamin_c' => 0,
        'total_vitamin_a' => 0,
        'total_vitamin_e' => 0,
        'total_vitamin_d' => 0,
        'total_vitamin_k' => 0,
    ]);

    $this->actingAs($user)
        ->post(route('admin.meal-library.bulk-destroy'), ['ids' => [$active->id]])
        ->assertRedirect(route('admin.meal-library'))
        ->assertSessionHas('success');

    expect(Meal::withTrashed()->find($older->id))->toBeNull()
        ->and(Meal::withTrashed()->find($active->id))->toBeNull()
        ->and(Meal::queryForMealLibrary()->whereRaw('lower(trim(name)) = ?', ['smashed white beans'])->exists())->toBeFalse();
});

test('bulk destroy excludes base recipes from deletion even when id is submitted', function () {
    $user = User::factory()->create();

    $baseRecipe = Meal::query()->create([
        'name' => 'Base Broth',
        'category' => RecipeCategory::BaseRecipe,
        'meal_type' => MealType::BaseRecipe,
        'total_calories' => 10,
        'total_protein' => 1,
        'total_carbs' => 1,
        'total_fat' => 1,
        'total_b6' => 0,
        'total_folate' => 0,
        'total_b12' => 0,
        'total_iron' => 0,
        'total_magnesium' => 0,
        'total_fiber' => 0,
        'total_sugar' => 0,
        'total_calcium' => 0,
        'total_potassium' => 0,
        'total_sodium' => 0,
        'total_zinc' => 0,
        'total_vitamin_c' => 0,
        'total_vitamin_a' => 0,
        'total_vitamin_e' => 0,
        'total_vitamin_d' => 0,
        'total_vitamin_k' => 0,
    ]);

    $this->actingAs($user)
        ->post(route('admin.meal-library.bulk-destroy'), ['ids' => [$baseRecipe->id]])
        ->assertRedirect(route('admin.meal-library'))
        ->assertSessionHas('error');

    expect(Meal::query()->find($baseRecipe->id))->not->toBeNull()
        ->and(Meal::query()->find($baseRecipe->id)?->trashed())->toBeFalse();
});

test('meal library index inertia payload excludes meals removed via bulk destroy', function () {
    $user = User::factory()->create();

    $meal = Meal::query()->create([
        'name' => 'Inertia Index Gone Meal',
        'category' => RecipeCategory::Meal,
        'total_calories' => 100,
        'total_protein' => 1,
        'total_carbs' => 1,
        'total_fat' => 1,
        'total_b6' => 0,
        'total_folate' => 0,
        'total_b12' => 0,
        'total_iron' => 0,
        'total_magnesium' => 0,
        'total_fiber' => 0,
        'total_sugar' => 0,
        'total_calcium' => 0,
        'total_potassium' => 0,
        'total_sodium' => 0,
        'total_zinc' => 0,
        'total_vitamin_c' => 0,
        'total_vitamin_a' => 0,
        'total_vitamin_e' => 0,
        'total_vitamin_d' => 0,
        'total_vitamin_k' => 0,
    ]);

    $this->actingAs($user)
        ->postJson(route('admin.meal-library.bulk-destroy'), ['ids' => [$meal->id]])
        ->assertOk()
        ->assertJsonPath('deleted', 1);

    $this->actingAs($user)
        ->get(route('admin.meal-library'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/MealLibrary')
            ->where('meals', fn ($meals) => collect($meals)->pluck('id')->doesntContain((string) $meal->id)));
});

test('meal library export excludes meals removed via bulk destroy', function () {
    $user = User::factory()->create();

    $meal = Meal::query()->create([
        'name' => 'Export After Delete Meal',
        'category' => RecipeCategory::Meal,
        'total_calories' => 100,
        'total_protein' => 1,
        'total_carbs' => 1,
        'total_fat' => 1,
        'total_b6' => 0,
        'total_folate' => 0,
        'total_b12' => 0,
        'total_iron' => 0,
        'total_magnesium' => 0,
        'total_fiber' => 0,
        'total_sugar' => 0,
        'total_calcium' => 0,
        'total_potassium' => 0,
        'total_sodium' => 0,
        'total_zinc' => 0,
        'total_vitamin_c' => 0,
        'total_vitamin_a' => 0,
        'total_vitamin_e' => 0,
        'total_vitamin_d' => 0,
        'total_vitamin_k' => 0,
    ]);

    $this->actingAs($user)
        ->postJson(route('admin.meal-library.bulk-destroy'), ['ids' => [$meal->id]])
        ->assertOk();

    $response = $this->actingAs($user)->get(route('meals.library.export-csv'));

    $response->assertOk();

    $csv = $response->streamedContent();

    expect($csv)->not->toContain('Export After Delete Meal');
});
