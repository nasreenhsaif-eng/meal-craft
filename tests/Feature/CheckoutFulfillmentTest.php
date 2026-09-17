<?php

use App\Enums\OnboardingStep;
use App\Models\CustomerProfile;
use App\Models\User;

test('guests cannot visit the fulfillment selection page', function () {
    $this->get(route('checkout.fulfillment'))
        ->assertRedirect(route('login'));
});

test('incomplete customers are sent back to onboarding from fulfillment selection', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create();

    $this->actingAs($customer)
        ->get(route('checkout.fulfillment'))
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::Gender->value]));
});

test('completed customers can choose how to receive their meal plan', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create();

    $this->actingAs($customer)
        ->get(route('checkout.fulfillment'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Checkout/FulfillmentSelection')
            ->where('homeUrl', route('app.home'))
            ->where('deliveryUrl', route('checkout.delivery'))
            ->where('recipesUrl', route('meal-plan.recipes')));
});

test('completed customers can open the fresh delivery checkout step', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create();

    $this->actingAs($customer)
        ->get(route('checkout.delivery'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Checkout/Delivery')
            ->where('fulfillmentUrl', route('checkout.fulfillment'))
            ->where('detailsUrl', route('checkout.details'))
            ->where('homeUrl', route('app.home')));
});

test('digital recipes path opens the printable meal plan or sends the customer to consultation', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create();

    $this->actingAs($customer)
        ->get(route('meal-plan.recipes'))
        ->assertRedirect(route('consultation.crafted-for-you'));
});

test('customer home offers profile, meals plan, and summary destinations', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create();

    $this->actingAs($customer)
        ->get(route('app.home'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('App/Home')
            ->where('profileEditUrl', route('onboarding.show', ['step' => OnboardingStep::Gender->value]))
            ->where('consultationUrl', route('consultation.crafted-for-you'))
            ->where('mealPlanSummaryUrl', route('app.meal-plan'))
            ->missing('fulfillmentUrl'));
});
