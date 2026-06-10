<?php

use App\Models\User;

test('authenticated users can view the sign out page with logout control', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->get(route('sign-out'))
        ->assertOk()
        ->assertSee('Log out', false)
        ->assertSee($admin->email, false);
});

test('guests see sign out page with signup guidance instead of login redirect', function () {
    $this->get(route('sign-out'))
        ->assertOk()
        ->assertSee('You are not signed in', false)
        ->assertSee('Customer signup', false);
});

test('authenticated users visiting join are redirected away from registration', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->get(route('join'))
        ->assertRedirect(route('dashboard', absolute: false));
});
