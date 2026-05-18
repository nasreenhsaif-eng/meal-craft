<?php

use App\Models\Ingredient;
use App\Models\Meal;
use App\Support\MenuDevelopmentCsv;
use Database\Seeders\MenuDevelopmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('menu development seeder imports ingredients and meals from database data csv files', function () {
    $ingredientsPath = MenuDevelopmentCsv::ingredientsPath();
    $mealsPath = MenuDevelopmentCsv::mealsPath();

    file_put_contents($ingredientsPath, implode(',', MenuDevelopmentCsv::INGREDIENT_HEADERS)."\n"
        ."Seeder Test Rice,Grains,,120,3,25,1,0,0,0,0,0,2,0,0,0,0,0,0,0,0,0,0,1,0,,,,,0\n");

    file_put_contents($mealsPath, '"'.implode('","', MenuDevelopmentCsv::MEAL_HEADERS)."\"\n"
        .'"Seeder Test Bowl",Meal,"Seeder Test Rice:100",,,,,,,,,,,,,,,"A test bowl.","Cook."\n');

    $this->seed(MenuDevelopmentSeeder::class);

    expect(Ingredient::query()->where('name', 'Seeder Test Rice')->exists())->toBeTrue()
        ->and(Meal::query()->where('name', 'Seeder Test Bowl')->exists())->toBeTrue();
});

test('menu development seeder skips empty csv files with only headers', function () {
    file_put_contents(
        MenuDevelopmentCsv::ingredientsPath(),
        implode(',', MenuDevelopmentCsv::INGREDIENT_HEADERS)."\n",
    );
    file_put_contents(
        MenuDevelopmentCsv::mealsPath(),
        implode(',', MenuDevelopmentCsv::MEAL_HEADERS)."\n",
    );

    $this->seed(MenuDevelopmentSeeder::class);

    expect(Ingredient::query()->count())->toBe(0)
        ->and(Meal::query()->count())->toBe(0);
});

test('menu development csv helper detects data rows', function () {
    $path = MenuDevelopmentCsv::ingredientsPath();

    file_put_contents($path, "name\n\n");
    expect(MenuDevelopmentCsv::hasDataRows($path))->toBeFalse();

    file_put_contents($path, "name\nRice,Grains\n");
    expect(MenuDevelopmentCsv::hasDataRows($path))->toBeTrue();
});

test('database seeder registers menu development seeder', function () {
    $this->seed();

    expect(Ingredient::query()->count())->toBeGreaterThanOrEqual(0);
});
