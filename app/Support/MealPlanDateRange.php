<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Calendar span for a meal plan week (Sunday through Saturday).
 *
 * On Friday 25 September 2026 the upcoming plan is 27th September to 3rd October.
 */
final class MealPlanDateRange
{
    /**
     * @param  list<int>  $selectedWeekdays  1 = Sunday … 7 = Saturday. Empty means the full week.
     * @return array{startsOn: string, endsOn: string, label: string}
     */
    public static function forWeek(?CarbonInterface $today = null, array $selectedWeekdays = []): array
    {
        $weekStart = self::upcomingSunday($today ?? now());
        [$startOffset, $endOffset] = self::offsets($selectedWeekdays);
        $start = $weekStart->copy()->addDays($startOffset);
        $end = $weekStart->copy()->addDays($endOffset);

        return [
            'startsOn' => $start->toDateString(),
            'endsOn' => $end->toDateString(),
            'label' => self::rangeLabel($start, $end),
        ];
    }

    /**
     * @return array{startsOn: string, endsOn: string, label: string}|null
     */
    public static function fromPublishedDates(?CarbonInterface $startsOn, ?CarbonInterface $endsOn): ?array
    {
        if ($startsOn === null || $endsOn === null) {
            return null;
        }

        $start = Carbon::parse($startsOn)->startOfDay();
        $end = Carbon::parse($endsOn)->startOfDay();

        return [
            'startsOn' => $start->toDateString(),
            'endsOn' => $end->toDateString(),
            'label' => self::rangeLabel($start, $end),
        ];
    }

    /**
     * Weekdays still available from today through the published end date.
     *
     * @return list<int> 1 = Sunday … 7 = Saturday
     */
    public static function remainingWeekdays(
        CarbonInterface $startsOn,
        CarbonInterface $endsOn,
        ?CarbonInterface $today = null,
    ): array {
        $today = Carbon::parse($today ?? now())->startOfDay();
        $start = Carbon::parse($startsOn)->startOfDay();
        $end = Carbon::parse($endsOn)->startOfDay();

        if ($today->greaterThan($end)) {
            return [];
        }

        $cursor = $today->lessThan($start) ? $start->copy() : $today->copy();
        $days = [];

        while ($cursor->lessThanOrEqualTo($end)) {
            $days[] = $cursor->dayOfWeek === 0 ? 1 : $cursor->dayOfWeek + 1;
            $cursor->addDay();
        }

        return array_values(array_unique($days));
    }

    /**
     * @param  list<int>  $weekdays
     * @return array{startsOn: string, endsOn: string, label: string}|null
     */
    public static function forRemainingWeekdays(
        CarbonInterface $startsOn,
        CarbonInterface $endsOn,
        array $weekdays,
    ): ?array {
        if ($weekdays === []) {
            return null;
        }

        $weekStart = Carbon::parse($startsOn)->startOfDay();
        sort($weekdays);
        $first = $weekdays[0];
        $last = $weekdays[array_key_last($weekdays)];
        $start = $weekStart->copy()->addDays($first - 1);
        $end = Carbon::parse($endsOn)->startOfDay();
        $endCandidate = $weekStart->copy()->addDays($last - 1);

        if ($endCandidate->lessThanOrEqualTo($end)) {
            $end = $endCandidate;
        }

        return [
            'startsOn' => $start->toDateString(),
            'endsOn' => $end->toDateString(),
            'label' => self::rangeLabel($start, $end),
        ];
    }

    public static function dateForWeekday(int $dayOfWeek, ?CarbonInterface $today = null): Carbon
    {
        $dayOfWeek = max(1, min(7, $dayOfWeek));

        return self::upcomingSunday($today ?? now())->addDays($dayOfWeek - 1);
    }

    public static function dayLabel(CarbonInterface $date): string
    {
        return Carbon::parse($date)->format('jS F');
    }

    private static function upcomingSunday(CarbonInterface $today): Carbon
    {
        $today = Carbon::parse($today)->startOfDay();

        return $today->isSunday() ? $today : $today->next(Carbon::SUNDAY);
    }

    /**
     * @param  list<int>  $selectedWeekdays
     * @return array{0: int, 1: int}
     */
    private static function offsets(array $selectedWeekdays): array
    {
        $days = array_values(array_unique(array_filter(
            array_map(static fn (mixed $day): int => (int) $day, $selectedWeekdays),
            static fn (int $day): bool => $day >= 1 && $day <= 7,
        )));

        if ($days === []) {
            return [0, 6];
        }

        sort($days);

        return [$days[0] - 1, $days[array_key_last($days)] - 1];
    }

    private static function rangeLabel(CarbonInterface $start, CarbonInterface $end): string
    {
        $start = Carbon::parse($start);
        $end = Carbon::parse($end);

        if ($start->isSameDay($end)) {
            return self::dayLabel($start);
        }

        return self::dayLabel($start).' to '.self::dayLabel($end);
    }
}
