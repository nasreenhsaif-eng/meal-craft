<?php

use App\Enums\MealLibraryKey;
use App\Models\Meal;
use App\Services\BalancedMealInstructionRefiner;
use App\Support\MealInstructionsText;
use App\Support\MealLibraryEditGuard;

test('instruction refiner backfills placeholder instructions on tiers library meals', function () {
    $meal = Meal::factory()->create([
        'name' => 'Pesto Chicken Koosa Noodles',
        'library_key' => MealLibraryKey::Tiers,
        'instructions' => 'nut-free basil pesto',
        'description' => 'nut-free basil pesto',
        'library_edited_at' => now(),
    ]);

    expect(MealLibraryEditGuard::shouldSkipMealRefinement($meal))->toBeTrue()
        ->and(MealLibraryEditGuard::shouldSkipMealInstructionRefinement($meal))->toBeFalse();

    app(BalancedMealInstructionRefiner::class)->refine();

    $meal->refresh();

    expect(MealInstructionsText::needsBackfill($meal->instructions, $meal->description))->toBeFalse()
        ->and(MealInstructionsText::linesFromRaw($meal->instructions))->toContain('Spiralize zucchini into noodles (or cut thin ribbons with a peeler). Pat dry.');
});
