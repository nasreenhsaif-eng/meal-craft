<?php

use App\Enums\CycleProtocolMode;
use App\Enums\CycleSyncPhase;
use App\Enums\OnboardingStep;
use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Support\Carbon;

test('next from period logging advances to protocol selection without requiring protocol mode', function () {
    Carbon::setTestNow(Carbon::parse('2026-04-22'));

    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::PeriodTracking,
        'sex' => 'female',
        'diet_protocol' => 'cycle_sync',
        'protocol_mode' => CycleProtocolMode::NonFasting,
    ]);

    $this->actingAs($customer)
        ->post(route('onboarding.period-tracking.store'), [
            'logged_periods' => [
                ['start' => '2026-04-20', 'end' => '2026-04-21'],
            ],
            'average_cycle_length' => 30,
        ])
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::ProtocolSelection->value]));

    expect($customer->fresh()->currentOnboardingStep())->toBe(OnboardingStep::ProtocolSelection)
        ->and($customer->fresh()->customerProfile?->logged_periods)->toBe([
            ['start' => '2026-04-20', 'end' => '2026-04-21'],
        ]);
});

test('protocol selection persists non fasting and renders matching active phase payload', function () {
    Carbon::setTestNow(Carbon::parse('2026-04-25'));

    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::ProtocolSelection,
        'sex' => 'female',
        'diet_protocol' => 'cycle_sync',
        'logged_periods' => [
            ['start' => '2026-04-20', 'end' => '2026-04-22'],
        ],
        'average_cycle_length' => 30,
        'protocol_mode' => CycleProtocolMode::NonFasting,
    ]);

    $this->actingAs($customer)
        ->post(route('onboarding.protocol-selection.store'), [
            'protocol_mode' => 'non_fasting',
        ])
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::ActivePhase->value]));

    $profile = $customer->fresh()->customerProfile;

    expect($profile?->protocol_mode)->toBe(CycleProtocolMode::NonFasting)
        ->and($profile?->period_tracking_data['current_phase_metadata']['fasting_window'] ?? null)
        ->toBe('Regular Meals · No Fasting')
        ->and($profile?->period_tracking_data['meal_plan_generation_payload']['craft_key'] ?? null)
        ->toBe('full');

    $this->actingAs($customer)
        ->get(route('onboarding.show', ['step' => OnboardingStep::ActivePhase->value]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Onboarding/Container')
            ->where('activeStep', OnboardingStep::ActivePhase->value)
            ->where('activePhase.protocolMode', 'non_fasting')
            ->where('activePhase.currentPhaseMetadata.fasting_window', 'Regular Meals · No Fasting'));
});

test('protocol selection fasting mode keeps nurture as no fasting', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-15'));

    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::ProtocolSelection,
        'sex' => 'female',
        'diet_protocol' => 'cycle_sync',
        'logged_periods' => [
            ['start' => '2026-04-20', 'end' => '2026-04-21'],
        ],
        'average_cycle_length' => 30,
    ]);

    $this->actingAs($customer)
        ->post(route('onboarding.protocol-selection.store'), [
            'protocol_mode' => 'fasting',
        ])
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::ActivePhase->value]));

    $data = $customer->fresh()->customerProfile?->period_tracking_data ?? [];
    $nurture = collect($data['phase_catalog'] ?? [])->firstWhere('id', CycleSyncPhase::NurturePhase->value);

    expect($customer->fresh()->customerProfile?->protocol_mode)->toBe(CycleProtocolMode::Fasting)
        ->and($data['current_sync_phase'] ?? null)->toBe(CycleSyncPhase::NurturePhase->value)
        ->and($data['current_phase_metadata']['fasting_type'] ?? null)->toBe('no_fasting')
        ->and($nurture['fasting_type'] ?? null)->toBe('no_fasting')
        ->and($data['meal_plan_generation_payload']['craft_key'] ?? null)->toBe('full');

    $this->actingAs($customer)
        ->get(route('onboarding.show', ['step' => OnboardingStep::ActivePhase->value]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('activePhase.protocolMode', 'fasting')
            ->where('activePhase.currentPhaseMetadata.fasting_type', 'no_fasting'));
});

test('active phase continue advances to daily targets', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::ActivePhase,
        'sex' => 'female',
        'diet_protocol' => 'cycle_sync',
        'logged_periods' => [
            ['start' => '2026-04-20', 'end' => '2026-04-21'],
        ],
        'average_cycle_length' => 30,
        'protocol_mode' => CycleProtocolMode::Fasting,
    ]);

    $this->actingAs($customer)
        ->post(route('onboarding.active-phase.store'))
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::DailyTargets->value]));

    expect($customer->fresh()->currentOnboardingStep())->toBe(OnboardingStep::DailyTargets);
});
