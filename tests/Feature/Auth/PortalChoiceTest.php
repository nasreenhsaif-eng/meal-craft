<?php

use App\Enums\OnboardingStep;
use App\Enums\UserRole;
use App\Models\CustomerProfile;
use App\Models\User;

test('guests cannot access the portal choice screen', function () {
    $this->get(route('login.portal-choice'))
        ->assertRedirect(route('login'));
});

test('customers are redirected away from the portal choice screen', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create();

    $this->actingAs($customer)
        ->get(route('login.portal-choice'))
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::Gender->value], absolute: false));
});

test('admins can access the portal choice screen after login', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->get(route('login.portal-choice'))
        ->assertOk()
        ->assertSee('mc-auth-portal-choice-root', false);
});

test('admins choosing customer onboarding can preview onboarding', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->get(route('onboarding.show', ['step' => OnboardingStep::Gender->value]))
        ->assertSuccessful();
});
