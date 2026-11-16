<?php

use App\Support\MealPlanDateRange;
use Illuminate\Support\Carbon;

test('remaining weekdays start from today inside a published week', function () {
    $starts = Carbon::parse('2026-09-27');
    $ends = Carbon::parse('2026-10-03');
    $wednesday = Carbon::parse('2026-09-30');

    $days = MealPlanDateRange::remainingWeekdays($starts, $ends, $wednesday);
    $range = MealPlanDateRange::forRemainingWeekdays($starts, $ends, $days);

    expect($days)->toBe([4, 5, 6, 7])
        ->and($range['label'])->toBe('30th September to 3rd October');
});

test('remaining weekdays include the full week before it starts', function () {
    $starts = Carbon::parse('2026-09-27');
    $ends = Carbon::parse('2026-10-03');
    $friday = Carbon::parse('2026-09-25');

    expect(MealPlanDateRange::remainingWeekdays($starts, $ends, $friday))->toBe([1, 2, 3, 4, 5, 6, 7]);
});
