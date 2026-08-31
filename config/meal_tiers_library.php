<?php

return [

    'main_calorie_tiers' => [400, 500, 550, 600, 700, 800],

    // Savory egg breakfasts only (chia puddings stay one size).
    'breakfast_calorie_tiers' => [300, 400, 500],

    'savory_egg_counts' => [
        300 => 2,
        400 => 3,
        500 => 4,
    ],

    /*
    |--------------------------------------------------------------------------
    | Shared primary-protein grams (fish / beef / legacy non-chicken paths)
    |--------------------------------------------------------------------------
    */
    'protein_grams' => [
        400 => 120.0,
        500 => 155.0,
        550 => 165.0,
        600 => 180.0,
        700 => 210.0,
        800 => 240.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Chicken kitchen pack-out (plates + salads, same 500 ml cup)
    |--------------------------------------------------------------------------
    */
    'chicken_kitchen' => [
        'container_ml' => 500.0,
        'plate_prep_oil_grams' => 5.0,
        'salad_dressing_ml' => 20.0,
        'salad_dressing_grams' => 20.0,
        'nut_seed_cap_grams' => 15.0,
        'calorie_tolerance' => 25.0,
        // Cooked plated grams. Raw breast/thigh store rawGramsFromCooked of these targets.
        'cooked_protein_grams' => [
            400 => 100.0,
            500 => 130.0,
            550 => 145.0,
            600 => 165.0,
            700 => 195.0,
            800 => 225.0,
        ],
        // Approximate packed volume (ml per gram) by density band.
        'ml_per_gram' => [
            'protein' => 1.05,
            'leafy' => 6.0,
            'dense_veg' => 1.15,
            'grain' => 1.35,
            'nut_seed' => 1.4,
            'default_extra' => 2.0,
            'oil' => 1.09,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Calorie buckets per family and tab (protein / carbs+veg / sauce fat / seasoning)
    |--------------------------------------------------------------------------
    */
    'families' => [
        'chicken' => [
            400 => ['protein' => 160, 'carbs_veg' => 160, 'sauce_fat' => 80, 'seasoning' => 5, 'designed_calories' => 405],
            500 => ['protein' => 200, 'carbs_veg' => 200, 'sauce_fat' => 100, 'seasoning' => 6, 'designed_calories' => 500],
            550 => ['protein' => 220, 'carbs_veg' => 220, 'sauce_fat' => 110, 'seasoning' => 7, 'designed_calories' => 557],
            600 => ['protein' => 240, 'carbs_veg' => 240, 'sauce_fat' => 120, 'seasoning' => 8, 'designed_calories' => 608],
            700 => ['protein' => 280, 'carbs_veg' => 280, 'sauce_fat' => 140, 'seasoning' => 9, 'designed_calories' => 709],
            800 => ['protein' => 320, 'carbs_veg' => 320, 'sauce_fat' => 160, 'seasoning' => 10, 'designed_calories' => 810],
        ],
        'fish' => [
            400 => ['protein' => 250, 'carbs_veg' => 100, 'sauce_fat' => 50, 'seasoning' => 5, 'designed_calories' => 405],
            500 => ['protein' => 313, 'carbs_veg' => 125, 'sauce_fat' => 63, 'seasoning' => 6, 'designed_calories' => 506],
            550 => ['protein' => 344, 'carbs_veg' => 138, 'sauce_fat' => 69, 'seasoning' => 7, 'designed_calories' => 557],
            600 => ['protein' => 375, 'carbs_veg' => 150, 'sauce_fat' => 75, 'seasoning' => 8, 'designed_calories' => 608],
            700 => ['protein' => 438, 'carbs_veg' => 175, 'sauce_fat' => 88, 'seasoning' => 9, 'designed_calories' => 709],
            800 => ['protein' => 500, 'carbs_veg' => 200, 'sauce_fat' => 100, 'seasoning' => 10, 'designed_calories' => 810],
        ],
        'beef' => [
            400 => ['protein' => 300, 'carbs_veg' => 50, 'sauce_fat' => 50, 'seasoning' => 5, 'designed_calories' => 405],
            500 => ['protein' => 375, 'carbs_veg' => 63, 'sauce_fat' => 63, 'seasoning' => 6, 'designed_calories' => 506],
            550 => ['protein' => 413, 'carbs_veg' => 69, 'sauce_fat' => 69, 'seasoning' => 7, 'designed_calories' => 557],
            600 => ['protein' => 450, 'carbs_veg' => 75, 'sauce_fat' => 75, 'seasoning' => 8, 'designed_calories' => 608],
            700 => ['protein' => 525, 'carbs_veg' => 88, 'sauce_fat' => 88, 'seasoning' => 9, 'designed_calories' => 709],
            800 => ['protein' => 600, 'carbs_veg' => 100, 'sauce_fat' => 100, 'seasoning' => 10, 'designed_calories' => 810],
        ],
    ],

];
