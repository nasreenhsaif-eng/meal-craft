<?php

use App\Models\User;

test('usda ingredient search is disabled (local-only mode)', function () {
    $this->get('/ingredients/usda-search')->assertNotFound();
});

test('authenticated users also cannot access usda ingredient search', function () {
    $this->actingAs(User::factory()->create());
    $this->get('/ingredients/usda-search')->assertNotFound();
});
