<?php

use App\Models\Meal;
use App\Services\MenuDevelopmentCsvSync;
use App\Support\MenuDevelopmentCsv;
use Tests\Support\IsolatesMenuDevelopmentCsv;

uses(IsolatesMenuDevelopmentCsv::class);

beforeEach(function (): void {
    $this->setUpIsolatedMenuDevelopmentCsvPaths();
});

test('sync meals refuses to overwrite a fuller master csv from a sparse library', function (): void {
    $path = MenuDevelopmentCsv::mealsPath();
    $headers = implode(',', MenuDevelopmentCsv::MEAL_HEADERS);
    $rows = [$headers];

    for ($i = 1; $i <= 20; $i++) {
        $rows[] = '"Sparse Guard Meal '.$i.'",Meal,"Chicken Breast:150",350,35,35,7.8,,,,,false,,,,,,,';
    }

    file_put_contents($path, implode("\n", $rows)."\n");

    Meal::factory()->create(['name' => 'Only One Library Meal']);

    expect(fn () => app(MenuDevelopmentCsvSync::class)->syncMealsFromDatabase())
        ->toThrow(RuntimeException::class, 'Refusing to sync meals.csv');
});

test('sync meals allows export when library is at least half the csv size', function (): void {
    $path = MenuDevelopmentCsv::mealsPath();
    $headers = implode(',', MenuDevelopmentCsv::MEAL_HEADERS);
    $rows = [$headers];

    for ($i = 1; $i <= 4; $i++) {
        $rows[] = '"Half Guard Meal '.$i.'",Meal,"Chicken Breast:150",350,35,35,7.8,,,,,false,,,,,,,';
    }

    file_put_contents($path, implode("\n", $rows)."\n");

    foreach (range(1, 2) as $i) {
        Meal::factory()->create(['name' => 'Half Guard Library Meal '.$i]);
    }

    expect(app(MenuDevelopmentCsvSync::class)->syncMealsFromDatabase())->toBe(2)
        ->and(file_get_contents($path))->toContain('Half Guard Library Meal 1')
        ->and(file_get_contents($path))->not->toContain('Half Guard Meal 1');
});
