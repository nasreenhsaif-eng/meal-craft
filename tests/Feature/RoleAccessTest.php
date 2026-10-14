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
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::Gender->value]))
        ->assertSessionHas('error');
});

test('customers cannot access kitchen routes', function () {
    $customer = User::factory()->customer()->create();
    $profile = CustomerProfile::factory()->for($customer)->create();
    $profile->craftPlans()->create([
        'craft_key' => 'full',
        'week_duration' => 5,
        'selected_weekdays' => [1, 2, 3, 4, 5],
        'submitted_at' => now(),
    ]);

    $this->actingAs($customer)
        ->get(route('meals.index'))
        ->assertRedirect(route('app.home'))
        ->assertSessionHas('error');
});

test('admins can preview customer onboarding', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->get(route('onboarding.show', ['step' => OnboardingStep::Gender->value]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Onboarding/Container')
            ->where('activeStep', OnboardingStep::Gender->value)
            ->has('mealCraft.onboarding.options.sex'));
});

test('admins can save diet protocol while previewing onboarding', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    CustomerProfile::factory()->for($admin)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::DietProtocol,
        'sex' => 'male',
    ]);

    $this->actingAs($admin)
        ->post(route('onboarding.diet-protocol.store'), [
            'diet_protocol' => 'balanced',
        ])
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::Birthday->value]));
});

test('customers cannot access the customer app before onboarding is complete', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create();

    $this->actingAs($customer)
        ->get(route('app.home'))
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::Gender->value]));
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

test('admin users are redirected to the portal choice screen after login', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->post(route('login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertRedirect(route('login.portal-choice', absolute: false));
});

test('customers with completed onboarding and a submitted meal plan land on welcome back after login', function () {
    $customer = User::factory()->customer()->create();
    $profile = CustomerProfile::factory()->for($customer)->create();
    $profile->craftPlans()->create([
        'craft_key' => 'full',
        'week_duration' => 5,
        'selected_weekdays' => [1, 2, 3, 4, 5],
        'submitted_at' => now(),
    ]);

    $this->post(route('login.store'), [
        'email' => $customer->email,
        'password' => 'password',
    ])->assertRedirect(route('app.home', absolute: false));
});

test('customers with completed onboarding but no meal plan are sent to crafted for you after login', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create();

    $this->post(route('login.store'), [
        'email' => $customer->email,
        'password' => 'password',
    ])->assertRedirect(route('consultation.crafted-for-you', absolute: false));
});

test('customer users are redirected to onboarding after login', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create();

    $this->post(route('login.store'), [
        'email' => $customer->email,
        'password' => 'password',
    ])->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::Gender->value], absolute: false));
});
