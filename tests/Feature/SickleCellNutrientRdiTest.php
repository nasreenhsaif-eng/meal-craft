<?php

use App\Services\RecipeNutritionCalculator;
use App\Support\SickleCellNutrientRdi;

test('high source threshold is twenty percent of rdi', function () {
    expect(SickleCellNutrientRdi::highSourceThreshold(SickleCellNutrientRdi::RDI_FOLATE_MCG))->toBe(200.0)
        ->and(SickleCellNutrientRdi::highSourceThreshold(SickleCellNutrientRdi::RDI_B12_MCG))->toBe(0.48);
});

test('folate badge lights at twenty percent rdi per serving', function () {
    $below = SickleCellNutrientRdi::highlightBadgeLabels(['b9_folate' => 199.9]);
    $at = SickleCellNutrientRdi::highlightBadgeLabels(['b9_folate' => 200.0]);

    expect($below)->not->toContain(SickleCellNutrientRdi::BADGE_FOLATE)
        ->and($at)->toContain(SickleCellNutrientRdi::BADGE_FOLATE);
});

test('vitamin d and calcium badge requires both nutrients at high source', function () {
    $onlyD = SickleCellNutrientRdi::highlightBadgeLabels([
        'vitamin_d' => 3.0,
        'calcium' => 100.0,
    ]);
    $both = SickleCellNutrientRdi::highlightBadgeLabels([
        'vitamin_d' => 3.0,
        'calcium' => 200.0,
    ]);

    expect($onlyD)->not->toContain(SickleCellNutrientRdi::BADGE_VITAMIN_D_CALCIUM)
        ->and($both)->toContain(SickleCellNutrientRdi::BADGE_VITAMIN_D_CALCIUM);
});

test('antioxidants badge lights when any of a c or e is high source', function () {
    $vitaminC = SickleCellNutrientRdi::highlightBadgeLabels(['vitamin_c' => 18.0]);
    $low = SickleCellNutrientRdi::highlightBadgeLabels(['vitamin_c' => 10.0]);

    expect($vitaminC)->toContain(SickleCellNutrientRdi::BADGE_ANTIOXIDANTS)
        ->and($low)->not->toContain(SickleCellNutrientRdi::BADGE_ANTIOXIDANTS);
});

test('badge tooltips use positive sickle cell descriptions', function () {
    expect(SickleCellNutrientRdi::tooltipForBadge(SickleCellNutrientRdi::BADGE_FOLATE))
        ->toContain('fresh, healthy red blood cells');
});

test('recipe nutrition calculator delegates to sickle cell rdi helpers', function () {
    $nutrition = [
        'b9_folate' => SickleCellNutrientRdi::highSourceThreshold(SickleCellNutrientRdi::RDI_FOLATE_MCG),
    ];

    expect(RecipeNutritionCalculator::sickleCellProgramMealHighlight($nutrition))->toBeTrue()
        ->and(RecipeNutritionCalculator::sickleCellHighlights($nutrition)['folate'])->toBeTrue();
});
