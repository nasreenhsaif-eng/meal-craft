<?php

use App\Enums\OnboardingStep;
use App\Models\CustomerProfile;
use App\Models\User;
use App\Support\PostAuthenticationRedirect;

test('post authentication redirect sends ready customers to welcome back', function () {
    $customer = User::factory()->customer()->create();
    $profile = CustomerProfile::factory()->for($customer)->create();
    $profile->craftPlans()->create([
        'craft_key' => 'full',
        'week_duration' => 5,
        'selected_weekdays' => [1, 2, 3, 4, 5],
        'submitted_at' => now(),
    ]);

    expect(PostAuthenticationRedirect::pathFor($customer->fresh()))
        ->toBe(route('app.home', absolute: false))
        ->and($customer->fresh()->shouldLandOnWelcomeBack())->toBeTrue();
});

test('post authentication redirect sends profile-complete customers without meals to crafted for you', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create();

    expect(PostAuthenticationRedirect::pathFor($customer->fresh()))
        ->toBe(route('consultation.crafted-for-you', absolute: false));
});

test('post authentication redirect sends incomplete customers to onboarding', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create();

    expect(PostAuthenticationRedirect::pathFor($customer->fresh()))
        ->toBe(route('onboarding.show', ['step' => OnboardingStep::Gender->value], absolute: false));
});
