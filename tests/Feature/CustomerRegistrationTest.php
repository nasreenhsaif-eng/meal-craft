<?php

use App\Enums\OnboardingStep;
use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyFeature(Features::registration());
});

test('join page can be rendered', function () {
    $this->get(route('join'))->assertSuccessful();
});

test('fortify register page renders the customer join form', function () {
    $this->get('/register')->assertOk();
});

test('new customers can register through join and are sent to otp verification', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Jane Customer',
        'email' => 'customer@example.com',
        'phone' => '+97333005555',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('join.verify'));

    $this->assertAuthenticated();

    $user = User::query()->where('email', 'customer@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->isCustomer())->toBeTrue()
        ->and($user->customerProfile)->not->toBeNull()
        ->and($user->customerProfile->onboarding_step)->toBe(OnboardingStep::Gender);
});

test('registration creates customer role only', function () {
    $this->post(route('register.store'), [
        'name' => 'Another Customer',
        'email' => 'another@example.com',
        'phone' => '+97333006666',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::query()->where('email', 'another@example.com')->firstOrFail();

    expect($user->isCustomer())->toBeTrue();
});
