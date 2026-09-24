<?php

use App\Enums\CustomerActivityLevel;
use App\Enums\CustomerContactPreference;
use App\Enums\CustomerDeliveryTime;
use App\Enums\CustomerPlanType;
use App\Enums\CustomerSex;
use App\Enums\OnboardingStep;
use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * @return array<string, mixed>
 */
function checkoutConfirmPayload(array $overrides = []): array
{
    return array_merge([
        'declaration_accepted' => true,
        'phone' => '+973 1234 5678',
        'contact_preference' => CustomerContactPreference::Whatsapp->value,
        'delivery_time' => CustomerDeliveryTime::Morning->value,
        'area' => 'Adliya',
        'block' => '338',
        'road' => '1234',
        'house_number' => '12',
        'gate_flat_number' => '2',
        'country' => 'Bahrain',
        'planned_start_date' => now()->addWeek()->toDateString(),
        'follow_instagram' => true,
        'customer_question' => 'Can I skip Fridays?',
        'uncalculated_plan' => false,
    ], $overrides);
}

test('guests cannot visit checkout details', function () {
    $this->get(route('checkout.details'))
        ->assertRedirect(route('login'));
});

test('incomplete customers are sent back to onboarding from checkout details', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create();

    $this->actingAs($customer)
        ->get(route('checkout.details'))
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::Gender->value]));
});

test('completed customers see onboarding details for review on checkout', function () {
    $customer = User::factory()->customer()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);
    $profile = CustomerProfile::factory()->for($customer)->create([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'phone' => '+973 1111 2222',
        'contact_preference' => CustomerContactPreference::Email,
        'height_cm' => 165,
        'weight_kg' => 70,
        'target_weight_kg' => 65,
        'date_of_birth' => '1990-05-20',
        'sex' => CustomerSex::Female,
        'activity_level' => CustomerActivityLevel::LightlyActive,
        'diet_protocol' => 'balanced',
        'allergies' => ['peanuts'],
        'dislikes' => ['cilantro'],
    ]);
    $profile->craftPlans()->create([
        'craft_key' => 'full',
        'week_duration' => 5,
        'selected_weekdays' => [0, 1, 2, 3, 4],
    ]);

    $this->actingAs($customer)
        ->get(route('checkout.details'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Checkout/CustomerDetails')
            ->where('form.first_name', 'Ada')
            ->where('form.last_name', 'Lovelace')
            ->where('form.email', 'ada@example.com')
            ->where('form.phone', '+973 1111 2222')
            ->where('form.contact_preference', 'email')
            ->where('form.height_cm', '165')
            ->where('form.weight_kg', '70')
            ->where('form.gender', 'female')
            ->where('form.activity_level', 'lightly_active')
            ->where('form.dislikes_and_allergies', 'peanuts, cilantro')
            ->where('form.plan_type', 'full')
            ->where('form.plan_days', '5')
            ->where('form.country', 'Bahrain')
            ->where('declarationAccepted', false)
            ->missing('phoneEditable')
            ->where('uniqueCode', $profile->fresh()->unique_code));
});

test('checkout confirmation requires the declaration checkbox', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create([
        'phone' => '+973 1234 5678',
    ]);

    $this->actingAs($customer)
        ->post(route('checkout.details.store'), checkoutConfirmPayload([
            'declaration_accepted' => false,
        ]))
        ->assertSessionHasErrors('declaration_accepted');
});

test('customers can confirm checkout details without overwriting onboarding body fields', function () {
    $customer = User::factory()->customer()->create(['name' => 'Ada Lovelace']);
    $profile = CustomerProfile::factory()->for($customer)->create([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'phone' => null,
        'height_cm' => 165,
        'weight_kg' => 70,
        'plan_type' => CustomerPlanType::Full,
        'plan_days' => 5,
        'allergies' => ['peanuts'],
        'dislikes' => ['cilantro'],
    ]);

    $this->actingAs($customer)
        ->post(route('checkout.details.store'), checkoutConfirmPayload([
            'first_name' => 'Hacker',
            'last_name' => 'Name',
            'height_cm' => 999,
            'weight_kg' => 12,
            'plan_type' => CustomerPlanType::Day->value,
            'plan_days' => 3,
            'dislikes_and_allergies' => 'should-not-apply',
        ]))
        ->assertRedirect(route('checkout.payment'))
        ->assertSessionHas('success');

    $profile->refresh();

    expect($profile->first_name)->toBe('Ada')
        ->and($profile->last_name)->toBe('Lovelace')
        ->and($profile->height_cm)->toBe(165.0)
        ->and($profile->weight_kg)->toBe(70.0)
        ->and($profile->plan_type)->toBe(CustomerPlanType::Full)
        ->and($profile->plan_days)->toBe(5)
        ->and($profile->allergies)->toBe(['peanuts'])
        ->and($profile->dislikes)->toBe(['cilantro'])
        ->and($profile->phone)->toBe('+97312345678')
        ->and($profile->contact_preference)->toBe(CustomerContactPreference::Whatsapp)
        ->and($profile->delivery_time)->toBe(CustomerDeliveryTime::Morning)
        ->and($profile->area)->toBe('Adliya')
        ->and($profile->follow_instagram)->toBeTrue()
        ->and($profile->intake_declaration_accepted_at)->not->toBeNull()
        ->and($profile->unique_code)->toMatch('/^MC-[A-Z0-9]{8}$/')
        ->and(Str::isUuid((string) $profile->intake_submission_id))->toBeTrue();

    $submissionId = $profile->intake_submission_id;
    $declaredAt = $profile->intake_declaration_accepted_at;

    $this->actingAs($customer)
        ->post(route('checkout.details.store'), checkoutConfirmPayload([
            'phone' => '+973 8888 1111',
            'area' => 'Juffair',
        ]))
        ->assertRedirect(route('checkout.payment'));

    $profile->refresh();

    expect($profile->intake_submission_id)->toBe($submissionId)
        ->and($profile->phone)->toBe('+97388881111')
        ->and($profile->area)->toBe('Juffair')
        ->and($profile->intake_declaration_accepted_at)->not->toBeNull()
        ->and($profile->intake_declaration_accepted_at->greaterThanOrEqualTo($declaredAt))->toBeTrue();
});

test('checkout confirmation requires a phone number when profile has none', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create([
        'phone' => null,
    ]);

    $this->actingAs($customer)
        ->post(route('checkout.details.store'), checkoutConfirmPayload([
            'phone' => null,
        ]))
        ->assertSessionHasErrors('phone');
});

test('checkout confirmation requires a contact preference', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create([
        'phone' => '+973 1234 5678',
        'contact_preference' => null,
    ]);

    $this->actingAs($customer)
        ->post(route('checkout.details.store'), checkoutConfirmPayload([
            'contact_preference' => null,
        ]))
        ->assertSessionHasErrors('contact_preference');
});

test('checkout confirmation requires a phone number even when profile already has one', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create([
        'phone' => '+973 1111 2222',
        'contact_preference' => CustomerContactPreference::Email,
    ]);

    $this->actingAs($customer)
        ->post(route('checkout.details.store'), checkoutConfirmPayload([
            'phone' => null,
        ]))
        ->assertSessionHasErrors('phone');
});

test('checkout confirmation requires delivery address fields', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create([
        'phone' => '+973 1234 5678',
        'contact_preference' => CustomerContactPreference::Whatsapp,
    ]);

    $this->actingAs($customer)
        ->post(route('checkout.details.store'), checkoutConfirmPayload([
            'delivery_time' => null,
            'planned_start_date' => null,
            'area' => null,
            'block' => null,
            'road' => null,
            'house_number' => null,
            'country' => null,
        ]))
        ->assertSessionHasErrors([
            'delivery_time',
            'planned_start_date',
            'area',
            'block',
            'road',
            'house_number',
            'country',
        ]);
});
