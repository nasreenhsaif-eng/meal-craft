<?php

use App\Enums\CustomerSubscriptionStatus;
use App\Models\CustomerCraftPlan;
use App\Models\CustomerProfile;
use App\Models\CustomerSubscription;
use App\Models\User;

test('checkout make payment creates a paid customer subscription', function () {
    $customer = User::factory()->customer()->create();
    $profile = CustomerProfile::factory()->for($customer)->create([
        'planned_start_date' => '2026-12-01',
        'intake_declaration_accepted_at' => now(),
    ]);

    CustomerCraftPlan::query()->create([
        'customer_profile_id' => $profile->id,
        'craft_key' => 'full',
        'fulfillment_choice' => 'partner_kitchen',
        'week_duration' => 5,
        'plan_weeks' => 4,
        'selected_weekdays' => [1, 2, 3, 4, 5],
        'submitted_at' => now(),
    ]);

    $this->actingAs($customer)
        ->post(route('checkout.payment.store'), [
            'cart_confirmed' => true,
            'refund_policy_accepted' => true,
            'payment_method' => 'benefit_pay',
            'invoice_same_address' => true,
        ])
        ->assertRedirect(route('checkout.payment'))
        ->assertSessionHas('success');

    $subscription = CustomerSubscription::query()
        ->where('customer_profile_id', $profile->id)
        ->latest('id')
        ->first();

    expect($subscription)->not->toBeNull()
        ->and($subscription->status)->toBe(CustomerSubscriptionStatus::Paid)
        ->and($subscription->paid_at)->not->toBeNull()
        ->and($subscription->starts_on->toDateString())->toBe('2026-12-01')
        ->and($subscription->fulfillment_choice)->toBe('partner_kitchen')
        ->and($subscription->delivery_days_count)->toBeGreaterThan(0)
        ->and($subscription->meal_plan_name)->toBe('Full Craft');
});

test('admin customer profile show includes paid subscription props', function () {
    $cmg = User::factory()->craftMasterGeneral()->create();
    $customer = User::factory()->customer()->create(['name' => 'Paid Customer']);
    $profile = CustomerProfile::factory()->for($customer)->create([
        'planned_start_date' => '2026-12-01',
    ]);

    $subscription = CustomerSubscription::factory()->for($profile)->create([
        'status' => CustomerSubscriptionStatus::Paid,
        'paid_at' => now(),
        'starts_on' => '2026-12-01',
        'expires_on' => '2026-12-28',
        'delivery_days_count' => 20,
        'fulfillment_choice' => 'partner_kitchen',
        'craft_key' => 'full',
        'meal_plan_name' => 'Full Craft',
    ]);

    $this->actingAs($cmg)
        ->get(route('admin.customers.show', $profile))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/CustomerProfileShow')
            ->where('subscription.status', 'paid')
            ->where('subscription.mealPlanName', 'Full Craft')
            ->where('subscription.startDate', '2026-12-01')
            ->where('subscription.expiryDate', '2026-12-28')
            ->where('subscription.deliveries', 20)
            ->where('subscription.frozenDays', [])
            ->where('canEdit', true));

    expect($subscription->fresh()->customer_profile_id)->toBe($profile->id);
});

test('admin customer profile show has null subscription when unpaid', function () {
    $admin = User::factory()->create();
    $customer = User::factory()->customer()->create();
    $profile = CustomerProfile::factory()->for($customer)->create();

    $this->actingAs($admin)
        ->get(route('admin.customers.show', $profile))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/CustomerProfileShow')
            ->where('subscription', null));
});
