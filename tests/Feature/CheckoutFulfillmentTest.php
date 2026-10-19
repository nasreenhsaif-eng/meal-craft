<?php

use App\Enums\OnboardingStep;
use App\Models\CustomerProfile;
use App\Models\User;
use App\Support\MealPlanDateRange;
use Illuminate\Support\Carbon;

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
            ->where('profileUrl', route('app.profile'))
            ->where('shopUrl', route('app.shop'))
            ->where('mealPlanSummaryUrl', route('app.meal-plan'))
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
            ->where('detailsUrl', route('checkout.details'))
            ->where('homeUrl', route('app.home'))
            ->where('profileUrl', route('app.profile'))
            ->where('shopUrl', route('app.shop'))
            ->where('mealPlanSummaryUrl', route('app.meal-plan')));
});

test('digital recipes path opens the printable meal plan or sends the customer to consultation', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create();

    $this->actingAs($customer)
        ->get(route('meal-plan.recipes'))
        ->assertRedirect(route('consultation.crafted-for-you'));
});

test('customer home without submitted meals redirects to crafted for you', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create();

    $this->actingAs($customer)
        ->get(route('app.home'))
        ->assertRedirect(route('consultation.crafted-for-you'));
});

test('customer home offers profile, meals plan, and summary destinations', function () {
    $customer = User::factory()->customer()->create();
    $profile = CustomerProfile::factory()->for($customer)->create();
    $profile->craftPlans()->create([
        'craft_key' => 'full',
        'week_duration' => 5,
        'selected_weekdays' => [1, 2, 3, 4, 5],
        'submitted_at' => now(),
    ]);

    $chooseByFriday = now()->startOfDay();
    if (! $chooseByFriday->isFriday()) {
        $chooseByFriday = $chooseByFriday->next(Carbon::FRIDAY);
    }

    $this->actingAs($customer)
        ->get(route('app.home'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('App/Home')
            ->where('homeUrl', route('app.home'))
            ->where('profileUrl', route('app.profile'))
            ->where('shopUrl', route('app.shop'))
            ->where('profileEditUrl', route('onboarding.show', ['step' => OnboardingStep::Gender->value]))
            ->where('consultationUrl', route('consultation.crafted-for-you'))
            ->where('consultationEditUrl', route('consultation.crafted-for-you.edit'))
            ->where('mealPlanSummaryUrl', route('app.meal-plan'))
            ->where('fulfillmentUrl', route('checkout.fulfillment'))
            ->where('chooseByFridayLabel', MealPlanDateRange::dayLabel($chooseByFriday))
            ->where('showPlanReady', false));
});
