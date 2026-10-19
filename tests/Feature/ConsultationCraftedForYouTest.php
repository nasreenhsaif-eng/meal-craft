<?php

use App\Enums\OnboardingStep;
use App\Models\CustomerProfile;
use App\Models\User;
use App\Services\Nutrition\UserPlanCalculator;

test('guests cannot visit the consultation crafted for you page', function () {
    $this->get(route('consultation.crafted-for-you'))
        ->assertRedirect(route('login'));
});

test('authenticated customers can visit the consultation crafted for you page', function () {
    $user = User::factory()->customer()->create();
    CustomerProfile::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('consultation.crafted-for-you'))
        ->assertOk()
        ->assertSee('mc-consultation-crafted-root', false)
        ->assertSee('/app', false)
        ->assertSee('Your plan', false);
});

test('consultation page uses a relative adapted menu url so https sessions stay authenticated', function () {
    $user = User::factory()->customer()->create();
    CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 2000,
    ]);

    $response = $this->actingAs($user)
        ->get(route('consultation.crafted-for-you'))
        ->assertOk();

    preg_match(
        '/id="mc-consultation-crafted-config" type="application\/json">(.*?)<\/script>/s',
        $response->getContent(),
        $matches,
    );

    $config = json_decode($matches[1] ?? '{}', true);

    expect($config['adaptedMenuUrl'] ?? null)->toBe('/api/menu/adapted');
    expect($config['mealLibraryRevision'] ?? null)->toBeInt();
});

test('admin users can preview the customer consultation page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('consultation.crafted-for-you'))
        ->assertOk()
        ->assertSee('mc-consultation-crafted-root', false);

    preg_match(
        '/id="mc-consultation-crafted-config" type="application\/json">(.*?)<\/script>/s',
        $response->getContent(),
        $matches,
    );

    $config = json_decode($matches[1] ?? '{}', true);

    expect($config['isAdminPreview'] ?? null)->toBeTrue()
        ->and($config['planTier'] ?? null)->toBe(2000)
        ->and($config['planTiers'] ?? null)->toBe(UserPlanCalculator::planTiers());
});

test('customer consultation page does not enable admin tier preview', function () {
    $user = User::factory()->customer()->create();
    CustomerProfile::factory()->for($user)->create();

    $response = $this->actingAs($user)
        ->get(route('consultation.crafted-for-you'))
        ->assertOk();

    preg_match(
        '/id="mc-consultation-crafted-config" type="application\/json">(.*?)<\/script>/s',
        $response->getContent(),
        $matches,
    );

    $config = json_decode($matches[1] ?? '{}', true);

    expect($config['isAdminPreview'] ?? null)->toBeFalse()
        ->and($config['planTiers'] ?? null)->toBe([1250, 1500, 1800, 2000])
        ->and($config['sex'] ?? null)->toBe('female')
        ->and($config['activityLevel'] ?? null)->toBe('moderate');
});

test('consultation page sends customers without meals back to onboarding food filters', function () {
    $user = User::factory()->customer()->create();
    CustomerProfile::factory()->for($user)->create();

    $response = $this->actingAs($user)
        ->get(route('consultation.crafted-for-you', ['from' => 'onboarding']))
        ->assertOk();

    preg_match(
        '/id="mc-consultation-crafted-config" type="application\/json">(.*?)<\/script>/s',
        $response->getContent(),
        $matches,
    );

    $config = json_decode($matches[1] ?? '{}', true);
    $onboardingBack = route('onboarding.show', [
        'step' => OnboardingStep::FoodFilters->value,
    ], absolute: false);

    expect($config['backHref'] ?? null)->toBe($onboardingBack)
        ->and($config['homeHref'] ?? null)->toBe($onboardingBack);
});

test('consultation page sends customers with submitted meals back to welcome home', function () {
    $user = User::factory()->customer()->create();
    $profile = CustomerProfile::factory()->for($user)->create();
    $profile->craftPlans()->create([
        'craft_key' => 'full',
        'week_duration' => 5,
        'selected_weekdays' => [1, 2, 3, 4, 5],
        'submitted_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->get(route('consultation.crafted-for-you'))
        ->assertOk();

    preg_match(
        '/id="mc-consultation-crafted-config" type="application\/json">(.*?)<\/script>/s',
        $response->getContent(),
        $matches,
    );

    $config = json_decode($matches[1] ?? '{}', true);

    expect($config['backHref'] ?? null)->toBe(route('app.home', absolute: false))
        ->and($config['homeHref'] ?? null)->toBe(route('app.home', absolute: false));
});

test('inertia visits to consultation force a full page location redirect', function () {
    $user = User::factory()->customer()->create();
    CustomerProfile::factory()->for($user)->create();

    $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true'])
        ->get(route('consultation.crafted-for-you'))
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('consultation.crafted-for-you'));
});

test('consultation page points customers without meals back to onboarding', function () {
    $user = User::factory()->customer()->create();
    CustomerProfile::factory()->for($user)->create();

    $response = $this->actingAs($user)
        ->get(route('consultation.crafted-for-you'))
        ->assertOk();

    preg_match(
        '/id="mc-consultation-crafted-config" type="application\/json">(.*?)<\/script>/s',
        $response->getContent(),
        $matches,
    );

    $config = json_decode($matches[1] ?? '{}', true);

    expect($config['backHref'] ?? null)->toBe(route('onboarding.show', [
        'step' => OnboardingStep::FoodFilters->value,
    ], absolute: false));
});

test('admin navigation does not include consultation', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->get(route('meals.index'))
        ->assertOk()
        ->assertDontSee(route('consultation.crafted-for-you'), false);
});
