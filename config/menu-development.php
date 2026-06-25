<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Menu master CSV paths
    |--------------------------------------------------------------------------
    |
    | Override in phpunit.xml (or tests via config()) so Feature tests that export
    | from an in-memory database do not overwrite version-controlled master files.
    |
    */

    'meals_csv_path' => env('MENU_DEVELOPMENT_MEALS_CSV_PATH'),

    'ingredients_csv_path' => env('MENU_DEVELOPMENT_INGREDIENTS_CSV_PATH'),

];
