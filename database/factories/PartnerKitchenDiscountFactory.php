<?php

namespace Database\Factories;

use App\Models\PartnerKitchenDiscount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerKitchenDiscount>
 */
class PartnerKitchenDiscountFactory extends Factory
{
    protected $model = PartnerKitchenDiscount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label' => 'Promo discount',
            'kind' => 'fixed',
            'amount_bhd' => 10,
            'is_active' => true,
        ];
    }
}
