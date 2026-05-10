<?php

use App\Models\Ingredient;
use App\Models\User;

test('ingredients page auto-sorts verified items to top', function () {
    $user = User::factory()->create();

    $inStock = Ingredient::factory()->create([
        'name' => 'Verified Ingredient',
        'is_verified' => true,
    ]);

    $outOfStock = Ingredient::factory()->create([
        'name' => 'Unverified Ingredient',
        'is_verified' => false,
    ]);

    $response = $this->actingAs($user)->get(route('ingredients.index'));

    $response->assertStatus(200);

    $response->assertSeeInOrder([
        $inStock->name,
        $outOfStock->name,
    ]);
});
