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
    | Breakfast is tier-fixed: it only changes with the plan tier (1000/1200/…/2000),
    | never when the customer picks a heavier/lighter side, dessert, or soup.
    | Fixed-pick overshoot/undershoot vs the 2×150 budget is absorbed by mains only.
    |
    | Main scaling levers: protein + starchy carbs. Cooking fat (olive oil / butter)
    | and vegetables are kitchen floors and are not trimmed. Herbs/spices follow the
    | whole-dish scale (protein × carb geometric mean).
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
    | Main meal macro split (% of each main slot's calories)
    |--------------------------------------------------------------------------
    |
    | Mains scale to these protein-first targets at every tier. Carbs yield within
    | the same tier calorie budget so daily totals stay on plan.
    |
    */
    'main_each_macro_split' => [
        'protein_percentage' => 45.0,
        'carb_percentage' => 25.0,
        'fat_percentage' => 30.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Breakfast macro split (% of breakfast slot calories)
    |--------------------------------------------------------------------------
    */
    'breakfast_macro_split' => [
        'protein_percentage' => 40.0,
        'carb_percentage' => 25.0,
        'fat_percentage' => 35.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Fixed pick slots — customer picks 1–2 of 3 per day (~150 kcal each)
    |--------------------------------------------------------------------------
    */
    'fixed_choice_slots' => ['side_salad', 'dessert', 'soup'],
    'fixed_choice_count' => 2,
    'fixed_choice_min_count' => 1,
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
    | Day macro tolerance (grams) — consultation footer warnings & reconciliation
    |--------------------------------------------------------------------------
    */
    'day_macro_tolerance' => [
        'protein_g' => 15.0,
        'carbs_g' => 20.0,
        'fat_g' => 15.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Macro-first main meal scaling
    |--------------------------------------------------------------------------
    */
    'macro_first_main_scaling' => [
        'enabled' => true,
        'herb_flavor_multiplier_min' => 0.5,
        'herb_flavor_multiplier_max' => 2.0,
        'max_primary_meat_grams' => 200.0,
        'carb_baseline_floor_ratio' => 0.6,
        // Day surplus may trim starch further toward this ratio of library baseline
        // so mains can absorb fixed-pick overshoot without cutting fat/veg.
        'carb_day_surplus_floor_ratio' => 0.25,
        // Absolute cookable floor for starch lines — never wipe rice/quinoa to 0 g.
        'carb_kitchen_minimum_grams' => 20.0,
    ],

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
        1500 => 3,
        1800 => 4,
        2000 => 4,
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
        'Avocado' => 25.0,
        'Sweet Potato' => 100.0,
        'White Onion' => 40.0,
        'Red Onion' => 40.0,
        'Bell Pepper (Red)' => 40.0,
        'Spinach (Fresh)' => 25.0,
        'Olive Oil' => 5.0,
        'Olive Oil (Extra Virgin)' => 5.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Culinary portion constraints — cookability over raw macro forcing
    |--------------------------------------------------------------------------
    |
    | Structural floors keep named dish bases and sauté vegetables cookable.
    | Herb/spice caps stop accent ingredients from matching vegetable volume.
    |
    */
    'culinary_portion_constraints' => [
        'title_structural_minimum_grams' => 100.0,
        'default_woody_fresh_herb_maximum_grams' => 1.0,
        'default_soft_fresh_herb_maximum_grams' => 8.0,
        'default_dry_spice_maximum_grams' => 2.0,
        'default_minimum_grams' => [
            'White Onion' => 40.0,
            'Red Onion' => 40.0,
            'Bell Pepper (Red)' => 40.0,
            'Spinach (Fresh)' => 25.0,
            'Olive Oil' => 5.0,
            'Olive Oil (Extra Virgin)' => 5.0,
            'Sweet Potato' => 100.0,
        ],
        'per_meal_minimum_grams' => [
            'Sweet Potato Egg Hash' => [
                'Sweet Potato' => 120.0,
                'White Onion' => 50.0,
                'Bell Pepper (Red)' => 50.0,
                'Spinach (Fresh)' => 30.0,
                'Olive Oil' => 5.0,
            ],
        ],
        'herb_spice_maximum_grams' => [
            'Rosemary (Fresh)' => 1.0,
            'Thyme (Fresh)' => 1.0,
            'Fresh Coriander' => 5.0,
            'Black Pepper' => 1.0,
            'Sea Salt' => 1.0,
            'Flaxseeds' => 5.0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Main meal plate vegetables — minimum kitchen-realistic portions (grams)
    |--------------------------------------------------------------------------
    |
    | When a vegetable is a named component in meal instructions (e.g. "steamed
    | broccoli"), refiners and scaling must not strip it below this floor.
    |
    */
    'main_meal_plate_vegetable_minimum_grams' => 40.0,

    'main_meal_plate_vegetable_ingredients' => [
        'Broccoli',
        'Bok Choy',
        'Sweet Potato',
        'Beetroot',
        'Zucchini',
        'Pumpkin',
        'Green Beans',
        'Mushrooms',
        'Carrots',
        'Bell Pepper (Red)',
        'Spinach (Fresh)',
    ],

    'main_meal_plate_vegetable_canonical_grams' => [
        'Tamarind Honey & Sesame Chicken w Garlicky Green Beans' => [
            'Broccoli' => 60.0,
            'Bok Choy' => 80.0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Slot behaviour
    |--------------------------------------------------------------------------
    |
    | scalable      — portion scales to the slot target for the plan tier
    |                 (breakfast: tier table only; mains: also absorb fixed overshoot)
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
    | Slots eligible for the pick 1–2 fixed choice group
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
            'protein_percentage' => 35.0,
            'carb_percentage' => 35.0,
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
            'protein_percentage' => 35.0,
            'carb_percentage' => 35.0,
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
        'nutrient_dense' => [
            'protein_percentage' => 32.0,
            'carb_percentage' => 28.0,
            'fat_percentage' => 40.0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nutrient-dense protocol per-slot macro splits (% of calories)
    |--------------------------------------------------------------------------
    */
    'nutrient_dense_slot_macro_splits' => [
        'breakfast' => [
            'protein_percentage' => 35.0,
            'carb_percentage' => 20.0,
            'fat_percentage' => 45.0,
        ],
        'main_each' => [
            'protein_percentage' => 35.0,
            'carb_percentage' => 22.0,
            'fat_percentage' => 43.0,
        ],
        'fixed_choice' => [
            'protein_percentage' => 25.0,
            'carb_percentage' => 30.0,
            'fat_percentage' => 45.0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nutrient-dense fermented portion caps (grams per serving)
    |--------------------------------------------------------------------------
    */
    'nutrient_dense_fermented_caps' => [
        'miso_paste' => 12.0,
        'kimchi' => 40.0,
        'sauerkraut' => 50.0,
        'kefir' => 120.0,
        'fermented_chimichurri' => 25.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Nutrient-dense production meal plan (optional env override)
    |--------------------------------------------------------------------------
    */
    'nutrient_dense_production_meal_plan_id' => env('CUSTOMER_NUTRIENT_DENSE_MEAL_PLAN_ID'),

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

    /*
    |--------------------------------------------------------------------------
    | Heal crushed primary protein when building the adapted craft menu
    |--------------------------------------------------------------------------
    |
    | Always on outside PHPUnit. Set true in tests to exercise the library-wide
    | Cache::remember heal path.
    |
    */
    'heal_collapsed_protein_on_adapted_menu_build' => false,

];
