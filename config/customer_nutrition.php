<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fixed daily components (same calories for every customer)
    |--------------------------------------------------------------------------
    |
    | Soup, side salad, and dessert each use a fixed 150 kcal budget.
    |
    */
    'fixed_meal_calories' => 150.0,

    'fixed_meal_slots' => ['soup', 'side_salad', 'dessert'],

    /*
    |--------------------------------------------------------------------------
    | Scalable meal slots (Breakfast + 2× Main)
    |--------------------------------------------------------------------------
    */
    'scalable_slots' => [
        'breakfast' => 1,
        'main' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Baseline calories (library design targets when no meals exist yet)
    |--------------------------------------------------------------------------
    */
    'baseline_calories' => [
        'breakfast' => 250.0,
        'main' => 375.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Macro split presets (protein / carbs / fat as % of calories)
    |--------------------------------------------------------------------------
    */
    'macro_presets' => [
        'balanced' => [
            'protein_percentage' => 30.0,
            'carb_percentage' => 40.0,
            'fat_percentage' => 30.0,
        ],
        'high_protein' => [
            'protein_percentage' => 45.0,
            'carb_percentage' => 25.0,
            'fat_percentage' => 30.0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Diet protocol macro splits (% of calories)
    |--------------------------------------------------------------------------
    */
    'diet_protocol_macro_presets' => [
        'balanced' => [
            'protein_percentage' => 30.0,
            'carb_percentage' => 40.0,
            'fat_percentage' => 30.0,
        ],
        'ketobiotic' => [
            'protein_percentage' => 20.0,
            'carb_percentage' => 10.0,
            'fat_percentage' => 70.0,
        ],
        'cycle_sync' => [
            'protein_percentage' => 25.0,
            'carb_percentage' => 45.0,
            'fat_percentage' => 30.0,
        ],
        'sickle_cell_warrior' => [
            'protein_percentage' => 25.0,
            'carb_percentage' => 50.0,
            'fat_percentage' => 25.0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Activity multipliers (Harris–Benedict / standard TDEE factors)
    |--------------------------------------------------------------------------
    */
    'activity_multipliers' => [
        'sedentary' => 1.2,
        'light' => 1.375,
        'moderate' => 1.55,
        'active' => 1.725,
        'very_active' => 1.725,
    ],

    /*
    |--------------------------------------------------------------------------
    | Onboarding wizard activity multipliers (canonical four-step scale)
    |--------------------------------------------------------------------------
    */
    'onboarding_activity_multipliers' => [
        'sedentary' => 1.2,
        'lightly_active' => 1.375,
        'moderately_active' => 1.55,
        'very_active' => 1.725,
    ],

];
