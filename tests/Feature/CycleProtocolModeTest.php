<?php

use App\Enums\CycleProtocolMode;
use App\Enums\CycleSyncPhase;
use App\Enums\OnboardingStep;
use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Support\Carbon;

test('period tracking keeps existing protocol mode while advancing to protocol selection', function () {
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

    $profile = $customer->fresh()->customerProfile;

    expect($profile?->protocol_mode)->toBe(CycleProtocolMode::NonFasting)
        ->and($profile?->period_tracking_data['protocol_mode'] ?? null)->toBe('non_fasting')
        ->and($profile?->period_tracking_data['current_phase_metadata']['fasting_window'] ?? null)
        ->toBe('Regular Meals · No Fasting')
        ->and($profile?->period_tracking_data['meal_plan_generation_payload']['craft_key'] ?? null)
        ->toBe('full');
});

test('protocol selection can opt into fasting while nurture stays no fasting', function () {
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

    $profile = $customer->fresh()->customerProfile;
    $data = $profile?->period_tracking_data ?? [];
    $nurture = collect($data['phase_catalog'] ?? [])->firstWhere('id', CycleSyncPhase::NurturePhase->value);

    expect($profile?->protocol_mode)->toBe(CycleProtocolMode::Fasting)
        ->and($data['protocol_mode'] ?? null)->toBe('fasting')
        ->and($data['current_sync_phase'] ?? null)->toBe(CycleSyncPhase::NurturePhase->value)
        ->and($data['current_phase_metadata']['fasting_type'] ?? null)->toBe('no_fasting')
        ->and($data['current_phase_metadata']['schedules_full_daily_meals'] ?? null)->toBeTrue()
        ->and($nurture['fasting_type'] ?? null)->toBe('no_fasting')
        ->and($data['meal_plan_generation_payload']['craft_key'] ?? null)->toBe('full');
});

test('updating protocol mode rebuilds phase payload without clearing logged periods', function () {
    Carbon::setTestNow(Carbon::parse('2026-04-25'));

    $customer = User::factory()->customer()->create();
    $logged = [['start' => '2026-04-20', 'end' => '2026-04-22']];

    CustomerProfile::factory()->for($customer)->create([
        'sex' => 'female',
        'diet_protocol' => 'cycle_sync',
        'logged_periods' => $logged,
        'average_cycle_length' => 30,
        'protocol_mode' => CycleProtocolMode::NonFasting,
        'period_tracking_data' => [
            'logged_periods' => $logged,
        ],
        'onboarding_completed_at' => now(),
        'onboarding_step' => OnboardingStep::Review,
    ]);

    $this->actingAs($customer)
        ->put(route('app.protocol-mode.update'), [
            'protocol_mode' => 'fasting',
        ])
        ->assertRedirect();

    $profile = $customer->fresh()->customerProfile;

    expect($profile?->logged_periods)->toBe($logged)
        ->and($profile?->protocol_mode)->toBe(CycleProtocolMode::Fasting)
        ->and($profile?->period_tracking_data['protocol_mode'] ?? null)->toBe('fasting')
        ->and($profile?->period_tracking_data['current_phase_metadata']['fasting_window'] ?? null)
        ->toBe('13–72 hours')
        ->and($profile?->period_tracking_data['meal_plan_generation_payload']['craft_key'] ?? null)
        ->toBe('intermittent');
});

test('protocol mode update rejects non cycle sync profiles', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create([
        'diet_protocol' => 'balanced',
        'onboarding_completed_at' => now(),
        'onboarding_step' => OnboardingStep::Review,
    ]);

    $this->actingAs($customer)
        ->put(route('app.protocol-mode.update'), [
            'protocol_mode' => 'fasting',
        ])
        ->assertForbidden();
});
