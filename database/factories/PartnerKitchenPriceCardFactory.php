<?php

namespace Database\Factories;

use App\Models\PartnerKitchenPriceCard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerKitchenPriceCard>
 */
class PartnerKitchenPriceCardFactory extends Factory
{
    protected $model = PartnerKitchenPriceCard::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scope' => 'full',
            'calorie_row' => 1200,
            'billed_days' => 20,
            'amount_bhd' => 190,
            'discount_type' => 'amount',
            'discount_bhd' => 0,
        ];
    }
}
