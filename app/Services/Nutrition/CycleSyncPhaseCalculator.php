<?php

namespace App\Services\Nutrition;

use App\Enums\CycleProtocolMode;
use App\Enums\CycleSyncPhase;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Luteal-anchored Cycle Sync phase ranges, cycle day, and calendar color metadata.
 */
final class CycleSyncPhaseCalculator
{
    public const DEFAULT_CYCLE_LENGTH = 30;

    public const MIN_CYCLE_LENGTH = 21;

    public const MAX_CYCLE_LENGTH = 45;

    public const LOGGED_BLEED_COLOR = '#C44F5D';

    public const NURTURE_DAYS = 11;

    public const POWER_PHASE_2_DAYS = 4;

    public const MANIFESTATION_DAYS = 5;

    /**
     * @param  array<int, array{start: string, end?: string|null}>  $loggedPeriods
     * @return array{
     *     cycle_day: ?int,
     *     cycle_length: int,
     *     cycle_start: ?string,
     *     current_phase: ?string,
     *     current_sync_phase: ?string,
     *     phase_ranges: array<string, array{start: int, end: int}>,
     *     dates: list<array{date: string, cycle_day: int, phase: string, color: string, logged: bool}>,
     *     logged_periods: array<int, array{start: string, end?: string|null}>,
     *     last_period_date: ?string,
     *     lastPeriodDate: ?string,
     *     cycleLength: int,
     *     loggedPeriods: array<int, array{start: string, end?: string|null}>,
     *     currentPhase: ?string,
     *     currentSyncPhase: ?string,
     *     protocolMode: string,
     *     phaseRanges: array<string, array{start: int, end: int}>
     * }
     */
    public static function buildPeriodTrackingData(
        array $loggedPeriods,
        ?int $averageCycleLength = null,
        ?CarbonInterface $referenceDate = null,
        CycleProtocolMode|string|null $protocolMode = null,
    ): array {
        $mode = CycleProtocolMode::tryFromStored($protocolMode);
        $cycleLength = self::normalizeCycleLength($averageCycleLength);
        $cycleStart = self::resolveCycleStart($loggedPeriods);
        $reference = Carbon::parse($referenceDate ?? now())->startOfDay();
        $loggedDates = self::expandLoggedDates($loggedPeriods);
        $phaseRanges = self::phaseRangesForCycleLength($cycleLength);

        $cycleDay = null;
        $syncPhase = null;

        if ($cycleStart !== null) {
            $cycleDay = self::cycleDayForDate($cycleStart, $reference->toDateString());
            $syncPhase = self::phaseForCycleDay($cycleDay, $cycleLength);
        }

        $dates = self::buildDateMetadata(
            $cycleStart,
            $cycleLength,
            $loggedDates,
            $reference,
        );
        $phaseCatalog = self::phaseCatalog($cycleLength, $mode);
        $phaseMetadata = $syncPhase?->metadata($mode);
        $mealPlanPayload = $syncPhase?->mealPlanGenerationPayload($mode);

        return [
            'last_period_date' => $cycleStart,
            'lastPeriodDate' => $cycleStart,
            'cycle_start' => $cycleStart,
            'cycle_length' => $cycleLength,
            'cycleLength' => $cycleLength,
            'cycle_day' => $cycleDay,
            'cycleDay' => $cycleDay,
            'protocol_mode' => $mode->value,
            'protocolMode' => $mode->value,
            'current_phase' => $syncPhase?->toLegacyCyclePhase()->value,
            'currentPhase' => $syncPhase?->toLegacyCyclePhase()->value,
            'current_sync_phase' => $syncPhase?->value,
            'currentSyncPhase' => $syncPhase?->value,
            'current_phase_metadata' => $phaseMetadata,
            'currentPhaseMetadata' => $phaseMetadata,
            'meal_plan_generation_payload' => $mealPlanPayload,
            'mealPlanGenerationPayload' => $mealPlanPayload,
            'phase_ranges' => $phaseRanges,
            'phaseRanges' => $phaseRanges,
            'phase_catalog' => $phaseCatalog,
            'phaseCatalog' => $phaseCatalog,
            'dates' => $dates,
            'logged_periods' => $loggedPeriods,
            'loggedPeriods' => $loggedPeriods,
        ];
    }

    /**
     * @return list<array{
     *     id: string,
     *     phase_name: string,
     *     biological_phase: string,
     *     sub_label: string,
     *     fasting_window: string,
     *     fasting_type: string,
     *     food_approach: string,
     *     why_description: string,
     *     protocol_mode: string,
     *     schedules_full_daily_meals: bool,
     *     meal_plan_cta_label: string,
     *     color: string,
     *     start: int,
     *     end: int
     * }>
     */
    public static function phaseCatalog(
        int $cycleLength,
        CycleProtocolMode|string|null $protocolMode = null,
    ): array {
        $mode = CycleProtocolMode::tryFromStored($protocolMode);
        $ranges = self::phaseRangesForCycleLength($cycleLength);

        return array_map(
            static function (CycleSyncPhase $phase) use ($ranges, $mode): array {
                return [
                    ...$phase->metadata($mode),
                    'start' => $ranges[$phase->value]['start'],
                    'end' => $ranges[$phase->value]['end'],
                ];
            },
            CycleSyncPhase::cases(),
        );
    }

    public static function normalizeCycleLength(?int $cycleLength): int
    {
        if ($cycleLength === null) {
            return self::DEFAULT_CYCLE_LENGTH;
        }

        return max(self::MIN_CYCLE_LENGTH, min(self::MAX_CYCLE_LENGTH, $cycleLength));
    }

    /**
     * @return array<string, array{start: int, end: int}>
     */
    public static function phaseRangesForCycleLength(int $cycleLength): array
    {
        $length = self::normalizeCycleLength($cycleLength);
        $nurtureStart = max(1, $length - self::NURTURE_DAYS + 1);
        $power2End = $nurtureStart - 1;
        $power2Start = max(1, $power2End - self::POWER_PHASE_2_DAYS + 1);
        $manifestEnd = $power2Start - 1;
        $manifestStart = max(1, $manifestEnd - self::MANIFESTATION_DAYS + 1);
        $power1End = max(0, $manifestStart - 1);

        return [
            CycleSyncPhase::PowerPhase1->value => [
                'start' => 1,
                'end' => $power1End,
            ],
            CycleSyncPhase::Manifestation->value => [
                'start' => $manifestStart,
                'end' => $manifestEnd,
            ],
            CycleSyncPhase::PowerPhase2->value => [
                'start' => $power2Start,
                'end' => $power2End,
            ],
            CycleSyncPhase::NurturePhase->value => [
                'start' => $nurtureStart,
                'end' => $length,
            ],
        ];
    }

    public static function phaseForCycleDay(int $cycleDay, int $cycleLength): CycleSyncPhase
    {
        if ($cycleDay < 1) {
            return CycleSyncPhase::PowerPhase1;
        }

        $length = self::normalizeCycleLength($cycleLength);
        $ranges = self::phaseRangesForCycleLength($length);

        if ($cycleDay > $length) {
            return CycleSyncPhase::NurturePhase;
        }

        foreach ([
            CycleSyncPhase::NurturePhase,
            CycleSyncPhase::PowerPhase2,
            CycleSyncPhase::Manifestation,
            CycleSyncPhase::PowerPhase1,
        ] as $phase) {
            $range = $ranges[$phase->value];

            if ($cycleDay >= $range['start'] && $cycleDay <= $range['end']) {
                return $phase;
            }
        }

        return CycleSyncPhase::PowerPhase1;
    }

    public static function colorForDate(bool $logged, CycleSyncPhase $phase): string
    {
        if ($logged) {
            return self::LOGGED_BLEED_COLOR;
        }

        return $phase->color();
    }

    /**
     * Earliest date of the latest contiguous bleed cluster.
     *
     * @param  array<int, array{start: string, end?: string|null}>  $loggedPeriods
     */
    public static function resolveCycleStart(array $loggedPeriods): ?string
    {
        $dates = self::expandLoggedDates($loggedPeriods);

        if ($dates === []) {
            return null;
        }

        sort($dates);

        $clusterStart = $dates[count($dates) - 1];

        for ($index = count($dates) - 2; $index >= 0; $index--) {
            $previous = Carbon::parse($dates[$index])->startOfDay();
            $current = Carbon::parse($dates[$index + 1])->startOfDay();

            if ((int) $previous->diffInDays($current, false) === 1) {
                $clusterStart = $dates[$index];

                continue;
            }

            break;
        }

        return $clusterStart;
    }

    /**
     * @param  array<int, array{start: string, end?: string|null}>  $loggedPeriods
     * @return list<string>
     */
    public static function expandLoggedDates(array $loggedPeriods): array
    {
        $dates = [];

        foreach ($loggedPeriods as $period) {
            $start = $period['start'] ?? null;

            if (! is_string($start) || $start === '') {
                continue;
            }

            $end = $period['end'] ?? $start;

            if (! is_string($end) || $end === '') {
                $end = $start;
            }

            $cursor = Carbon::parse($start)->startOfDay();
            $last = Carbon::parse($end)->startOfDay();

            if ($last->lt($cursor)) {
                [$cursor, $last] = [$last, $cursor];
            }

            while ($cursor->lte($last)) {
                $dates[] = $cursor->toDateString();
                $cursor->addDay();
            }
        }

        $dates = array_values(array_unique($dates));
        sort($dates);

        return $dates;
    }

    /**
     * @param  list<string>  $loggedDates
     * @return list<array{date: string, cycle_day: int, phase: string, color: string, logged: bool}>
     */
    public static function buildDateMetadata(
        ?string $cycleStart,
        int $cycleLength,
        array $loggedDates,
        ?CarbonInterface $referenceDate = null,
    ): array {
        if ($cycleStart === null) {
            return [];
        }

        $length = self::normalizeCycleLength($cycleLength);
        $reference = Carbon::parse($referenceDate ?? now())->startOfDay();
        $start = Carbon::parse($cycleStart)->startOfDay();
        $horizonEnd = $start->copy()->addDays(max($length + 14, (int) $start->diffInDays($reference, false) + 7));
        $loggedLookup = array_fill_keys($loggedDates, true);
        $dates = [];

        $cursor = $start->copy();

        while ($cursor->lte($horizonEnd)) {
            $iso = $cursor->toDateString();
            $cycleDay = self::cycleDayForDate($cycleStart, $iso);
            $phase = self::phaseForCycleDay($cycleDay, $length);
            $logged = isset($loggedLookup[$iso]);

            $dates[] = [
                'date' => $iso,
                'cycle_day' => $cycleDay,
                'phase' => $phase->value,
                'color' => self::colorForDate($logged, $phase),
                'logged' => $logged,
            ];

            $cursor->addDay();
        }

        return $dates;
    }

    public static function cycleDayForDate(string $cycleStart, string $isoDate): int
    {
        $start = Carbon::parse($cycleStart)->startOfDay();
        $date = Carbon::parse($isoDate)->startOfDay();

        return ((int) $start->diffInDays($date, false)) + 1;
    }
}
