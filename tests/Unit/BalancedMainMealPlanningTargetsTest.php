<?php

use App\Support\BalancedMainMealPlanningTargets;

test('balanced main meal design band uses 360 kcal at 40/30/30 macros', function (): void {
    $targets = BalancedMainMealPlanningTargets::forDesignBand();

    expect($targets['target_calories'])->toBe(360.0)
        ->and($targets['target_protein'])->toBe(36.0)
        ->and($targets['target_carbs'])->toBe(27.0)
        ->and($targets['target_fat'])->toBe(12.0);
});
