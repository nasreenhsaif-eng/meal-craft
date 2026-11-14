<?php

use App\Models\PlanCaloriePrice;
use App\Models\SubscriptionPlan;
use Database\Seeders\PartnerKitchenPriceSeeder;

test('seeded daily rates do not rise as billed days increase', function () {
    $this->seed(PartnerKitchenPriceSeeder::class);

    SubscriptionPlan::query()->each(function (SubscriptionPlan $plan): void {
        $plan->prices()
            ->orderBy('calorie_tier')
            ->orderBy('days_count')
            ->get()
            ->groupBy('calorie_tier')
            ->each(function ($prices): void {
                $previous = null;

                foreach ($prices as $price) {
                    if ($previous instanceof PlanCaloriePrice) {
                        expect($price->daily_rate)->toBeLessThanOrEqual($previous->daily_rate);
                    }

                    $previous = $price;
                }
            });
    });
});
