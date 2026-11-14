<?php

use App\Enums\OnboardingStep;
use App\Models\CustomerCraftPlan;
use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Support\Carbon;

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
            ->where('contactEditUrl', route('checkout.details'))
            ->where('logoutUrl', route('logout'))
            ->where('details.email', 'nasreen@example.com')
            ->where('details.phone', '+97333177718')
            ->where('details.address', 'Adliya, Bahrain')
            ->where('details.deliveryAddress', 'House 12, Gate/Flat 4, Road 1801, Block 318, Adliya, Bahrain')
            ->where('details.cards', [])
            ->where('subscription', null)
            ->missing('details.heightCm')
            ->missing('profileEditUrl')
            ->has('details'));
});

test('completed customers see subscription details from their latest submitted craft plan', function () {
    $startDate = Carbon::parse('2026-11-15')->startOfDay();

    $customer = User::factory()->customer()->create();
    $profile = CustomerProfile::factory()->for($customer)->create([
        'planned_start_date' => $startDate->toDateString(),
    ]);

    CustomerCraftPlan::query()->create([
        'customer_profile_id' => $profile->id,
        'craft_key' => 'full',
        'fulfillment_choice' => 'partner_kitchen',
        'week_duration' => 7,
        'plan_weeks' => 4,
        'selected_weekdays' => [1, 2, 3, 4, 5, 6, 7],
        'submitted_at' => now(),
    ]);

    $this->actingAs($customer)
        ->get(route('app.profile'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('App/Profile')
            ->where('subscription.mealPlanName', 'Full Craft')
            ->where('subscription.days', 28)
            ->where('subscription.startDate', '2026-11-15')
            ->where('subscription.expiryDate', '2026-12-12')
            ->where('subscription.deliveries', 28));
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
