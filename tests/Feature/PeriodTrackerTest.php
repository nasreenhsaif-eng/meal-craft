<?php

use App\Enums\CycleProtocolMode;
use App\Enums\CycleSyncPhase;
use App\Enums\DietProtocol;
use App\Enums\OnboardingStep;
use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Support\Carbon;

test('period tracker appears on tools only for female customers', function () {
    $female = User::factory()->customer()->create();
    CustomerProfile::factory()->for($female)->create([
        'sex' => 'female',
        'onboarding_completed_at' => now(),
        'onboarding_step' => OnboardingStep::Review,
    ]);

    $male = User::factory()->customer()->create();
    CustomerProfile::factory()->for($male)->create([
        'sex' => 'male',
        'onboarding_completed_at' => now(),
        'onboarding_step' => OnboardingStep::Review,
    ]);

    $this->actingAs($female)
        ->get(route('app.tools'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('App/MyTools')
            ->where('showPeriodTracker', true)
            ->where('periodTrackerUrl', route('app.tools.period-tracker')));

    $this->actingAs($male)
        ->get(route('app.tools'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('App/MyTools')
            ->where('showPeriodTracker', false)
            ->where('periodTrackerUrl', null));
});

test('non female customers are redirected away from period tracker', function () {
    $male = User::factory()->customer()->create();
    CustomerProfile::factory()->for($male)->create([
        'sex' => 'male',
        'onboarding_completed_at' => now(),
        'onboarding_step' => OnboardingStep::Review,
    ]);

    $this->actingAs($male)
        ->get(route('app.tools.period-tracker'))
        ->assertRedirect(route('app.tools'));
});

test('period tracker renders analysis food exercise and brain phase payload', function () {
    Carbon::setTestNow(Carbon::parse('2026-04-25'));

    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create([
        'sex' => 'female',
        'diet_protocol' => DietProtocol::CycleSync,
        'protocol_mode' => CycleProtocolMode::NonFasting,
        'logged_periods' => [
            ['start' => '2026-04-20', 'end' => '2026-04-22'],
        ],
        'average_cycle_length' => 30,
        'onboarding_completed_at' => now(),
        'onboarding_step' => OnboardingStep::Review,
    ]);

    $this->actingAs($customer)
        ->get(route('app.tools.period-tracker'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('App/PeriodTracker')
            ->where('tracker.isCycleSync', true)
            ->where('tracker.cycleDay', 6)
            ->where('tracker.currentSyncPhase', CycleSyncPhase::PowerPhase1->value)
            ->has('tracker.phaseCatalog', 4)
            ->where('tracker.phaseCatalog.0.id', CycleSyncPhase::PowerPhase1->value)
            ->where('tracker.currentPhase.foodApproach', 'Ketobiotic')
            ->where('tracker.currentPhase.exerciseFocus', fn ($value) => is_string($value) && $value !== '')
            ->where('tracker.currentPhase.brainWhatToDo', fn ($value) => is_string($value) && $value !== ''));
});

test('editing cycle for cycle sync sets meal plan effective from tomorrow', function () {
    Carbon::setTestNow(Carbon::parse('2026-04-25'));

    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create([
        'sex' => 'female',
        'diet_protocol' => DietProtocol::CycleSync,
        'protocol_mode' => CycleProtocolMode::NonFasting,
        'logged_periods' => [
            ['start' => '2026-04-10', 'end' => '2026-04-12'],
        ],
        'average_cycle_length' => 30,
        'onboarding_completed_at' => now(),
        'onboarding_step' => OnboardingStep::Review,
    ]);

    $this->actingAs($customer)
        ->put(route('app.tools.period-tracker.update'), [
            'logged_periods' => [
                ['start' => '2026-04-20', 'end' => '2026-04-22'],
            ],
            'average_cycle_length' => 28,
            'protocol_mode' => 'fasting',
        ])
        ->assertRedirect()
        ->assertSessionHas('mealPlanChangeNotice', 'Your meal plan will change starting tomorrow.');

    $profile = $customer->fresh()->customerProfile;

    expect($profile?->average_cycle_length)->toBe(28)
        ->and($profile?->protocol_mode)->toBe(CycleProtocolMode::Fasting)
        ->and($profile?->period_tracking_data['meal_plan_effective_from'] ?? null)
        ->toBe('2026-04-26');
});

test('editing cycle without cycle sync does not set meal plan effective from', function () {
    Carbon::setTestNow(Carbon::parse('2026-04-25'));

    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create([
        'sex' => 'female',
        'diet_protocol' => DietProtocol::Balanced,
        'protocol_mode' => CycleProtocolMode::NonFasting,
        'logged_periods' => [
            ['start' => '2026-04-10', 'end' => '2026-04-12'],
        ],
        'average_cycle_length' => 30,
        'onboarding_completed_at' => now(),
        'onboarding_step' => OnboardingStep::Review,
    ]);

    $this->actingAs($customer)
        ->put(route('app.tools.period-tracker.update'), [
            'logged_periods' => [
                ['start' => '2026-04-20', 'end' => '2026-04-22'],
            ],
            'average_cycle_length' => 28,
            'protocol_mode' => 'non_fasting',
        ])
        ->assertRedirect()
        ->assertSessionMissing('mealPlanChangeNotice');

    $profile = $customer->fresh()->customerProfile;

    expect($profile?->average_cycle_length)->toBe(28)
        ->and($profile?->period_tracking_data['meal_plan_effective_from'] ?? null)->toBeNull();
});
