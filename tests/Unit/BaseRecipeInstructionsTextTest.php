<?php

use App\Support\BaseRecipeInstructionsText;
use Tests\TestCase;

uses(TestCase::class);

test('normalize for storage formats numbered steps with Step prefix and newline joins', function () {
    $raw = 'Finely chop the coriander. Mince the garlic. Combine oil, lime, and spices.';

    $stored = BaseRecipeInstructionsText::normalizeForStorage($raw);

    expect($stored)->toContain("Step 1:")
        ->and($stored)->toContain("\n")
        ->and($stored)->toMatch('/Step 1:.*\nStep 2:/s');
});

test('normalize for storage preserves explicit step lines from csv quoted cell', function () {
    $raw = '"Step 1: Chop herbs.\nStep 2: Whisk dressing.\nStep 3: Combine."';

    expect(BaseRecipeInstructionsText::normalizeForStorage($raw))->toBe(
        "Step 1: Chop herbs.\nStep 2: Whisk dressing.\nStep 3: Combine.",
    );
});

test('strip image fields removes photo columns from csv record', function () {
    $record = [
        'name' => 'Paste',
        'image_url' => 'http://example.com/x.jpg',
        'instructions' => 'Mix well.',
    ];

    $clean = BaseRecipeInstructionsText::stripImageFieldsFromCsvRecord($record);

    expect($clean)->not->toHaveKey('image_url')
        ->and($clean['name'])->toBe('Paste');
});
