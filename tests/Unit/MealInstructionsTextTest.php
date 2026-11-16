<?php

use App\Support\MealInstructionsText;

test('normalize for storage splits inline numbered steps onto separate lines', function () {
    $raw = '1. Heat oil. 2. Add carrots. 3. Pour broth and simmer.';

    expect(MealInstructionsText::normalizeForStorage($raw))->toBe(
        "Heat oil.\nAdd carrots.\nPour broth and simmer.",
    );
});

test('lines from raw preserves explicit newlines and strips step numbers', function () {
    $raw = "1. First step\n\n2. Second step\n3. Third step";

    expect(MealInstructionsText::linesFromRaw($raw))->toBe([
        'First step',
        'Second step',
        'Third step',
    ]);
});

test('lines from raw normalizes literal backslash n sequences', function () {
    expect(MealInstructionsText::linesFromRaw('1. One\\n2. Two'))->toBe([
        'One',
        'Two',
    ]);
});

test('normalize for storage returns null for blank cells', function () {
    expect(MealInstructionsText::normalizeForStorage('   '))->toBeNull();
});

test('needs backfill detects empty and placeholder instructions', function () {
    expect(MealInstructionsText::needsBackfill(null, null))->toBeTrue()
        ->and(MealInstructionsText::needsBackfill('nut-free basil pesto', null))->toBeTrue()
        ->and(MealInstructionsText::needsBackfill("1. Spiralize zucchini\n2. Pan-sear chicken", null))->toBeFalse();
});
