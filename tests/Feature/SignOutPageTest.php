<?php

use App\Models\CustomerProfile;
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

test('authenticated users visiting join are redirected to sign out', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->get(route('join'))
        ->assertRedirect(route('sign-out', absolute: false));
});

test('authenticated users visiting login are redirected to sign out', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create();

    $this->actingAs($customer)
        ->get(route('login'))
        ->assertRedirect(route('sign-out', absolute: false));
});

test('failed login shows error message on login page', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('These credentials do not match our records.', false);
});
