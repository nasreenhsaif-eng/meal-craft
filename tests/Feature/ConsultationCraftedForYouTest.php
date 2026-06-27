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

    expect($config['isAdminPreview'] ?? null)->toBeFalse();
});
