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

function checkoutIntakePayload(User $customer, array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => $customer->email,
        'phone' => '+973 1234 5678',
        'contact_preference' => CustomerContactPreference::Whatsapp->value,
        'date_of_birth' => '1990-05-20',
        'gender' => CustomerSex::Female->value,
        'height_cm' => 165,
        'weight_kg' => 70,
        'target_weight_kg' => 65,
        'activity_level' => CustomerActivityLevel::LightlyActive->value,
        'delivery_time' => CustomerDeliveryTime::Morning->value,
        'dislikes_and_allergies' => 'peanuts, cilantro',
        'diet_protocol' => 'balanced',
        'plan_type' => CustomerPlanType::Full->value,
        'plan_days' => 5,
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

test('completed customers see intake details prefilled from onboarding', function () {
    $customer = User::factory()->customer()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);
    $profile = CustomerProfile::factory()->for($customer)->create([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
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
            ->where('form.height_cm', '165')
            ->where('form.weight_kg', '70')
            ->where('form.gender', 'female')
            ->where('form.activity_level', 'lightly_active')
            ->where('form.dislikes_and_allergies', 'peanuts, cilantro')
            ->where('form.plan_type', 'full')
            ->where('form.plan_days', '5')
            ->where('form.country', 'Bahrain')
            ->where('uniqueCode', $profile->fresh()->unique_code));
});

test('customers can save checkout intake details and receive a submission id', function () {
    $customer = User::factory()->customer()->create(['name' => 'Old Name']);
    $profile = CustomerProfile::factory()->for($customer)->create();

    $this->actingAs($customer)
        ->post(route('checkout.details.store'), checkoutIntakePayload($customer, [
            'email' => 'ada.saved@example.com',
        ]))
        ->assertRedirect(route('checkout.details'))
        ->assertSessionHas('success');

    $profile->refresh();
    $customer->refresh();

    expect($customer->name)->toBe('Ada Lovelace')
        ->and($customer->email)->toBe('ada.saved@example.com')
        ->and($profile->first_name)->toBe('Ada')
        ->and($profile->last_name)->toBe('Lovelace')
        ->and($profile->phone)->toBe('+973 1234 5678')
        ->and($profile->contact_preference)->toBe(CustomerContactPreference::Whatsapp)
        ->and($profile->delivery_time)->toBe(CustomerDeliveryTime::Morning)
        ->and($profile->plan_type)->toBe(CustomerPlanType::Full)
        ->and($profile->plan_days)->toBe(5)
        ->and($profile->area)->toBe('Adliya')
        ->and($profile->follow_instagram)->toBeTrue()
        ->and($profile->allergies)->toBe(['peanuts'])
        ->and($profile->dislikes)->toBe(['cilantro'])
        ->and($profile->unique_code)->toMatch('/^MC-[A-Z0-9]{8}$/')
        ->and(Str::isUuid((string) $profile->intake_submission_id))->toBeTrue();

    $submissionId = $profile->intake_submission_id;

    $this->actingAs($customer)
        ->post(route('checkout.details.store'), checkoutIntakePayload($customer, [
            'email' => 'ada.saved@example.com',
            'phone' => '+973 8888 1111',
        ]))
        ->assertRedirect(route('checkout.details'));

    expect($profile->fresh()->intake_submission_id)->toBe($submissionId)
        ->and($profile->fresh()->phone)->toBe('+973 8888 1111');
});

test('checkout intake requires a phone number', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create();

    $this->actingAs($customer)
        ->post(route('checkout.details.store'), checkoutIntakePayload($customer, [
            'phone' => null,
        ]))
        ->assertSessionHasErrors('phone');
});
