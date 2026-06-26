<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Customer plan calorie tiers (kcal / day)
    |--------------------------------------------------------------------------
    */
    'plan_tiers' => [1000, 1200, 1500, 1800, 2000],

    /*
    |--------------------------------------------------------------------------
    | Production weekly meal plan (admin scheduler)
    |--------------------------------------------------------------------------
    |
    | Customers see soups (and future fixed slots) from this weekly structured
    | plan. When null, the latest weekly structured plan is used.
    |
    */
    'production_meal_plan_id' => env('CUSTOMER_PRODUCTION_MEAL_PLAN_ID'),

    /*
    |--------------------------------------------------------------------------
    | Consultation craft calorie budgets
    |--------------------------------------------------------------------------
    */
    'business_craft_calories' => 500,
    'business_side_planning_midpoint' => 175.0,

    /*
    |--------------------------------------------------------------------------
    | Slot behaviour
    |--------------------------------------------------------------------------
    |
    | scalable         — portion scales to hit slot target within the plan tier
    | fixed_portion    — standard kitchen portion; calories count toward tier
    | optional_add_on  — optional slot; when included, fixed standard portion counts within tier
    |
    */
    'slot_behaviors' => [
        'breakfast' => 'scalable',
        'main' => 'scalable',
        'side_salad' => 'fixed_portion',
        'dessert' => 'fixed_portion',
        'soup' => 'optional_add_on',
    ],

    /*
    |--------------------------------------------------------------------------
    | Slots that count toward the core plan tier when included (soup only when opted in)
    |--------------------------------------------------------------------------
    */
    'core_fixed_portion_slots' => ['side_salad', 'dessert'],

    /*
    |--------------------------------------------------------------------------
    | Menu-development calorie bands per slot (min / target / max kcal)
    |--------------------------------------------------------------------------
    */
    'slot_calorie_bands' => [
        'breakfast' => ['min' => 200.0, 'target' => 240.0, 'max' => 280.0],
        'main' => ['min' => 300.0, 'target' => 360.0, 'max' => 420.0],
        'side_salad' => ['min' => 150.0, 'target' => 175.0, 'max' => 200.0],
        'dessert' => ['min' => 140.0, 'target' => 170.0, 'max' => 200.0],
        'soup' => ['min' => 120.0, 'target' => 150.0, 'max' => 180.0],
    ],

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
    | Share of scalable budget (after core fixed portions) per slot
    |--------------------------------------------------------------------------
    |
    | Weights must sum to 1.0 across breakfast + (main_each × main count).
    |
    */
    'scalable_slot_weights' => [
        'breakfast' => 0.20,
        'main_each' => 0.40,
    ],

    /*
    |--------------------------------------------------------------------------
    | Baseline calories (library design targets when no meals exist yet)
    |--------------------------------------------------------------------------
    */
    'baseline_calories' => [
        'breakfast' => 240.0,
        'main' => 360.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Macro split presets (protein / carbs / fat as % of calories)
    |--------------------------------------------------------------------------
    */
    'macro_presets' => [
        'balanced' => [
            'protein_percentage' => 40.0,
            'carb_percentage' => 30.0,
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
            'protein_percentage' => 40.0,
            'carb_percentage' => 30.0,
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
        'thyroid' => [
            'protein_percentage' => 30.0,
            'carb_percentage' => 35.0,
            'fat_percentage' => 35.0,
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
