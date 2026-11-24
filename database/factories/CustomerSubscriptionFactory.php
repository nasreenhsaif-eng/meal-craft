<?php

namespace Database\Factories;

use App\Enums\CustomerSubscriptionStatus;
use App\Models\CustomerProfile;
use App\Models\CustomerSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerSubscription>
 */
class CustomerSubscriptionFactory extends Factory
{
    protected $model = CustomerSubscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsOn = now()->startOfDay();

        return [
            'customer_profile_id' => CustomerProfile::factory(),
            'customer_craft_plan_id' => null,
            'status' => CustomerSubscriptionStatus::Paid,
            'paid_at' => now(),
            'starts_on' => $startsOn->toDateString(),
            'expires_on' => $startsOn->copy()->addWeeks(4)->subDay()->toDateString(),
            'delivery_days_count' => 20,
            'fulfillment_choice' => 'partner_kitchen',
            'craft_key' => 'full',
            'meal_plan_name' => 'Full Craft',
            'amount_bhd' => null,
        ];
    }
}
