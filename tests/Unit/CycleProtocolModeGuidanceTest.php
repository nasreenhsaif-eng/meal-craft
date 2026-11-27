<?php

use App\Enums\CycleProtocolMode;
use App\Enums\CycleSyncPhase;
use App\Services\Nutrition\CycleSyncPhaseCalculator;

it('defaults new users to non fasting protocol mode', function () {
    expect(CycleProtocolMode::default())->toBe(CycleProtocolMode::NonFasting)
        ->and(CycleProtocolMode::tryFromStored(null))->toBe(CycleProtocolMode::NonFasting);
});

it('resolves effective fasting for every phase under both protocol modes', function (CycleSyncPhase $phase) {
    $fasting = $phase->effectiveFastingFor(CycleProtocolMode::Fasting);
    $nonFasting = $phase->effectiveFastingFor(CycleProtocolMode::NonFasting);

    expect($nonFasting['fasting_type'])->toBe('no_fasting')
        ->and($nonFasting['fasting_window'])->toBe('Regular Meals · No Fasting')
        ->and($nonFasting['schedules_full_daily_meals'])->toBeTrue()
        ->and($nonFasting['why_description'])->not->toContain('13–72')
        ->and($nonFasting['why_description'])->not->toContain('13–15');

    if ($phase === CycleSyncPhase::NurturePhase) {
        expect($fasting['fasting_type'])->toBe('no_fasting')
            ->and($fasting['schedules_full_daily_meals'])->toBeTrue()
            ->and($fasting['fasting_window'])->toBe('Regular Meals · No Fasting');
    } else {
        expect($fasting['fasting_type'])->toBe('fasting')
            ->and($fasting['schedules_full_daily_meals'])->toBeFalse()
            ->and($fasting['fasting_window'])->toBe($phase->fastingWindow());
    }

    $payloadFasting = $phase->mealPlanGenerationPayload(CycleProtocolMode::Fasting);
    $payloadNonFasting = $phase->mealPlanGenerationPayload(CycleProtocolMode::NonFasting);

    expect($payloadNonFasting)->toMatchArray([
        'protocol_mode' => 'non_fasting',
        'phase_key' => $phase->value,
        'biological_phase' => $phase->biologicalPhase(),
        'food_approach' => $phase->foodApproach(),
        'schedules_full_daily_meals' => true,
        'craft_key' => 'full',
    ]);

    expect($payloadFasting['protocol_mode'])->toBe('fasting')
        ->and($payloadFasting['phase_key'])->toBe($phase->value)
        ->and($payloadFasting['craft_key'])->toBe(
            $phase === CycleSyncPhase::NurturePhase ? 'full' : 'intermittent',
        );
})->with([
    'power_phase_1' => CycleSyncPhase::PowerPhase1,
    'manifestation' => CycleSyncPhase::Manifestation,
    'power_phase_2' => CycleSyncPhase::PowerPhase2,
    'nurture_phase' => CycleSyncPhase::NurturePhase,
]);

it('builds period tracking payloads that respect protocol mode', function () {
    $nonFasting = CycleSyncPhaseCalculator::buildPeriodTrackingData(
        [['start' => '2026-04-01', 'end' => '2026-04-03']],
        30,
        null,
        CycleProtocolMode::NonFasting,
    );
    $fasting = CycleSyncPhaseCalculator::buildPeriodTrackingData(
        [['start' => '2026-04-01', 'end' => '2026-04-03']],
        30,
        null,
        CycleProtocolMode::Fasting,
    );

    expect($nonFasting['protocol_mode'])->toBe('non_fasting')
        ->and($nonFasting['current_phase_metadata']['fasting_type'] ?? null)->toBe('no_fasting')
        ->and($nonFasting['current_phase_metadata']['fasting_window'] ?? null)->toBe('Regular Meals · No Fasting')
        ->and($fasting['protocol_mode'])->toBe('fasting');

    $nurtureCatalogFasting = collect($fasting['phase_catalog'])->firstWhere('id', CycleSyncPhase::NurturePhase->value);

    expect($nurtureCatalogFasting['fasting_type'] ?? null)->toBe('no_fasting')
        ->and($nurtureCatalogFasting['schedules_full_daily_meals'] ?? null)->toBeTrue();
});
