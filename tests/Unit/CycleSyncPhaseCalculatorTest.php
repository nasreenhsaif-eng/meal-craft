<?php

use App\Enums\CycleSyncPhase;
use App\Services\Nutrition\CycleSyncPhaseCalculator;
use Illuminate\Support\Carbon;

it('uses luteal-anchored ranges for a 30 day cycle', function () {
    $ranges = CycleSyncPhaseCalculator::phaseRangesForCycleLength(30);

    expect($ranges[CycleSyncPhase::PowerPhase1->value])->toBe(['start' => 1, 'end' => 10])
        ->and($ranges[CycleSyncPhase::Manifestation->value])->toBe(['start' => 11, 'end' => 15])
        ->and($ranges[CycleSyncPhase::PowerPhase2->value])->toBe(['start' => 16, 'end' => 19])
        ->and($ranges[CycleSyncPhase::NurturePhase->value])->toBe(['start' => 20, 'end' => 30]);
});

it('scales power phase 1 for shorter and longer cycles', function (int $cycleLength, int $power1End, array $manifest, array $power2, array $nurture) {
    $ranges = CycleSyncPhaseCalculator::phaseRangesForCycleLength($cycleLength);

    expect($ranges[CycleSyncPhase::PowerPhase1->value]['end'])->toBe($power1End)
        ->and($ranges[CycleSyncPhase::Manifestation->value])->toBe($manifest)
        ->and($ranges[CycleSyncPhase::PowerPhase2->value])->toBe($power2)
        ->and($ranges[CycleSyncPhase::NurturePhase->value])->toBe($nurture);
})->with([
    '26 day cycle' => [26, 6, ['start' => 7, 'end' => 11], ['start' => 12, 'end' => 15], ['start' => 16, 'end' => 26]],
    '28 day cycle' => [28, 8, ['start' => 9, 'end' => 13], ['start' => 14, 'end' => 17], ['start' => 18, 'end' => 28]],
    '34 day cycle' => [34, 14, ['start' => 15, 'end' => 19], ['start' => 20, 'end' => 23], ['start' => 24, 'end' => 34]],
]);

it('keeps logged bleed days crimson while neighbors use phase colors', function () {
    Carbon::setTestNow(Carbon::parse('2026-04-23'));

    $payload = CycleSyncPhaseCalculator::buildPeriodTrackingData(
        [['start' => '2026-04-20', 'end' => '2026-04-22']],
        30,
    );

    $byDate = collect($payload['dates'])->keyBy('date');

    expect($byDate['2026-04-20']['logged'])->toBeTrue()
        ->and($byDate['2026-04-20']['color'])->toBe(CycleSyncPhaseCalculator::LOGGED_BLEED_COLOR)
        ->and($byDate['2026-04-20']['phase'])->toBe(CycleSyncPhase::PowerPhase1->value)
        ->and($byDate['2026-04-25']['logged'])->toBeFalse()
        ->and($byDate['2026-04-25']['color'])->toBe(CycleSyncPhase::PowerPhase1->color())
        ->and($byDate['2026-04-30']['color'])->toBe(CycleSyncPhase::Manifestation->color())
        ->and($byDate['2026-05-05']['color'])->toBe(CycleSyncPhase::PowerPhase2->color())
        ->and($byDate['2026-05-10']['color'])->toBe(CycleSyncPhase::NurturePhase->color());
});

it('resets day one when a new bleed start is logged after a gap', function () {
    $first = CycleSyncPhaseCalculator::resolveCycleStart([
        ['start' => '2026-03-01', 'end' => '2026-03-04'],
    ]);
    $second = CycleSyncPhaseCalculator::resolveCycleStart([
        ['start' => '2026-03-01', 'end' => '2026-03-04'],
        ['start' => '2026-04-01', 'end' => '2026-04-03'],
    ]);

    expect($first)->toBe('2026-03-01')
        ->and($second)->toBe('2026-04-01')
        ->and(CycleSyncPhaseCalculator::cycleDayForDate($second, '2026-04-01'))->toBe(1);
});

it('stays in nurture when cycle day exceeds cycle length', function () {
    expect(CycleSyncPhaseCalculator::phaseForCycleDay(31, 30))->toBe(CycleSyncPhase::NurturePhase)
        ->and(CycleSyncPhaseCalculator::phaseForCycleDay(40, 30))->toBe(CycleSyncPhase::NurturePhase);
});

it('defaults cycle length to 30', function () {
    expect(CycleSyncPhaseCalculator::normalizeCycleLength(null))->toBe(30);
});

it('includes biological subtitles in the phase catalog', function () {
    $catalog = CycleSyncPhaseCalculator::phaseCatalog(30);

    expect($catalog)->toHaveCount(4)
        ->and($catalog[0])->toMatchArray([
            'id' => CycleSyncPhase::PowerPhase1->value,
            'phase_name' => 'Power Phase',
            'biological_phase' => 'Follicular Phase',
            'sub_label' => 'Follicular · Menstruation',
            'fasting_window' => 'Regular Meals · No Fasting',
            'fasting_type' => 'no_fasting',
            'food_approach' => 'Ketobiotic',
            'protocol_mode' => 'non_fasting',
            'color' => '#6E8C47',
            'start' => 1,
            'end' => 10,
        ])
        ->and($catalog[1]['biological_phase'])->toBe('Ovulation Window')
        ->and($catalog[2]['biological_phase'])->toBe('Early Luteal Phase')
        ->and($catalog[3])->toMatchArray([
            'id' => CycleSyncPhase::NurturePhase->value,
            'biological_phase' => 'Mid–Late Luteal Phase',
            'sub_label' => 'Mid–Late Luteal',
            'fasting_type' => 'no_fasting',
            'food_approach' => 'Hormone Feasting',
            'color' => '#2F4C9B',
        ]);

    $fastingCatalog = CycleSyncPhaseCalculator::phaseCatalog(30, 'fasting');

    expect($fastingCatalog[0]['fasting_window'])->toBe('13–72 hours')
        ->and($fastingCatalog[0]['fasting_type'])->toBe('fasting')
        ->and($fastingCatalog[3]['fasting_type'])->toBe('no_fasting')
        ->and($fastingCatalog[3]['schedules_full_daily_meals'])->toBeTrue();
});
