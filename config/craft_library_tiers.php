<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Craft × total-calorie → Meal Tiers Library plate tabs
    |--------------------------------------------------------------------------
    |
    | "main_each" is one authored main tab. Spreadsheet "Main meals" is the
    | combined total of all main plates (main_each × main_count).
    |
    | Desserts and sides stay one-size authored portions. include_* false means
    | that slot is omitted for the craft/tier (spreadsheet 0).
    |
    */

    'full' => [
        1250 => [
            'breakfast' => 300,
            'main_each' => 400,
            'main_count' => 2,
            'include_side_salad' => true,
            'include_dessert' => false,
            'include_soup' => false,
        ],
        1500 => [
            'breakfast' => 300,
            'main_each' => 400,
            'main_count' => 2,
            'include_side_salad' => true,
            'include_dessert' => true,
            'include_soup' => false,
        ],
        1800 => [
            'breakfast' => 400,
            'main_each' => 500,
            'main_count' => 2,
            'include_side_salad' => true,
            'include_dessert' => true,
            'include_soup' => false,
        ],
        2000 => [
            'breakfast' => 500,
            'main_each' => 600,
            'main_count' => 2,
            'include_side_salad' => true,
            'include_dessert' => true,
            'include_soup' => false,
        ],
    ],

    'afternoon' => [
        950 => [
            'breakfast' => 0,
            'main_each' => 400,
            'main_count' => 2,
            'include_side_salad' => true,
            'include_dessert' => false,
            'include_soup' => false,
        ],
        1200 => [
            'breakfast' => 0,
            'main_each' => 400,
            'main_count' => 2,
            'include_side_salad' => true,
            'include_dessert' => true,
            'include_soup' => false,
        ],
        1500 => [
            'breakfast' => 0,
            'main_each' => 550,
            'main_count' => 2,
            'include_side_salad' => true,
            'include_dessert' => true,
            'include_soup' => false,
        ],
        1800 => [
            'breakfast' => 0,
            'main_each' => 700,
            'main_count' => 2,
            'include_side_salad' => true,
            'include_dessert' => true,
            'include_soup' => false,
        ],
        2000 => [
            'breakfast' => 0,
            'main_each' => 800,
            'main_count' => 2,
            'include_side_salad' => true,
            'include_dessert' => true,
            'include_soup' => false,
        ],
    ],

    'day' => [
        1100 => [
            'breakfast' => 300,
            'main_each' => 400,
            'main_count' => 1,
            'include_side_salad' => true,
            'include_dessert' => true,
            'include_soup' => false,
        ],
        1200 => [
            'breakfast' => 400,
            'main_each' => 400,
            'main_count' => 1,
            'include_side_salad' => true,
            'include_dessert' => true,
            'include_soup' => false,
        ],
        1400 => [
            'breakfast' => 500,
            'main_each' => 500,
            'main_count' => 1,
            'include_side_salad' => true,
            'include_dessert' => true,
            'include_soup' => false,
        ],
    ],

    'business' => [
        550 => [
            'breakfast' => 0,
            'main_each' => 400,
            'main_count' => 1,
            'include_side_salad' => true,
            'include_dessert' => false,
            'include_soup' => false,
        ],
        650 => [
            'breakfast' => 0,
            'main_each' => 400,
            'main_count' => 1,
            'include_side_salad' => false,
            'include_dessert' => true,
            'include_soup' => false,
        ],
    ],

    'intermittent' => [
        950 => [
            'breakfast' => 0,
            'main_each' => 400,
            'main_count' => 1,
            'include_side_salad' => true,
            'include_dessert' => true,
            'include_soup' => true,
        ],
        1050 => [
            'breakfast' => 0,
            'main_each' => 500,
            'main_count' => 1,
            'include_side_salad' => true,
            'include_dessert' => true,
            'include_soup' => true,
        ],
        1100 => [
            'breakfast' => 0,
            'main_each' => 550,
            'main_count' => 1,
            'include_side_salad' => true,
            'include_dessert' => true,
            'include_soup' => true,
        ],
        1150 => [
            'breakfast' => 0,
            'main_each' => 600,
            'main_count' => 1,
            'include_side_salad' => true,
            'include_dessert' => true,
            'include_soup' => true,
        ],
    ],

];
