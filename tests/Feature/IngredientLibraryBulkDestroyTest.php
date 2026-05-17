<?php

use App\Models\Ingredient;
use App\Models\User;

test('guest cannot bulk destroy ingredients from ingredient library', function () {
    $ingredient = Ingredient::factory()->create(['is_verified' => true]);

    $this->post(route('admin.ingredient-library.bulk-destroy'), ['ids' => [$ingredient->id]])
        ->assertRedirect();

    expect(Ingredient::query()->find($ingredient->id))->not->toBeNull();
});

test('authenticated user can bulk delete ingredients from ingredient library', function () {
    $user = User::factory()->create();

    $keep = Ingredient::factory()->create(['is_verified' => true, 'name' => 'Keep Me']);
    $remove = Ingredient::factory()->create(['is_verified' => true, 'name' => 'Remove Me']);

    $this->actingAs($user)
        ->postJson(route('admin.ingredient-library.bulk-destroy'), ['ids' => [$remove->id]])
        ->assertOk()
        ->assertJson(['deleted' => 1]);

    expect(Ingredient::query()->find($keep->id))->not->toBeNull()
        ->and(Ingredient::query()->find($remove->id))->toBeNull();
});
