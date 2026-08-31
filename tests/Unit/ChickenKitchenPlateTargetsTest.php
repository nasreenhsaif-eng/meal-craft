<?php

use App\Support\ChickenKitchenPlateTargets;

test('chicken kitchen targets expose the shared protein curve and packaging constants', function () {
    expect(ChickenKitchenPlateTargets::cookedProteinGramsForTier(400))->toBe(100.0)
        ->and(ChickenKitchenPlateTargets::cookedProteinGramsForTier(500))->toBe(130.0)
        ->and(ChickenKitchenPlateTargets::cookedProteinGramsForTier(800))->toBe(225.0)
        ->and(ChickenKitchenPlateTargets::containerMl())->toBe(500.0)
        ->and(ChickenKitchenPlateTargets::platePrepOilGrams())->toBe(5.0)
        ->and(ChickenKitchenPlateTargets::saladDressingGrams())->toBe(20.0)
        ->and(ChickenKitchenPlateTargets::designedCaloriesForTier(500))->toBe(500.0);
});
