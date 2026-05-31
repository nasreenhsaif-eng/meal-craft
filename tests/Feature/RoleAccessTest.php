<?php

use App\Enums\OnboardingStep;
use App\Enums\UserRole;
use App\Models\CustomerProfile;
use App\Models\User;

test('admin users can access the admin dashboard', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertSuccessful();
});

test('customers cannot access the admin dashboard', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create();

    $this->actingAs($customer)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('customers cannot access kitchen routes', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create();

    $this->actingAs($customer)
        ->get(route('meals.index'))
        ->assertForbidden();
});

test('admins can preview customer onboarding', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->get(route('onboarding.show', ['step' => OnboardingStep::Welcome->value]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Onboarding/Welcome')
            ->has('mealCraft.onboarding.options.sex'));
});

test('customers cannot access the customer app before onboarding is complete', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create();

    $this->actingAs($customer)
        ->get(route('app.home'))
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::Welcome->value]));
});

test('inactive users cannot log in', function () {
    $user = User::factory()->inactive()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('admin users are redirected to the admin dashboard after login', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->post(route('login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertRedirect(route('admin.dashboard', absolute: false));
});

test('customer users are redirected to onboarding after login', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create();

    $this->post(route('login.store'), [
        'email' => $customer->email,
        'password' => 'password',
    ])->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::Welcome->value], absolute: false));
});
