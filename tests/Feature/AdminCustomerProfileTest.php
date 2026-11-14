<?php

use App\Enums\CustomerActivityLevel;
use App\Enums\CustomerContactPreference;
use App\Enums\CustomerPlanType;
use App\Enums\CustomerSex;
use App\Enums\CustomerSubscriptionStatus;
use App\Models\CustomerProfile;
use App\Models\CustomerSubscription;
use App\Models\User;
use App\Support\CustomerIntake;

test('admin can open a customer profile and see intake fields', function () {
    $admin = User::factory()->withAdminPermissions(['customer-profiles'])->create();
    $customer = User::factory()->customer()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);
    $profile = CustomerProfile::factory()->for($customer)->create([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'phone' => '+973 1111 2222',
        'sex' => CustomerSex::Female,
        'activity_level' => CustomerActivityLevel::LightlyActive,
        'planned_start_date' => '2026-10-01',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.customers.show', $profile))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/CustomerProfileShow')
            ->where('form.first_name', 'Ada')
            ->where('form.email', 'ada@example.com')
            ->where('form.phone', '+973 1111 2222')
            ->where('uniqueCode', $profile->unique_code)
            ->where('canEdit', true)
            ->where('subscription', null)
            ->where('listUrl', route('admin.customers')));
});

test('admin customer list is a sheet with intake fields options and subscription', function () {
    $admin = User::factory()->withAdminPermissions(['customer-profiles'])->create();
    $customer = User::factory()->customer()->create(['name' => 'Listed Customer']);
    $profile = CustomerProfile::factory()->for($customer)->create([
        'phone' => '+973 3333 4444',
        'planned_start_date' => '2026-10-12',
        'first_name' => 'Listed',
        'last_name' => 'Customer',
    ]);

    CustomerSubscription::factory()->for($profile, 'customerProfile')->create([
        'status' => CustomerSubscriptionStatus::Paid,
        'meal_plan_name' => 'Business',
        'starts_on' => '2026-10-12',
        'expires_on' => '2026-11-08',
        'delivery_days_count' => 20,
        'paid_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.customers'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/CustomerProfiles')
            ->where('canEdit', true)
            ->has('options.contactPreferences')
            ->has('customers', 1)
            ->where('customers.0.name', 'Listed Customer')
            ->where('customers.0.uniqueCode', $profile->unique_code)
            ->where('customers.0.fields.phone', '+973 3333 4444')
            ->where('customers.0.fields.planned_start_date', '2026-10-12')
            ->where('customers.0.updateUrl', route('admin.customers.update', $profile))
            ->where('customers.0.subscription.mealPlanName', 'Business')
            ->where('customers.0.subscription.status', CustomerSubscriptionStatus::Paid->value));
});

test('admin with customer-profiles permission can update a customer profile', function () {
    $admin = User::factory()->withAdminPermissions(['customer-profiles'])->create();
    $customer = User::factory()->customer()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
    ]);
    $profile = CustomerProfile::factory()->for($customer)->create([
        'first_name' => 'Old',
        'last_name' => 'Name',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.customers.show', $profile))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/CustomerProfileShow')
            ->where('canEdit', true));

    $payload = [
        ...CustomerIntake::toFormArray($profile),
        'first_name' => 'Grace',
        'last_name' => 'Hopper',
        'email' => 'grace@example.com',
        'phone' => '+973 5555 0000',
        'contact_preference' => CustomerContactPreference::Email->value,
        'gender' => CustomerSex::Female->value,
        'activity_level' => CustomerActivityLevel::Sedentary->value,
        'plan_type' => CustomerPlanType::Business->value,
        'plan_days' => '3',
        'area' => 'Juffair',
        'block' => '210',
        'road' => '40',
        'house_number' => '8',
        'gate_flat_number' => '11',
        'country' => 'Bahrain',
        'follow_instagram' => false,
        'uncalculated_plan' => true,
        'customer_question' => 'Please deliver after 7am.',
        'dislikes_and_allergies' => 'shellfish',
    ];

    $this->actingAs($admin)
        ->from(route('admin.customers'))
        ->put(route('admin.customers.update', $profile), $payload)
        ->assertRedirect(route('admin.customers'))
        ->assertSessionHas('success');

    $profile->refresh();
    $customer->refresh();

    expect($customer->name)->toBe('Grace Hopper')
        ->and($customer->email)->toBe('grace@example.com')
        ->and($profile->first_name)->toBe('Grace')
        ->and($profile->phone)->toBe('+973 5555 0000');
});

test('admin without customer-profiles permission cannot update a customer profile', function () {
    $admin = User::factory()->withAdminPermissions(['ingredient-db'])->create();
    $customer = User::factory()->customer()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
    ]);
    $profile = CustomerProfile::factory()->for($customer)->create([
        'first_name' => 'Old',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.customers.update', $profile), [
            'first_name' => 'Nope',
            'last_name' => 'Nope',
            'email' => 'nope@example.com',
        ])
        ->assertRedirect(route('admin.dashboard'))
        ->assertSessionHas('error');

    expect($profile->fresh()->first_name)->toBe('Old')
        ->and($customer->fresh()->name)->toBe('Old Name');
});

test('craft master general can update a customer profile including name and email', function () {
    $admin = User::factory()->craftMasterGeneral()->create();
    $customer = User::factory()->customer()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
    ]);
    $profile = CustomerProfile::factory()->for($customer)->create();

    $this->actingAs($admin)
        ->from(route('admin.customers'))
        ->put(route('admin.customers.update', $profile), [
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'email' => 'grace@example.com',
            'phone' => '+973 5555 0000',
            'contact_preference' => CustomerContactPreference::Email->value,
            'gender' => CustomerSex::Female->value,
            'activity_level' => CustomerActivityLevel::Sedentary->value,
            'plan_type' => CustomerPlanType::Business->value,
            'plan_days' => 3,
            'area' => 'Juffair',
            'block' => '210',
            'road' => '40',
            'house_number' => '8',
            'gate_flat_number' => '11',
            'country' => 'Bahrain',
            'follow_instagram' => false,
            'uncalculated_plan' => true,
            'customer_question' => 'Please deliver after 7am.',
            'dislikes_and_allergies' => 'shellfish',
        ])
        ->assertRedirect(route('admin.customers'))
        ->assertSessionHas('success');

    $profile->refresh();
    $customer->refresh();

    expect($customer->name)->toBe('Grace Hopper')
        ->and($customer->email)->toBe('grace@example.com')
        ->and($profile->first_name)->toBe('Grace')
        ->and($profile->phone)->toBe('+973 5555 0000')
        ->and($profile->plan_type)->toBe(CustomerPlanType::Business)
        ->and($profile->plan_days)->toBe(3)
        ->and($profile->uncalculated_plan)->toBeTrue()
        ->and($profile->allergies)->toBe(['shellfish'])
        ->and($profile->intake_submission_id)->toBeNull();
});

test('customers cannot open admin customer profile pages', function () {
    $customer = User::factory()->customer()->create();
    $profile = CustomerProfile::factory()->for($customer)->create();

    $this->actingAs($customer)
        ->get(route('admin.customers.show', $profile))
        ->assertRedirect($customer->homePath())
        ->assertSessionHas('error');

    $this->actingAs($customer)
        ->put(route('admin.customers.update', $profile), [
            'first_name' => 'Nope',
            'last_name' => 'Nope',
            'email' => $customer->email,
        ])
        ->assertRedirect($customer->homePath());
});
