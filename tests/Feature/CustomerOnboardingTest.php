<?php

use App\Enums\OnboardingStep;
use App\Models\CustomerProfile;
use App\Models\User;
use App\Support\MealCraftInertiaSharedData;

test('legacy onboarding welcome url redirects to gender', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create();

    $this->actingAs($customer)
        ->get(route('onboarding.show', ['step' => 'welcome']))
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::Gender->value]));
});

test('female customer advances to diet protocol after gender', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::Gender,
    ]);

    $this->actingAs($customer)
        ->post(route('onboarding.gender.store'), [
            'sex' => 'female',
        ])
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::DietProtocol->value]));

    $profile = $customer->fresh()->customerProfile;

    expect($profile?->sex?->value)->toBe('female')
        ->and($customer->fresh()->currentOnboardingStep())->toBe(OnboardingStep::DietProtocol);
});

test('male customer advances to diet protocol after gender', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::Gender,
    ]);

    $this->actingAs($customer)
        ->post(route('onboarding.gender.store'), [
            'sex' => 'male',
        ])
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::DietProtocol->value]));

    expect($customer->fresh()->currentOnboardingStep())->toBe(OnboardingStep::DietProtocol);
});

test('customer can save period tracking and advance to birthday', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::PeriodTracking,
        'sex' => 'female',
        'diet_protocol' => 'cycle_sync',
    ]);

    $this->actingAs($customer)
        ->post(route('onboarding.period-tracking.store'), [
            'logged_periods' => [
                ['start' => '2026-04-20', 'end' => '2026-04-26'],
            ],
            'average_cycle_length' => 28,
        ])
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::Birthday->value]));

    $profile = $customer->fresh()->customerProfile;

    expect($profile?->logged_periods)->toBe([
        ['start' => '2026-04-20', 'end' => '2026-04-26'],
    ])->and($profile?->average_cycle_length)->toBe(28)
        ->and($profile?->period_tracking_data)->toBeArray()
        ->and($profile?->period_tracking_data['logged_periods'] ?? null)->toBe([
            ['start' => '2026-04-20', 'end' => '2026-04-26'],
        ])
        ->and($customer->fresh()->currentOnboardingStep())->toBe(OnboardingStep::Birthday);
});

test('customer period tracking stores calculated average cycle length from multiple logs', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::PeriodTracking,
        'sex' => 'female',
        'diet_protocol' => 'cycle_sync',
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

test('customer without cycle sync cannot access period tracking onboarding page', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::DietProtocol,
        'sex' => 'female',
        'diet_protocol' => 'balanced',
    ]);

    $this->actingAs($customer)
        ->get(route('onboarding.show', ['step' => OnboardingStep::PeriodTracking->value]))
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::DietProtocol->value]));
});

test('customer without cycle sync stuck on period tracking step is advanced to birthday', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::PeriodTracking,
        'sex' => 'female',
        'diet_protocol' => 'ketobiotic',
    ]);

    $this->actingAs($customer)
        ->get(route('onboarding.show', ['step' => OnboardingStep::PeriodTracking->value]))
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::Birthday->value]));

    expect($customer->fresh()->currentOnboardingStep())->toBe(OnboardingStep::Birthday);
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

test('male customer stuck on period tracking step is advanced to birthday', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::PeriodTracking,
        'sex' => 'male',
    ]);

    $this->actingAs($customer)
        ->get(route('onboarding.show', ['step' => OnboardingStep::PeriodTracking->value]))
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::Birthday->value]));

    expect($customer->fresh()->currentOnboardingStep())->toBe(OnboardingStep::Birthday);
});

test('period tracking onboarding page renders for cycle sync customers', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::PeriodTracking,
        'sex' => 'female',
        'diet_protocol' => 'cycle_sync',
    ]);

    $this->actingAs($customer)
        ->get(route('onboarding.show', ['step' => OnboardingStep::PeriodTracking->value]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Onboarding/Container')
            ->where('activeStep', OnboardingStep::PeriodTracking->value)
            ->where('mealCraft.onboarding.currentStep', OnboardingStep::PeriodTracking->value)
            ->has('mealCraft.onboarding.urls.periodTracking'));
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
            ->component('Onboarding/Container')
            ->where('activeStep', OnboardingStep::Gender->value)
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
            ->component('Onboarding/Container')
            ->where('activeStep', OnboardingStep::Birthday->value)
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
            ->component('Onboarding/Container')
            ->where('activeStep', OnboardingStep::Height->value)
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
            ->component('Onboarding/Container')
            ->where('activeStep', OnboardingStep::Weight->value)
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
            ->component('Onboarding/Container')
            ->where('activeStep', OnboardingStep::TargetWeight->value)
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
            ->component('Onboarding/Container')
            ->where('activeStep', OnboardingStep::Activity->value)
            ->has('mealCraft.onboarding.steps')
            ->has('mealCraft.onboarding.urls.activity')
            ->where('mealCraft.onboarding.currentStep', OnboardingStep::Activity->value));
});

test('customer can save activity and advance to daily targets', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::Activity,
        'sex' => 'female',
        'date_of_birth' => '1992-03-03',
        'age' => 32,
        'height_cm' => 175,
        'weight_kg' => 72,
        'target_weight_kg' => 68,
        'diet_protocol' => 'balanced',
    ]);

    $this->actingAs($customer)
        ->post(route('onboarding.activity.store'), [
            'activity_level' => 'lightly_active',
        ])
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::DailyTargets->value]));

    $profile = $customer->fresh()->customerProfile;

    expect($profile?->activity_level?->value)->toBe('lightly_active')
        ->and($profile?->protein_percentage)->toBe(40.0)
        ->and($profile?->carb_percentage)->toBe(40.0)
        ->and($profile?->fat_percentage)->toBe(20.0)
        ->and($customer->fresh()->currentOnboardingStep())->toBe(OnboardingStep::DailyTargets);
});

test('diet protocol submission calculates and persists daily targets', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::DietProtocol,
        'sex' => 'female',
        'date_of_birth' => '1992-03-03',
        'age' => 32,
        'height_cm' => 165,
        'weight_kg' => 68,
        'target_weight_kg' => 65,
        'activity_level' => 'lightly_active',
    ]);

    $this->actingAs($customer)
        ->post(route('onboarding.diet-protocol.store'), [
            'diet_protocol' => 'ketobiotic',
        ])
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::Birthday->value]));

    $profile = $customer->fresh()->customerProfile;

    expect($profile?->diet_protocol)->toBe('ketobiotic')
        ->and($profile?->daily_calorie_target)->toBeIn([1000, 1200, 1500, 1800, 2000])
        ->and($profile?->fat_percentage)->toBe(70.0)
        ->and($customer->fresh()->currentOnboardingStep())->toBe(OnboardingStep::Birthday);

    $shared = MealCraftInertiaSharedData::onboarding($customer->fresh());

    expect($shared['computedTargets']['dailyCalories'] ?? null)->toBe($profile?->daily_calorie_target)
        ->and($shared['computedTargets']['fatPercentage'] ?? null)->toBe(70.0);
});

test('cycle sync diet protocol advances to period tracking', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::DietProtocol,
        'sex' => 'female',
    ]);

    $this->actingAs($customer)
        ->post(route('onboarding.diet-protocol.store'), [
            'diet_protocol' => 'cycle_sync',
        ])
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::PeriodTracking->value]));

    expect($customer->fresh()->customerProfile?->diet_protocol)->toBe('cycle_sync')
        ->and($customer->fresh()->currentOnboardingStep())->toBe(OnboardingStep::PeriodTracking);
});

test('customer can open any onboarding tab ahead of saved progress', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create();

    $this->actingAs($customer)
        ->get(route('onboarding.show', ['step' => OnboardingStep::FoodFilters->value]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Onboarding/Container')
            ->where('activeStep', OnboardingStep::FoodFilters->value)
            ->where('mealCraft.onboarding.currentStep', OnboardingStep::Gender->value));
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
            ->component('Onboarding/Container')
            ->where('activeStep', OnboardingStep::Activity->value)
            ->has('mealCraft.onboarding.steps')
            ->has('mealCraft.onboarding.urls.activity')
            ->has('mealCraft.onboarding.options.activityLevels')
            ->has('mealCraft.onboarding.options.allergens')
            ->has('mealCraft.onboarding.options.dislikes')
            ->where('mealCraft.onboarding.currentStep', OnboardingStep::Activity->value));
});

test('completed onboarding redirects to meal selection', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::FoodFilters,
    ]);

    $this->actingAs($customer)
        ->post(route('onboarding.food-filters.store'), [
            'allergies' => ['gluten', 'dairy'],
        ])
        ->assertRedirect(route('consultation.crafted-for-you'));

    $profile = $customer->fresh()->customerProfile;

    expect($profile?->onboarding_completed_at)->not->toBeNull()
        ->and($profile?->food_filters)->toBe(['gluten', 'dairy']);

    $this->actingAs($customer)
        ->get(route('app.home'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('App/Home')
            ->where('consultationUrl', route('consultation.crafted-for-you'))
            ->where('craftPlan', null));
});

test('completed customers can reset onboarding for testing', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create([
        'onboarding_step' => OnboardingStep::FoodFilters,
    ]);

    $this->actingAs($customer)
        ->post(route('onboarding.reset'))
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::Gender->value]));

    $profile = $customer->fresh()->customerProfile;

    expect($profile?->onboarding_completed_at)->toBeNull()
        ->and($profile?->onboarding_step)->toBe(OnboardingStep::Gender);
});

test('completed customers are redirected away from onboarding', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create();

    $this->actingAs($customer)
        ->get(route('onboarding.show', ['step' => OnboardingStep::Gender->value]))
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
