<?php

use App\Support\MealPlanDateRange;
use Illuminate\Support\Carbon;

test('upcoming meal plan week runs from the next sunday through saturday', function () {
    $friday = Carbon::parse('2026-09-25');

    $range = MealPlanDateRange::forWeek($friday);

    expect($range['startsOn'])->toBe('2026-09-27')
        ->and($range['endsOn'])->toBe('2026-10-03')
        ->and($range['label'])->toBe('27th September to 3rd October');
});

test('a sunday uses the week that starts that day', function () {
    $range = MealPlanDateRange::forWeek(Carbon::parse('2026-09-27'));

    expect($range['startsOn'])->toBe('2026-09-27')
        ->and($range['endsOn'])->toBe('2026-10-03');
});

test('selected weekdays narrow the plan start and end', function () {
    $range = MealPlanDateRange::forWeek(Carbon::parse('2026-09-25'), [2, 6]);

    expect($range['startsOn'])->toBe('2026-09-28')
        ->and($range['endsOn'])->toBe('2026-10-02')
        ->and($range['label'])->toBe('28th September to 2nd October');
});

test('weekday dates fall inside the upcoming plan week', function () {
    $friday = Carbon::parse('2026-09-25');

    expect(MealPlanDateRange::dateForWeekday(1, $friday)->toDateString())->toBe('2026-09-27')
        ->and(MealPlanDateRange::dateForWeekday(7, $friday)->toDateString())->toBe('2026-10-03')
        ->and(MealPlanDateRange::dayLabel(MealPlanDateRange::dateForWeekday(7, $friday)))->toBe('3rd October');
});
