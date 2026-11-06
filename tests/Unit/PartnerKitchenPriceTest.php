<?php

use App\Support\PartnerKitchenPrice;

test('nearest calorie row maps craft plates onto the price table', function () {
    expect(PartnerKitchenPrice::nearestCalorieRow(1250))->toBe(1200)
        ->and(PartnerKitchenPrice::nearestCalorieRow(950))->toBe(1200)
        ->and(PartnerKitchenPrice::nearestCalorieRow(1100))->toBe(1200)
        ->and(PartnerKitchenPrice::nearestCalorieRow(1400))->toBe(1500)
        ->and(PartnerKitchenPrice::nearestCalorieRow(2000))->toBe(2000);
});

test('shared crafts use the calorie matrix for four week packages', function (string $craftKey) {
    expect(PartnerKitchenPrice::packageAmount($craftKey, 2000, 7, 4))->toBe(350.0)
        ->and(PartnerKitchenPrice::packageAmount($craftKey, 2000, 6, 4))->toBe(300.0)
        ->and(PartnerKitchenPrice::packageAmount($craftKey, 2000, 5, 4))->toBe(250.0)
        ->and(PartnerKitchenPrice::packageAmount($craftKey, 1200, 7, 4))->toBe(266.0);
})->with(['full', 'afternoon', 'day', 'intermittent']);

test('shared crafts use the trial one week prices', function () {
    expect(PartnerKitchenPrice::packageAmount('full', 1200, 7, 1))->toBe(79.0)
        ->and(PartnerKitchenPrice::packageAmount('full', 1500, 6, 1))->toBe(76.0)
        ->and(PartnerKitchenPrice::packageAmount('full', 1800, 5, 1))->toBe(71.0)
        ->and(PartnerKitchenPrice::packageAmount('day', 1400, 7, 1))->toBe(89.0)
        ->and(PartnerKitchenPrice::packageAmount('intermittent', 1100, 5, 1))->toBe(57.0);
});

test('business craft ignores calorie plate and uses the day length table', function () {
    expect(PartnerKitchenPrice::packageAmount('business', 550, 5, 4))->toBe(95.0)
        ->and(PartnerKitchenPrice::packageAmount('business', 650, 5, 4))->toBe(95.0)
        ->and(PartnerKitchenPrice::packageAmount('business', 550, 6, 4))->toBe(114.0)
        ->and(PartnerKitchenPrice::packageAmount('business', 550, 7, 4))->toBe(133.0)
        ->and(PartnerKitchenPrice::packageAmount('business', 550, 7, 1))->toBe(40.0)
        ->and(PartnerKitchenPrice::packageAmount('business', 550, 6, 1))->toBe(34.0)
        ->and(PartnerKitchenPrice::packageAmount('business', 550, 5, 1))->toBe(29.0);
});

test('daily rate for craft buttons uses the four week per day amount', function () {
    expect(PartnerKitchenPrice::dailyRateForCraft('full', 1200))->toBe(9.5)
        ->and(PartnerKitchenPrice::dailyRateForCraft('full', 2000))->toBe(12.5)
        ->and(PartnerKitchenPrice::dailyRateForCraft('business', 550))->toBe(4.75);
});

test('billed day helpers map week length and plan weeks', function () {
    expect(PartnerKitchenPrice::billedDays(7, 4))->toBe(28)
        ->and(PartnerKitchenPrice::billedDays(6, 4))->toBe(24)
        ->and(PartnerKitchenPrice::billedDays(5, 4))->toBe(20)
        ->and(PartnerKitchenPrice::billedDays(7, 1))->toBe(7)
        ->and(PartnerKitchenPrice::planWeeksFromBilledDays(28))->toBe(4)
        ->and(PartnerKitchenPrice::planWeeksFromBilledDays(7))->toBe(1)
        ->and(PartnerKitchenPrice::weekDurationFromBilledDays(24))->toBe(6);
});
