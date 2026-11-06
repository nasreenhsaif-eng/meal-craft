<?php

namespace Database\Seeders;

use App\Models\PartnerKitchenDiscount;
use App\Models\PartnerKitchenPriceCard;
use Illuminate\Database\Seeder;

class PartnerKitchenPriceSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array<int, array<int, int|float>> $calorieMatrix */
        $calorieMatrix = config('checkout.calorie_matrix', []);

        foreach ($calorieMatrix as $calorieRow => $days) {
            foreach ($days as $billedDays => $amount) {
                PartnerKitchenPriceCard::query()->updateOrCreate(
                    [
                        'scope' => 'shared',
                        'calorie_row' => (int) $calorieRow,
                        'billed_days' => (int) $billedDays,
                    ],
                    ['amount_bhd' => round((float) $amount, 3)],
                );
            }
        }

        /** @var array<int, int|float> $businessMatrix */
        $businessMatrix = config('checkout.business_matrix', []);

        foreach ($businessMatrix as $billedDays => $amount) {
            PartnerKitchenPriceCard::query()->updateOrCreate(
                [
                    'scope' => 'business',
                    'calorie_row' => 0,
                    'billed_days' => (int) $billedDays,
                ],
                ['amount_bhd' => round((float) $amount, 3)],
            );
        }

        PartnerKitchenDiscount::query()->updateOrCreate(
            [
                'kind' => 'limited_time',
                'label' => (string) config('checkout.limited_time_discount_label', 'Limited time offer'),
            ],
            [
                'amount_bhd' => round((float) config('checkout.limited_time_discount', 20), 3),
                'is_active' => true,
            ],
        );
    }
}
