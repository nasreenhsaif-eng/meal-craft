<?php

use App\Enums\CustomerDeliveryTime;
use App\Enums\DietProtocol;
use App\Enums\OnboardingStep;
use App\Models\CustomerCraftPlan;
use App\Models\CustomerProfile;
use App\Models\User;
use App\Support\CheckoutBasket;
use Database\Seeders\PartnerKitchenPriceSeeder;

/**
 * @return array<string, mixed>
 */
function paymentCheckoutConfirmPayload(array $overrides = []): array
{
    return array_merge([
        'declaration_accepted' => true,
        'phone' => '+97312345678',
        'contact_preference' => 'whatsapp',
        'delivery_time' => CustomerDeliveryTime::Morning->value,
        'area' => 'Adliya',
        'block' => '338',
        'road' => '1234',
        'house_number' => '12',
        'gate_flat_number' => '2',
        'country' => 'Bahrain',
        'planned_start_date' => now()->addWeek()->toDateString(),
    ], $overrides);
}

test('guests cannot visit checkout payment', function () {
    $this->get(route('checkout.payment'))
        ->assertRedirect(route('login'));
});

test('customers without confirmed details are sent back to details', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create([
        'intake_declaration_accepted_at' => null,
    ]);

    $this->actingAs($customer)
        ->get(route('checkout.payment'))
        ->assertRedirect(route('checkout.details'));
});

test('confirming details redirects to payment checkout', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create([
        'phone' => null,
        'diet_protocol' => DietProtocol::Balanced->value,
        'daily_calorie_target' => 1500,
        'area' => null,
    ]);

    $this->actingAs($customer)
        ->post(route('checkout.details.store'), paymentCheckoutConfirmPayload())
        ->assertRedirect(route('checkout.payment'));
});

test('completed customers see basket summary on payment checkout', function () {
    $this->seed(PartnerKitchenPriceSeeder::class);

    $customer = User::factory()->customer()->create();
    $profile = CustomerProfile::factory()->for($customer)->create([
        'diet_protocol' => DietProtocol::Balanced->value,
        'daily_calorie_target' => 2000,
        'area' => 'Adliya',
        'block' => '338',
        'road' => '1234',
        'house_number' => '12',
        'country' => 'Bahrain',
        'delivery_time' => CustomerDeliveryTime::Morning,
        'planned_start_date' => now()->addWeek()->toDateString(),
        'intake_declaration_accepted_at' => now(),
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
        ->get(route('checkout.payment'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Checkout/Payment')
            ->where('customerName', $customer->name)
            ->where('basket.deliveryAddress.area', 'Adliya')
            ->where('basket.benefitPayNumber', '33177718')
            ->where('basket.currency', 'BHD')
            ->where('basket.subtotal', 315)
            ->where('basket.deliveryCost', 5)
            ->where('detailsUrl', route('checkout.details'))
            ->where('submitUrl', route('checkout.payment.store'))
            ->etc());

    expect($profile->fresh()->intake_declaration_accepted_at)->not->toBeNull();
});

test('completed customers can view freeze and refund policies', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create([
        'intake_declaration_accepted_at' => now(),
    ]);

    $this->actingAs($customer)
        ->get(route('policies.refund'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Checkout/Policies')
            ->where('paymentUrl', route('checkout.payment'))
            ->where('homeUrl', route('app.home'))
            ->etc());
});

test('payment requires cart confirmation', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create([
        'intake_declaration_accepted_at' => now(),
    ]);

    $this->actingAs($customer)
        ->post(route('checkout.payment.store'), [
            'cart_confirmed' => false,
            'refund_policy_accepted' => true,
            'payment_method' => 'benefit_pay',
            'invoice_same_address' => true,
        ])
        ->assertSessionHasErrors('cart_confirmed');
});

test('payment requires refund policy acceptance', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create([
        'intake_declaration_accepted_at' => now(),
    ]);

    $this->actingAs($customer)
        ->post(route('checkout.payment.store'), [
            'cart_confirmed' => true,
            'refund_policy_accepted' => false,
            'payment_method' => 'benefit_pay',
            'invoice_same_address' => true,
        ])
        ->assertSessionHasErrors('refund_policy_accepted');
});

test('customers can submit benefit pay checkout', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create([
        'intake_declaration_accepted_at' => now(),
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
});

test('partner kitchen payment resumes consultation choose meals', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create([
        'intake_declaration_accepted_at' => now(),
    ]);

    $this->actingAs($customer)
        ->withSession(['checkout_resume_choose_meals' => true])
        ->post(route('checkout.payment.store'), [
            'cart_confirmed' => true,
            'refund_policy_accepted' => true,
            'payment_method' => 'benefit_pay',
            'invoice_same_address' => true,
        ])
        ->assertRedirect(route('consultation.crafted-for-you', ['choose_meals' => 1]))
        ->assertSessionHas('success')
        ->assertSessionMissing('checkout_resume_choose_meals');
});

test('checkout basket averages day calories from summary categories', function () {
    $average = CheckoutBasket::averageDayCaloriesFromSummary([
        'planTierCalories' => 1500,
        'days' => [
            [
                'categories' => [
                    'breakfasts' => [['calories' => 400]],
                    'meals' => [['calories' => 500]],
                    'sideSalads' => [['calories' => 200]],
                    'desserts' => [['calories' => 100]],
                    'soup' => [],
                ],
            ],
            [
                'categories' => [
                    'breakfasts' => [['calories' => 300]],
                    'meals' => [['calories' => 500]],
                    'sideSalads' => [['calories' => 200]],
                    'desserts' => [['calories' => 100]],
                    'soup' => [],
                ],
            ],
        ],
    ]);

    expect($average)->toBe(1150);
});

test('incomplete customers cannot open payment checkout', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::Gender,
    ]);

    $this->actingAs($customer)
        ->get(route('checkout.payment'))
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::Gender->value]));
});
