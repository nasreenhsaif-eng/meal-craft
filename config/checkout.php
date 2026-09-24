<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Partner kitchen weekly plan price (BHD)
    |--------------------------------------------------------------------------
    |
    | Matches the Picniq card on the delivery step until live pricing exists.
    |
    */

    'currency' => 'BHD',

    'subtotal' => 385.000,

    'delivery_cost' => 5.000,

    /*
    |--------------------------------------------------------------------------
    | Limited-time offer discount (BHD)
    |--------------------------------------------------------------------------
    */

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

    'refund_policy_url' => '/refund-policy',

];
