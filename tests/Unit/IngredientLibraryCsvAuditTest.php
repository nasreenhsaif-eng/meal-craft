<?php

use App\Support\MenuDevelopmentCsv;

test('master ingredients csv excludes junk and duplicate rows', function (): void {
    $path = MenuDevelopmentCsv::ingredientsPath();

    expect(MenuDevelopmentCsv::hasDataRows($path))->toBeTrue();

    $rows = array_values(array_filter(
        array_map('str_getcsv', file($path, FILE_IGNORE_NEW_LINES)),
        static fn (array $row): bool => $row !== [] && $row !== [null],
    ));

    $header = array_shift($rows);
    expect($header)->toBe(MenuDevelopmentCsv::INGREDIENT_HEADERS);

    $names = [];
    $fdcIds = [];

    foreach ($rows as $row) {
        $record = array_combine($header, array_pad($row, count($header), null));
        expect($record)->toBeArray();

        $name = trim((string) ($record['name'] ?? ''));
        expect($name)->not->toBe('');

        $names[] = $name;

        $fdcId = (int) ($record['fdc_id'] ?? 0);
        if ($fdcId > 0) {
            $fdcIds[] = $fdcId;
        }
    }

    expect($names)->not->toContain('Test Export')
        ->and($names)->not->toContain('Base Soup')
        ->and($names)->not->toContain('Rice')
        ->and($names)->not->toContain('Thai Red Curry Chicken w Roasted Pumpkin')
        ->and($names)->not->toContain('Gochugaru (Chili)')
        ->and($names)->not->toContain('coriander powder')
        ->and($names)->not->toContain('Cacao Powder')
        ->and($names)->not->toContain('Cherry Tomato')
        ->and($names)->not->toContain('Chilli Powder');

    expect(count($names))->toBe(count(array_unique($names)))
        ->and(count($fdcIds))->toBe(count(array_unique($fdcIds)));
});

test('base recipe rows in master csv declare recipe components', function (): void {
    $path = MenuDevelopmentCsv::ingredientsPath();
    $handle = fopen($path, 'r');
    expect($handle)->not->toBeFalse();

    $header = fgetcsv($handle);
    expect($header)->toBe(MenuDevelopmentCsv::INGREDIENT_HEADERS);

    while (($row = fgetcsv($handle)) !== false) {
        $record = array_combine($header, array_pad($row, count($header), null));
        $isBase = in_array(strtolower(trim((string) ($record['is_base_recipe'] ?? ''))), ['1', 'true', 'yes'], true);

        if (! $isBase) {
            continue;
        }

        expect(trim((string) ($record['recipe_components'] ?? '')))->not->toBe(
            '',
            'Base recipe '.$record['name'].' must include recipe_components.',
        );
    }

    fclose($handle);
});
