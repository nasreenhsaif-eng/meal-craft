<?php

use App\Enums\MealCyclePhaseTag;
use App\Services\MealCyclePhaseTaggingService;
use Tests\TestCase;

uses(TestCase::class);

test('menstrual phase requires iron over 4mg and vitamin c over 20mg', function () {
    $svc = new MealCyclePhaseTaggingService;

    $no = $svc->calculatePhaseCompatibility(
        ironMg: 4.0,
        vitaminEMg: 0,
        vitaminAMcg: 0,
        fiberG: 0,
        vitaminCMg: 25,
        b6Mg: 0,
        magnesiumMg: 0,
        zincMg: 0,
    );
    expect($no['tags'])->not->toContain(MealCyclePhaseTag::Menstrual->value);

    $yes = $svc->calculatePhaseCompatibility(
        ironMg: 4.1,
        vitaminEMg: 0,
        vitaminAMcg: 0,
        fiberG: 0,
        vitaminCMg: 20.1,
        b6Mg: 0,
        magnesiumMg: 0,
        zincMg: 0,
    );
    expect($yes['tags'])->toContain(MealCyclePhaseTag::Menstrual->value)
        ->and($yes['tooltips'])->toHaveKey(MealCyclePhaseTag::Menstrual->value);
});

test('follicular phase tags when vitamin e alone exceeds threshold', function () {
    $svc = new MealCyclePhaseTaggingService;

    $r = $svc->calculatePhaseCompatibility(
        ironMg: 0,
        vitaminEMg: 4.1,
        vitaminAMcg: 0,
        fiberG: 0,
        vitaminCMg: 0,
        b6Mg: 0,
        magnesiumMg: 0,
        zincMg: 0,
    );

    expect($r['tags'])->toContain(MealCyclePhaseTag::Follicular->value);
});

test('ovulatory phase requires fiber over 8g and b6 over 0.4mg', function () {
    $svc = new MealCyclePhaseTaggingService;

    $no = $svc->calculatePhaseCompatibility(
        ironMg: 0,
        vitaminEMg: 0,
        vitaminAMcg: 0,
        fiberG: 8.0,
        vitaminCMg: 0,
        b6Mg: 0.5,
        magnesiumMg: 0,
        zincMg: 0,
    );
    expect($no['tags'])->not->toContain(MealCyclePhaseTag::Ovulatory->value);

    $yes = $svc->calculatePhaseCompatibility(
        ironMg: 0,
        vitaminEMg: 0,
        vitaminAMcg: 0,
        fiberG: 8.1,
        vitaminCMg: 0,
        b6Mg: 0.41,
        magnesiumMg: 0,
        zincMg: 0,
    );
    expect($yes['tags'])->toContain(MealCyclePhaseTag::Ovulatory->value);
});

test('luteal phase requires magnesium over 80mg and zinc over 3mg', function () {
    $svc = new MealCyclePhaseTaggingService;

    $yes = $svc->calculatePhaseCompatibility(
        ironMg: 0,
        vitaminEMg: 0,
        vitaminAMcg: 0,
        fiberG: 0,
        vitaminCMg: 0,
        b6Mg: 0,
        magnesiumMg: 81,
        zincMg: 3.1,
    );

    expect($yes['tags'])->toContain(MealCyclePhaseTag::Luteal->value);
});
