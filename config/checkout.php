<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    */

    'currency' => 'BHD',

    /*
    |--------------------------------------------------------------------------
    | Delivery and cart discounts (BHD)
    |--------------------------------------------------------------------------
    |
    | Admin pricing cards can override the limited-time offer. Delivery stays
    | here until an admin discount row replaces it.
    |
    */

    'delivery_cost' => 5.000,

    'limited_time_discount' => 20.000,

    'limited_time_discount_label' => 'Limited time offer',

    /*
    |--------------------------------------------------------------------------
    | Optional promo codes → additional discount (BHD)
    |--------------------------------------------------------------------------
    */

    'promo_codes' => [
        'MEALCRAFT' => 10.000,
    ],

    'benefit_pay_number' => '33177718',

    'refund_policy_url' => '/refund-policy#refund',

    'freeze_policy_url' => '/refund-policy#freeze',

    /*
    |--------------------------------------------------------------------------
    | Partner Kitchen meal price matrix (BHD, whole dinars)
    |--------------------------------------------------------------------------
    |
    | Full, Afternoon, Day, and Intermittent share the calorie rows.
    | Business uses a separate day-length table (calories stay 500–600).
    | Keys under calorie_matrix are billed meal days.
    |
    */

    'price_calorie_rows' => [1200, 1500, 1800, 2000],

    'calorie_matrix' => [
        1200 => [
            28 => 266,
            24 => 228,
            20 => 190,
            7 => 79,
            6 => 68,
            5 => 57,
        ],
        1500 => [
            28 => 294,
            24 => 252,
            20 => 210,
            7 => 89,
            6 => 76,
            5 => 63,
        ],
        1800 => [
            28 => 329,
            24 => 282,
            20 => 235,
            7 => 99,
            6 => 85,
            5 => 71,
        ],
        2000 => [
            28 => 350,
            24 => 300,
            20 => 250,
            7 => 105,
            6 => 90,
            5 => 75,
        ],
    ],

    'business_matrix' => [
        28 => 133,
        24 => 114,
        20 => 95,
        7 => 40,
        6 => 34,
        5 => 29,
    ],

];
