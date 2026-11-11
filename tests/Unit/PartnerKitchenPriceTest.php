<?php

use App\Support\PartnerKitchenPrice;

test('nearest calorie row maps onto each craft price table', function () {
    expect(PartnerKitchenPrice::nearestCalorieRow(1250, 'full'))->toBe(1200)
        ->and(PartnerKitchenPrice::nearestCalorieRow(950, 'afternoon'))->toBe(950)
        ->and(PartnerKitchenPrice::nearestCalorieRow(850, 'day'))->toBe(850)
        ->and(PartnerKitchenPrice::nearestCalorieRow(1400, 'day'))->toBe(1400)
        ->and(PartnerKitchenPrice::nearestCalorieRow(850, 'intermittent'))->toBe(850)
        ->and(PartnerKitchenPrice::nearestCalorieRow(2000, 'full'))->toBe(2000);
});

test('price calories derive from full library by removing omitted meals', function () {
    expect(PartnerKitchenPrice::priceCaloriesForCraft(1250, 'full'))->toBe(1200)
        ->and(PartnerKitchenPrice::priceCaloriesForCraft(1200, 'full'))->toBe(1200)
        ->and(PartnerKitchenPrice::priceCaloriesForCraft(1250, 'afternoon'))->toBe(950)
        ->and(PartnerKitchenPrice::priceCaloriesForCraft(1200, 'afternoon'))->toBe(950)
        ->and(PartnerKitchenPrice::priceCaloriesForCraft(1250, 'day'))->toBe(850)
        ->and(PartnerKitchenPrice::priceCaloriesForCraft(1250, 'intermittent'))->toBe(850)
        ->and(PartnerKitchenPrice::priceCaloriesForCraft(1500, 'afternoon'))->toBe(1200)
        ->and(PartnerKitchenPrice::priceCaloriesForCraft(1500, 'day'))->toBe(1200)
        ->and(PartnerKitchenPrice::priceCaloriesForCraft(1800, 'afternoon'))->toBe(1400)
        ->and(PartnerKitchenPrice::priceCaloriesForCraft(1800, 'day'))->toBe(1300)
        ->and(PartnerKitchenPrice::priceCaloriesForCraft(2000, 'afternoon'))->toBe(1500)
        ->and(PartnerKitchenPrice::priceCaloriesForCraft(2000, 'intermittent'))->toBe(1400);
});

test('full craft uses the locked four week packages', function () {
    expect(PartnerKitchenPrice::packageAmount('full', 1200, 7, 4))->toBe(260.0)
        ->and(PartnerKitchenPrice::packageAmount('full', 2000, 6, 4))->toBe(313.0)
        ->and(PartnerKitchenPrice::packageAmount('full', 2000, 5, 4))->toBe(270.0)
        ->and(PartnerKitchenPrice::packageAmount('full', 1250, 5, 4))->toBe(200.0);
});

test('afternoon craft floors at 175 for the 950 band', function () {
    expect(PartnerKitchenPrice::packageAmount('afternoon', 950, 5, 4))->toBe(174.0)
        ->and(PartnerKitchenPrice::packageAmount('afternoon', 950, 6, 4))->toBe(202.0)
        ->and(PartnerKitchenPrice::packageAmount('afternoon', 950, 7, 4))->toBe(227.0)
        ->and(PartnerKitchenPrice::packageAmount('afternoon', 1200, 5, 4))->toBe(200.0)
        ->and(PartnerKitchenPrice::packageAmount('afternoon', 1400, 5, 4))->toBe(216.0)
        ->and(PartnerKitchenPrice::packageAmount('afternoon', 1500, 7, 4))->toBe(293.0);
});

test('day and intermittent crafts share the 850 floor at 150', function () {
    expect(PartnerKitchenPrice::packageAmount('day', 850, 5, 4))->toBe(163.0)
        ->and(PartnerKitchenPrice::packageAmount('day', 850, 6, 4))->toBe(190.0)
        ->and(PartnerKitchenPrice::packageAmount('day', 850, 7, 4))->toBe(213.0)
        ->and(PartnerKitchenPrice::packageAmount('intermittent', 850, 5, 4))->toBe(163.0)
        ->and(PartnerKitchenPrice::packageAmount('day', 1100, 5, 4))->toBe(200.0)
        ->and(PartnerKitchenPrice::packageAmount('day', 1300, 5, 4))->toBe(208.0)
        ->and(PartnerKitchenPrice::packageAmount('intermittent', 1400, 7, 4))->toBe(281.0);
});

test('calorie crafts keep the prior trial one week prices', function () {
    expect(PartnerKitchenPrice::packageAmount('full', 1200, 7, 1))->toBe(75.0)
        ->and(PartnerKitchenPrice::packageAmount('full', 1500, 6, 1))->toBe(74.0)
        ->and(PartnerKitchenPrice::packageAmount('full', 1800, 5, 1))->toBe(71.0)
        ->and(PartnerKitchenPrice::packageAmount('afternoon', 950, 7, 1))->toBe(65.0)
        ->and(PartnerKitchenPrice::packageAmount('day', 1400, 7, 1))->toBe(81.0)
        ->and(PartnerKitchenPrice::packageAmount('intermittent', 850, 5, 1))->toBe(47.0);
});

test('business craft ignores calorie plate and uses the day length table', function () {
    expect(PartnerKitchenPrice::packageAmount('business', 550, 5, 4))->toBe(144.0)
        ->and(PartnerKitchenPrice::packageAmount('business', 650, 5, 4))->toBe(144.0)
        ->and(PartnerKitchenPrice::packageAmount('business', 550, 6, 4))->toBe(167.0)
        ->and(PartnerKitchenPrice::packageAmount('business', 550, 7, 4))->toBe(188.0)
        ->and(PartnerKitchenPrice::packageAmount('business', 550, 7, 1))->toBe(54.0)
        ->and(PartnerKitchenPrice::packageAmount('business', 550, 6, 1))->toBe(47.0)
        ->and(PartnerKitchenPrice::packageAmount('business', 550, 5, 1))->toBe(41.0);
});

test('1250 full need maps to craft button daily rates 9.8 / 8.8 / 7.5', function () {
    $fullCalories = PartnerKitchenPrice::priceCaloriesForCraft(1250, 'full');
    $afternoonCalories = PartnerKitchenPrice::priceCaloriesForCraft(1250, 'afternoon');
    $dayCalories = PartnerKitchenPrice::priceCaloriesForCraft(1250, 'day');
    $intermittentCalories = PartnerKitchenPrice::priceCaloriesForCraft(1250, 'intermittent');

    expect($fullCalories)->toBe(1200)
        ->and($afternoonCalories)->toBe(950)
        ->and($dayCalories)->toBe(850)
        ->and($intermittentCalories)->toBe(850)
        ->and(PartnerKitchenPrice::dailyRateForCraft('full', $fullCalories))->toBe(10.0)
        ->and(PartnerKitchenPrice::dailyRateForCraft('afternoon', $afternoonCalories))->toBe(9.0)
        ->and(PartnerKitchenPrice::dailyRateForCraft('day', $dayCalories))->toBe(8.0)
        ->and(PartnerKitchenPrice::dailyRateForCraft('intermittent', $intermittentCalories))->toBe(8.0)
        ->and(PartnerKitchenPrice::dailyRateForCraft('business', 550))->toBe(7.0);
});

test('1500 full need maps to higher craft package tiers', function () {
    expect(PartnerKitchenPrice::dailyRateForCraft('full', PartnerKitchenPrice::priceCaloriesForCraft(1500, 'full')))->toBe(11.0)
        ->and(PartnerKitchenPrice::dailyRateForCraft('afternoon', PartnerKitchenPrice::priceCaloriesForCraft(1500, 'afternoon')))->toBe(10.0)
        ->and(PartnerKitchenPrice::dailyRateForCraft('day', PartnerKitchenPrice::priceCaloriesForCraft(1500, 'day')))->toBe(10.0);
});

test('partner kitchen display money can use one decimal', function () {
    expect(PartnerKitchenPrice::formatMoney(9.75, 1))->toBe('BHD 9.8')
        ->and(PartnerKitchenPrice::formatMoney(315, 3))->toBe('BHD 315.000');
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
