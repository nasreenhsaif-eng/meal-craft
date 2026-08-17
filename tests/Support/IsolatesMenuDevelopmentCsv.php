<?php

namespace Tests\Support;

use App\Support\MealLibraryRefinerOverrides;
use App\Support\MenuDevelopmentCsv;

trait IsolatesMenuDevelopmentCsv
{
    protected function setUpIsolatedMenuDevelopmentCsvPaths(): void
    {
        $directory = storage_path('framework/testing/menu-csv');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        config([
            'menu-development.meals_csv_path' => $directory.'/meals.csv',
            'menu-development.ingredients_csv_path' => $directory.'/ingredients.csv',
            'menu-development.refiner_overrides_path' => $directory.'/library_refiner_overrides.php',
        ]);

        foreach ([MenuDevelopmentCsv::mealsPath(), MenuDevelopmentCsv::ingredientsPath(), MealLibraryRefinerOverrides::path()] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
