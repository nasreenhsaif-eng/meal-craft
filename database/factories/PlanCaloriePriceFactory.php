<?php

namespace Database\Factories;

use App\Models\PlanCaloriePrice;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanCaloriePrice>
 */
class PlanCaloriePriceFactory extends Factory
{
    protected $model = PlanCaloriePrice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => SubscriptionPlan::factory(),
            'calorie_tier' => 1200,
            'days_count' => 20,
            'base_total_price' => 200,
            'discounted_total_price' => null,
            'daily_rate' => 10,
            'is_featured' => false,
        ];
    }
}
