<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'full',
            'slug' => 'full-craft',
            'name' => 'Full Craft',
            'meal_structure' => [
                'breakfast' => true,
                'soup' => false,
                'meal_count' => 2,
                'max_snack_count' => 2,
            ],
            'min_calories' => 1200,
            'is_active' => true,
        ];
    }
}
