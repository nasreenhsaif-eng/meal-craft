<?php

use App\Enums\OnboardingStep;
use App\Models\CustomerProfile;
use App\Models\User;

test('completed customers can view their profile details', function () {
    $customer = User::factory()->customer()->create([
        'email' => 'nasreen@example.com',
    ]);
    CustomerProfile::factory()->for($customer)->create([
        'phone' => '+97333177718',
        'area' => 'Adliya',
        'block' => '318',
        'road' => '1801',
        'house_number' => '12',
        'gate_flat_number' => '4',
        'country' => 'Bahrain',
    ]);

    $this->actingAs($customer)
        ->get(route('app.profile'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('App/Profile')
            ->where('homeUrl', route('app.home'))
            ->where('profileUrl', route('app.profile'))
            ->where('shopUrl', route('app.shop'))
            ->where('mealPlanSummaryUrl', route('app.meal-plan'))
            ->where('profileEditUrl', route('onboarding.show', ['step' => OnboardingStep::Gender->value]))
            ->where('details.email', 'nasreen@example.com')
            ->where('details.phone', '+97333177718')
            ->where('details.address', 'Adliya, Bahrain')
            ->where('details.deliveryAddress', 'House 12, Gate/Flat 4, Road 1801, Block 318, Adliya, Bahrain')
            ->where('details.cards', [])
            ->has('details'));
});

test('completed customers can view the shop page', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create();

    $this->actingAs($customer)
        ->get(route('app.shop'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('App/Shop')
            ->where('shopUrl', route('app.shop'))
            ->where('homeUrl', route('app.home')));
});

test('guests are redirected away from the customer profile page', function () {
    $this->get(route('app.profile'))
        ->assertRedirect(route('login'));
});

test('customers cannot view profile before onboarding is complete', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create();

    $this->actingAs($customer)
        ->get(route('app.profile'))
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::Gender->value]));
});
