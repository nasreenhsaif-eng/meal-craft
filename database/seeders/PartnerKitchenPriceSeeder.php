<?php

namespace Database\Seeders;

use App\Models\PartnerKitchenDiscount;
use App\Models\PlanCaloriePrice;
use App\Models\SubscriptionPlan;
use App\Support\SubscriptionPlanCatalog;
use Illuminate\Database\Seeder;

class PartnerKitchenPriceSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SubscriptionPlanCatalog::plans() as $definition) {
            $plan = SubscriptionPlan::query()->updateOrCreate(
                ['key' => $definition['key']],
                [
                    'slug' => $definition['slug'],
                    'name' => $definition['name'],
                    'meal_structure' => $definition['meal_structure'],
                    'min_calories' => $definition['min_calories'],
                    'is_active' => true,
                ],
            );

            foreach ($definition['calorie_tiers'] as $calorieTier) {
                foreach (SubscriptionPlanCatalog::DAYS as $daysCount) {
                    $base = SubscriptionPlanCatalog::basePrice($calorieTier, $daysCount);
                    $price = PlanCaloriePrice::query()->firstOrNew([
                        'plan_id' => $plan->id,
                        'calorie_tier' => $calorieTier,
                        'days_count' => $daysCount,
                    ]);

                    if (! $price->exists) {
                        $price->base_total_price = $base;
                        $price->discounted_total_price = null;
                        $price->is_featured = $daysCount === 24;
                        $price->syncDailyRate();
                        $price->save();
                    }
                }
            }
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
