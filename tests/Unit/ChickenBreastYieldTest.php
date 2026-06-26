<?php

use App\Support\ChickenBreastYield;

test('raw chicken breast macros match USDA boneless skinless breast', function (): void {
    expect(ChickenBreastYield::RAW_PROTEIN_PER_100G)->toBe(23.0)
        ->and(ChickenBreastYield::RAW_CALORIES_PER_100G)->toBe(120.0)
        ->and(ChickenBreastYield::cookedProteinPer100g())->toBe(30.67);
});

test('raw and cooked chicken breast weights convert at standard grill yield', function (): void {
    expect(ChickenBreastYield::cookedGramsFromRaw(100))->toBe(75.0)
        ->and(ChickenBreastYield::rawGramsFromCooked(75))->toBe(100.0)
        ->and(ChickenBreastYield::rawGramsForProtein(23))->toBe(100.0);
});

test('raw portion summary reports protein conserved through cooking', function (): void {
    $summary = ChickenBreastYield::rawPortionSummary(110);

    expect($summary['raw_grams'])->toBe(110.0)
        ->and($summary['cooked_plain_grams'])->toBe(82.5)
        ->and($summary['protein_grams'])->toBe(25.3)
        ->and($summary['calories'])->toBe(132.0);
});

test('marinated chicken base finished weight includes retained marinade solids', function (): void {
    $retainedMarinade = 7 + 0.3 + 4 + 4 + 2 + 0.7 + 5;

    expect(ChickenBreastYield::estimateMarinatedFinishedWeight(100, $retainedMarinade))->toBe(98.0);
});
