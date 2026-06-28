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
    | Explicit per-tier slot calorie targets (kcal)
    |--------------------------------------------------------------------------
    |
    | Full Craft day = breakfast + 2× main_each + fixed_choice_count × fixed_choice_calories.
    |
    */
    'tier_slot_calories' => [
        1000 => ['breakfast' => 200.0, 'main_each' => 250.0],
        1200 => ['breakfast' => 200.0, 'main_each' => 350.0],
        1500 => ['breakfast' => 300.0, 'main_each' => 450.0],
        1800 => ['breakfast' => 400.0, 'main_each' => 550.0],
        2000 => ['breakfast' => 450.0, 'main_each' => 625.0],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fixed pick slots — customer picks exactly 2 of 3 per day (~150 kcal each)
    |--------------------------------------------------------------------------
    */
    'fixed_choice_slots' => ['side_salad', 'dessert', 'soup'],
    'fixed_choice_count' => 2,
    'fixed_choice_calories' => 150.0,

    /*
    |--------------------------------------------------------------------------
    | Day calorie tolerance (kcal)
    |--------------------------------------------------------------------------
    |
    | Selected meals should total plan_tier ± this amount when slot targets are met.
    |
    */
    'day_calorie_tolerance' => 50.0,

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
    | Business craft — main always 350–400 kcal; side pick ~150 kcal
    |--------------------------------------------------------------------------
    */
    'business_craft' => [
        'main_min' => 350.0,
        'main_max' => 400.0,
        'main_target' => 375.0,
        'side_calories' => 150.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Fixed chia breakfast portion (kcal)
    |--------------------------------------------------------------------------
    |
    | Balanced rotation chia puddings are a standard kitchen portion — they do not
    | scale with the customer's plan tier. Mains absorb the remaining budget.
    |
    */
    'chia_breakfast_calories' => 200.0,

    /*
    |--------------------------------------------------------------------------
    | Savory egg breakfast — large eggs per plan tier
    |--------------------------------------------------------------------------
    |
    | Whole eggs scale by tier (protein-first). Non-egg sides scale in proportion to
    | the recipe egg amount so portions stay realistic (never calorie-squeezed).
    |
    */
    'savory_egg_breakfast_tier_counts' => [
        1000 => 2,
        1200 => 2,
        1500 => 4,
        1800 => 4,
        2000 => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Savory egg breakfast — minimum realistic side portions (grams)
    |--------------------------------------------------------------------------
    |
    | Applied after egg-proportional scaling so customer portions stay eatable.
    |
    */
    'savory_egg_breakfast_minimum_side_grams' => [
        'Avocado' => 50.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Slot behaviour
    |--------------------------------------------------------------------------
    |
    | scalable      — portion scales to hit slot target within the plan tier
    | fixed_portion — standard kitchen portion; calories count toward tier
    |
    */
    'slot_behaviors' => [
        'breakfast' => 'scalable',
        'main' => 'scalable',
        'side_salad' => 'fixed_portion',
        'dessert' => 'fixed_portion',
        'soup' => 'fixed_portion',
    ],

    /*
    |--------------------------------------------------------------------------
    | Slots eligible for the pick-2 fixed choice group
    |--------------------------------------------------------------------------
    */
    'core_fixed_portion_slots' => ['side_salad', 'dessert', 'soup'],

    /*
    |--------------------------------------------------------------------------
    | Menu-development calorie bands per slot (min / target / max kcal)
    |--------------------------------------------------------------------------
    */
    'slot_calorie_bands' => [
        'breakfast' => ['min' => 200.0, 'target' => 240.0, 'max' => 280.0],
        'main' => ['min' => 300.0, 'target' => 360.0, 'max' => 420.0],
        'side_salad' => ['min' => 140.0, 'target' => 150.0, 'max' => 160.0],
        'dessert' => ['min' => 140.0, 'target' => 150.0, 'max' => 160.0],
        'soup' => ['min' => 140.0, 'target' => 150.0, 'max' => 160.0],
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
