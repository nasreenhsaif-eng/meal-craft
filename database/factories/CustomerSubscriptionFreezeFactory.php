<?php

namespace Database\Factories;

use App\Models\CustomerSubscription;
use App\Models\CustomerSubscriptionFreeze;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerSubscriptionFreeze>
 */
class CustomerSubscriptionFreezeFactory extends Factory
{
    protected $model = CustomerSubscriptionFreeze::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_subscription_id' => CustomerSubscription::factory(),
            'frozen_on' => now()->addDays(3)->toDateString(),
        ];
    }
}
