<?php

use App\Support\MenuDevelopmentCsv;

test('absorption rice bases use dry basmati and cooked-rice calorie density', function (): void {
    $path = MenuDevelopmentCsv::ingredientsPath();
    $handle = fopen($path, 'r');
    expect($handle)->not->toBeFalse();

    $header = fgetcsv($handle);
    $index = array_flip($header);
    $rowsByName = [];

    while (($row = fgetcsv($handle)) !== false) {
        $name = trim((string) ($row[$index['name']] ?? ''));
        if ($name !== '') {
            $rowsByName[$name] = $row;
        }
    }

    fclose($handle);

    $expectations = [
        'Saffron Rice (Base)' => 'Basmati White',
        'Steamed Basmati Rice (Base)' => 'Basmati White',
        'Turmeric Rice (Base)' => 'Basmati White',
        'Brown Herbal Rice (Base)' => 'Basmati Brown',
    ];

    foreach ($expectations as $baseName => $dryGrain) {
        expect(isset($rowsByName[$baseName]))->toBeTrue("Missing {$baseName}");

        $components = (string) ($rowsByName[$baseName][$index['recipe_components']] ?? '');
        $calories = (float) ($rowsByName[$baseName][$index['calories']] ?? 0);

        expect($components)->toContain($dryGrain)
            ->and($components)->not->toContain('Basmati Rice (White)')
            ->and($calories)->toBeGreaterThan(100.0)
            ->and($calories)->toBeLessThan(140.0);
    }
});
