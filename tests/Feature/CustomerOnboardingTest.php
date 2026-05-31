<?php

use App\Enums\OnboardingStep;
use App\Models\CustomerProfile;
use App\Models\User;

test('customer onboarding welcome step advances to gender', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create();

    $this->actingAs($customer)
        ->post(route('onboarding.welcome.store'))
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::Gender->value]));

    expect($customer->fresh()->currentOnboardingStep())->toBe(OnboardingStep::Gender);
});

test('female customer advances to period tracking after gender', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::Gender,
    ]);

    $this->actingAs($customer)
        ->post(route('onboarding.gender.store'), [
            'sex' => 'female',
        ])
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::PeriodTracking->value]));

    $profile = $customer->fresh()->customerProfile;

    expect($profile?->sex?->value)->toBe('female')
        ->and($customer->fresh()->currentOnboardingStep())->toBe(OnboardingStep::PeriodTracking);
});

test('male customer skips period tracking and advances to birthday', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::Gender,
    ]);

    $this->actingAs($customer)
        ->post(route('onboarding.gender.store'), [
            'sex' => 'male',
        ])
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::Birthday->value]));

    expect($customer->fresh()->currentOnboardingStep())->toBe(OnboardingStep::Birthday);
});

test('customer can save period tracking and advance to birthday', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::PeriodTracking,
        'sex' => 'female',
    ]);

    $this->actingAs($customer)
        ->post(route('onboarding.period-tracking.store'), [
            'logged_periods' => [
                ['start' => '2026-04-20', 'end' => '2026-04-26'],
            ],
            'average_cycle_length' => 28,
        ])
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::Birthday->value]));

    expect($customer->fresh()->customerProfile?->logged_periods)->toBe([
        ['start' => '2026-04-20', 'end' => '2026-04-26'],
    ])->and($customer->fresh()->customerProfile?->average_cycle_length)->toBe(28)
        ->and($customer->fresh()->currentOnboardingStep())->toBe(OnboardingStep::Birthday);
});

test('customer period tracking stores calculated average cycle length from multiple logs', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::PeriodTracking,
        'sex' => 'female',
    ]);

    $this->actingAs($customer)
        ->post(route('onboarding.period-tracking.store'), [
            'logged_periods' => [
                ['start' => '2026-01-01', 'end' => '2026-01-05'],
                ['start' => '2026-02-01', 'end' => '2026-02-05'],
                ['start' => '2026-03-03', 'end' => '2026-03-07'],
                ['start' => '2026-03-31', 'end' => '2026-04-04'],
            ],
            'average_cycle_length' => 30,
        ])
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::Birthday->value]));

    expect($customer->fresh()->customerProfile?->average_cycle_length)->toBe(30);
});

test('male customer cannot access period tracking onboarding page', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::Gender,
        'sex' => 'male',
    ]);

    $this->actingAs($customer)
        ->get(route('onboarding.show', ['step' => OnboardingStep::PeriodTracking->value]))
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::Gender->value]));
});

test('gender onboarding page renders with shared props', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::Gender,
    ]);

    $this->actingAs($customer)
        ->get(route('onboarding.show', ['step' => OnboardingStep::Gender->value]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Onboarding/Gender')
            ->has('mealCraft.onboarding.steps')
            ->has('mealCraft.onboarding.urls.gender')
            ->has('mealCraft.onboarding.options.sex')
            ->where('mealCraft.onboarding.currentStep', OnboardingStep::Gender->value));
});

test('customer can save birthday and advance to height', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::Birthday,
        'sex' => 'female',
    ]);

    $this->actingAs($customer)
        ->post(route('onboarding.birthday.store'), [
            'date_of_birth' => '1992-03-03',
        ])
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::Height->value]));

    $profile = $customer->fresh()->customerProfile;

    expect($profile?->date_of_birth?->toDateString())->toBe('1992-03-03')
        ->and($profile?->age)->toBeGreaterThanOrEqual(30)
        ->and($customer->fresh()->currentOnboardingStep())->toBe(OnboardingStep::Height);
});

test('birthday onboarding page renders with shared props', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::Birthday,
    ]);

    $this->actingAs($customer)
        ->get(route('onboarding.show', ['step' => OnboardingStep::Birthday->value]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Onboarding/Birthday')
            ->has('mealCraft.onboarding.steps')
            ->has('mealCraft.onboarding.urls.birthday')
            ->where('mealCraft.onboarding.currentStep', OnboardingStep::Birthday->value));
});

test('customer can save height and advance to weight', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::Height,
        'sex' => 'female',
        'date_of_birth' => '1992-03-03',
        'age' => 32,
    ]);

    $this->actingAs($customer)
        ->post(route('onboarding.height.store'), [
            'height_cm' => 175,
        ])
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::Weight->value]));

    $profile = $customer->fresh()->customerProfile;

    expect($profile?->height_cm)->toBe(175.0)
        ->and($customer->fresh()->currentOnboardingStep())->toBe(OnboardingStep::Weight);
});

test('height onboarding page renders with shared props', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::Height,
    ]);

    $this->actingAs($customer)
        ->get(route('onboarding.show', ['step' => OnboardingStep::Height->value]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Onboarding/Height')
            ->has('mealCraft.onboarding.steps')
            ->has('mealCraft.onboarding.urls.height')
            ->where('mealCraft.onboarding.currentStep', OnboardingStep::Height->value));
});

test('weight onboarding page renders with shared props', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::Weight,
    ]);

    $this->actingAs($customer)
        ->get(route('onboarding.show', ['step' => OnboardingStep::Weight->value]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Onboarding/Weight')
            ->has('mealCraft.onboarding.steps')
            ->has('mealCraft.onboarding.urls.weight')
            ->where('mealCraft.onboarding.currentStep', OnboardingStep::Weight->value));
});

test('customer can save weight and advance to target weight', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::Weight,
        'sex' => 'female',
        'date_of_birth' => '1992-03-03',
        'age' => 32,
        'height_cm' => 175,
    ]);

    $this->actingAs($customer)
        ->post(route('onboarding.weight.store'), [
            'weight_kg' => 72,
        ])
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::TargetWeight->value]));

    $profile = $customer->fresh()->customerProfile;

    expect($profile?->weight_kg)->toBe(72.0)
        ->and($customer->fresh()->currentOnboardingStep())->toBe(OnboardingStep::TargetWeight);
});

test('target weight onboarding page renders with shared props', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::TargetWeight,
        'weight_kg' => 72,
    ]);

    $this->actingAs($customer)
        ->get(route('onboarding.show', ['step' => OnboardingStep::TargetWeight->value]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Onboarding/TargetWeight')
            ->has('mealCraft.onboarding.steps')
            ->has('mealCraft.onboarding.urls.targetWeight')
            ->where('mealCraft.onboarding.currentStep', OnboardingStep::TargetWeight->value));
});

test('customer can save target weight and advance to activity', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::TargetWeight,
        'sex' => 'female',
        'date_of_birth' => '1992-03-03',
        'age' => 32,
        'height_cm' => 175,
        'weight_kg' => 72,
    ]);

    $this->actingAs($customer)
        ->post(route('onboarding.target-weight.store'), [
            'target_weight_kg' => 68,
        ])
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::Activity->value]));

    $profile = $customer->fresh()->customerProfile;

    expect($profile?->target_weight_kg)->toBe(68.0)
        ->and($customer->fresh()->currentOnboardingStep())->toBe(OnboardingStep::Activity);
});

test('activity onboarding page renders with shared props', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::Activity,
    ]);

    $this->actingAs($customer)
        ->get(route('onboarding.show', ['step' => OnboardingStep::Activity->value]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Onboarding/Activity')
            ->has('mealCraft.onboarding.steps')
            ->has('mealCraft.onboarding.urls.activity')
            ->where('mealCraft.onboarding.currentStep', OnboardingStep::Activity->value));
});

test('customer can save activity and advance to macros', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::Activity,
        'sex' => 'female',
        'date_of_birth' => '1992-03-03',
        'age' => 32,
        'height_cm' => 175,
        'weight_kg' => 72,
        'target_weight_kg' => 68,
    ]);

    $this->actingAs($customer)
        ->post(route('onboarding.activity.store'), [
            'activity_level' => 'moderate',
        ])
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::Macros->value]));

    $profile = $customer->fresh()->customerProfile;

    expect($profile?->activity_level?->value)->toBe('moderate')
        ->and($customer->fresh()->currentOnboardingStep())->toBe(OnboardingStep::Macros);
});

test('customer cannot skip ahead in onboarding', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create();

    $this->actingAs($customer)
        ->get(route('onboarding.show', ['step' => OnboardingStep::Review->value]))
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::Welcome->value]));
});

test('onboarding pages receive shared meal craft onboarding props', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::Activity,
    ]);

    $this->actingAs($customer)
        ->get(route('onboarding.show', ['step' => OnboardingStep::Activity->value]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Onboarding/Activity')
            ->has('mealCraft.onboarding.steps')
            ->has('mealCraft.onboarding.urls.activity')
            ->has('mealCraft.onboarding.options.activityLevels')
            ->has('mealCraft.onboarding.options.allergens')
            ->has('mealCraft.onboarding.options.dislikes')
            ->where('mealCraft.onboarding.currentStep', OnboardingStep::Activity->value));
});

test('completed onboarding unlocks the customer app home', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create([
        'onboarding_step' => OnboardingStep::Review,
    ]);

    $this->actingAs($customer)
        ->post(route('onboarding.review.store'))
        ->assertRedirect(route('app.home'));

    $profile = $customer->fresh()->customerProfile;

    expect($profile?->onboarding_completed_at)->not->toBeNull();

    $this->actingAs($customer)
        ->get(route('app.home'))
        ->assertSuccessful();
});

test('completed customers are redirected away from onboarding', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create();

    $this->actingAs($customer)
        ->get(route('onboarding.show', ['step' => OnboardingStep::Welcome->value]))
        ->assertRedirect(route('app.home'));
});

test('admin can view customer profiles list', function () {
    $admin = User::factory()->create();
    $customer = User::factory()->customer()->create(['name' => 'Listed Customer']);
    CustomerProfile::factory()->for($customer)->create();

    $this->actingAs($admin)
        ->get(route('admin.customers'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/CustomerProfiles')
            ->has('customers', 1)
            ->where('customers.0.name', 'Listed Customer'));
});
