<?php

use App\Enums\CycleSyncPhase;
use App\Enums\OnboardingStep;
use App\Models\CustomerProfile;
use App\Models\User;
use App\Services\Nutrition\CycleSyncPhaseCalculator;
use Illuminate\Support\Carbon;

test('saving period tracking stores cycle day phase ranges and date colors', function () {
    Carbon::setTestNow(Carbon::parse('2026-04-22'));

    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::PeriodTracking,
        'sex' => 'female',
        'diet_protocol' => 'cycle_sync',
    ]);

    $this->actingAs($customer)
        ->post(route('onboarding.period-tracking.store'), [
            'logged_periods' => [
                ['start' => '2026-04-20', 'end' => '2026-04-21'],
            ],
            'average_cycle_length' => 30,
        ])
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::ProtocolSelection->value]));

    $data = $customer->fresh()->customerProfile?->period_tracking_data;

    expect($data['cycle_start'] ?? null)->toBe('2026-04-20')
        ->and($data['cycle_day'] ?? null)->toBe(3)
        ->and($data['current_sync_phase'] ?? null)->toBe(CycleSyncPhase::PowerPhase1->value)
        ->and($data['current_phase_metadata']['biological_phase'] ?? null)->toBe('Follicular Phase')
        ->and($data['current_phase_metadata']['sub_label'] ?? null)->toBe('Follicular · Menstruation')
        ->and($data['phase_ranges'][CycleSyncPhase::NurturePhase->value] ?? null)->toBe([
            'start' => 20,
            'end' => 30,
        ])
        ->and($data['phase_catalog'][0]['biological_phase'] ?? null)->toBe('Follicular Phase')
        ->and($data['phase_catalog'][3]['sub_label'] ?? null)->toBe('Mid–Late Luteal');

    $logged = collect($data['dates'] ?? [])->firstWhere('date', '2026-04-20');
    $phaseDay = collect($data['dates'] ?? [])->firstWhere('date', '2026-04-30');

    expect($logged['color'] ?? null)->toBe(CycleSyncPhaseCalculator::LOGGED_BLEED_COLOR)
        ->and($phaseDay['color'] ?? null)->toBe(CycleSyncPhase::Manifestation->color())
        ->and($phaseDay['logged'] ?? null)->toBeFalse();
});

test('logging a later bleed start shifts the cycle day one anchor', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-02'));

    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::PeriodTracking,
        'sex' => 'female',
        'diet_protocol' => 'cycle_sync',
    ]);

    $this->actingAs($customer)
        ->post(route('onboarding.period-tracking.store'), [
            'logged_periods' => [
                ['start' => '2026-04-01', 'end' => '2026-04-03'],
                ['start' => '2026-05-01', 'end' => '2026-05-02'],
            ],
            'average_cycle_length' => 30,
        ])
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::ProtocolSelection->value]));

    $data = $customer->fresh()->customerProfile?->period_tracking_data;

    expect($data['cycle_start'] ?? null)->toBe('2026-05-01')
        ->and($data['cycle_day'] ?? null)->toBe(2);
});
