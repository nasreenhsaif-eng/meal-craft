<?php

use App\Models\User;

test('guests visiting home are redirected to login', function () {
    $this->get(route('home'))
        ->assertRedirect(route('login'));
});

test('guests can view the marketing welcome page', function () {
    $this->get(route('welcome'))
        ->assertOk()
        ->assertSee('mc-auth-welcome-root', false);
});

test('authenticated users visiting home are redirected to their home path', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->get(route('home'))
        ->assertRedirect(route('admin.dashboard', absolute: false));
});

test('authenticated users visiting welcome are redirected to their home path', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->get(route('welcome'))
        ->assertRedirect(route('admin.dashboard', absolute: false));
});
