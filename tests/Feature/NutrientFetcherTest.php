<?php

use App\Models\User;

test('nutrient fetcher is disabled (local-only mode)', function () {
    $this->get('/nutrients/fetcher')->assertNotFound();
});

test('authenticated users also cannot access nutrient fetcher', function () {
    $this->actingAs(User::factory()->create());
    $this->get('/nutrients/fetcher')->assertNotFound();
});
