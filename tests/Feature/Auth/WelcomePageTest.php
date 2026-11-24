<?php

use App\Models\User;

test('guests visiting home see the marketing landing page', function () {
    $response = $this->get(route('home'))
        ->assertOk()
        ->assertSee('mc-auth-welcome-root', false);

    $configJson = $response->getContent();
    preg_match('/id="mc-auth-welcome-config"[^>]*>(.*?)<\/script>/s', $configJson, $matches);

    expect($matches[1] ?? null)->not->toBeNull();

    $config = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);

    expect($config)
        ->loginHref->toBe(route('login'))
        ->joinHref->toBe(route('join'));
});

test('guests visiting welcome are redirected to home', function () {
    $this->get(route('welcome'))
        ->assertRedirect(route('home'));
});

test('authenticated users visiting home are redirected to their home path', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->get(route('home'))
        ->assertRedirect(route('admin.dashboard', absolute: false));
});

test('authenticated users visiting welcome are redirected to home then their home path', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->get(route('welcome'))
        ->assertRedirect(route('home'));
});
