<?php

use App\Models\PlanCaloriePrice;
use App\Support\PartnerKitchenPrice;
use Database\Seeders\PartnerKitchenPriceSeeder;

test('a flat package discount lowers the checkout amount', function () {
    $this->seed(PartnerKitchenPriceSeeder::class);

    $price = PlanCaloriePrice::query()
        ->where('calorie_tier', 1200)
        ->where('days_count', 20)
        ->whereHas('plan', fn ($query) => $query->where('key', 'full'))
        ->firstOrFail();

    $before = PartnerKitchenPrice::packageAmount('full', 1200, 5, 4);
    $price->applyDiscount('amount', 10);

    expect(PartnerKitchenPrice::packageAmount('full', 1200, 5, 4))->toBe((float) max(0, $before - 10))
        ->and($price->fresh()->effectiveTotalPrice())->toBe((int) max(0, $before - 10));
});

test('a percent package discount stores the rounded sale total', function () {
    $this->seed(PartnerKitchenPriceSeeder::class);

    $price = PlanCaloriePrice::query()
        ->where('calorie_tier', 1200)
        ->where('days_count', 20)
        ->whereHas('plan', fn ($query) => $query->where('key', 'full'))
        ->firstOrFail();

    $price->base_total_price = 200;
    $price->discounted_total_price = null;
    $price->syncDailyRate();
    $price->save();
    $price->applyDiscount('percent', 10);

    expect(PartnerKitchenPrice::packageAmount('full', 1200, 5, 4))->toBe(180.0)
        ->and($price->fresh()->daily_rate)->toBe(9);
});
