<?php

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

test('consultation page sends craft duration back to welcome home for customers', function () {
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

    expect($config['backHref'] ?? null)->toBe(route('app.home', absolute: false))
        ->and($config['homeHref'] ?? null)->toBe(route('app.home'));
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

test('consultation page always points customer back to welcome home', function () {
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

    expect($config['backHref'] ?? null)->toBe(route('app.home', absolute: false));
});

test('admin navigation does not include consultation', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->get(route('meals.index'))
        ->assertOk()
        ->assertDontSee(route('consultation.crafted-for-you'), false);
});
