<?php

namespace App\Services\Nutrition;

use App\Enums\CyclePhase;
use Illuminate\Support\Carbon;

/**
 * Derives menstrual cycle phase from onboarding period tracking data.
 */
final class PeriodTrackingPhaseService
{
    private const DEFAULT_CYCLE_LENGTH = 28;

    private const PERIOD_BLEEDING_DAYS = 5;

    private const OVULATION_DAYS_BEFORE_NEXT_PERIOD = 14;

    private const FERTILE_DAYS_BEFORE_OVULATION = 5;

    private const FERTILE_DAYS_AFTER_OVULATION = 1;

    /**
     * @param  array<string, mixed>|null  $periodTrackingData
     */
    public static function resolveCurrentPhase(?array $periodTrackingData): ?CyclePhase
    {
        if ($periodTrackingData === null || $periodTrackingData === []) {
            return null;
        }

        $explicit = $periodTrackingData['currentPhase'] ?? $periodTrackingData['current_phase'] ?? null;

        if (is_string($explicit) && $explicit !== '') {
            return CyclePhase::tryFrom($explicit);
        }

        $lastPeriodDate = self::resolveLastPeriodDate($periodTrackingData);

        if ($lastPeriodDate === null) {
            return null;
        }

        $cycleLength = max(
            21,
            (int) ($periodTrackingData['cycleLength'] ?? $periodTrackingData['cycle_length'] ?? $periodTrackingData['average_cycle_length'] ?? self::DEFAULT_CYCLE_LENGTH),
        );

        $today = now()->startOfDay();
        $periodStart = Carbon::parse($lastPeriodDate)->startOfDay();
        $daysSinceStart = (int) $periodStart->diffInDays($today, false);

        if ($daysSinceStart < 0) {
            return CyclePhase::Follicular;
        }

        $dayInCycle = $daysSinceStart % $cycleLength;

        if ($dayInCycle < self::PERIOD_BLEEDING_DAYS) {
            return CyclePhase::Menstrual;
        }

        $ovulationDay = max(self::PERIOD_BLEEDING_DAYS, $cycleLength - self::OVULATION_DAYS_BEFORE_NEXT_PERIOD);
        $fertileStart = max(self::PERIOD_BLEEDING_DAYS, $ovulationDay - self::FERTILE_DAYS_BEFORE_OVULATION);
        $fertileEnd = min($cycleLength - 1, $ovulationDay + self::FERTILE_DAYS_AFTER_OVULATION);

        if ($dayInCycle >= $fertileStart && $dayInCycle <= $fertileEnd) {
            return $dayInCycle === $ovulationDay ? CyclePhase::Ovulatory : CyclePhase::Follicular;
        }

        if ($dayInCycle < $ovulationDay) {
            return CyclePhase::Follicular;
        }

        return CyclePhase::Luteal;
    }

    /**
     * @param  array<int, array{start: string, end?: string}>  $loggedPeriods
     * @return array{
     *     last_period_date: ?string,
     *     cycle_length: int,
     *     current_phase: ?string,
     *     logged_periods: array<int, array{start: string, end?: string}>
     * }
     */
    public static function buildPeriodTrackingData(
        array $loggedPeriods,
        ?int $averageCycleLength = null,
        ?CyclePhase $currentPhase = null,
    ): array {
        $lastPeriodDate = self::resolveLastPeriodDateFromLogs($loggedPeriods);
        $cycleLength = $averageCycleLength ?? self::DEFAULT_CYCLE_LENGTH;

        $payload = [
            'last_period_date' => $lastPeriodDate,
            'lastPeriodDate' => $lastPeriodDate,
            'cycle_length' => $cycleLength,
            'cycleLength' => $cycleLength,
            'logged_periods' => $loggedPeriods,
            'loggedPeriods' => $loggedPeriods,
        ];

        $phase = $currentPhase ?? self::resolveCurrentPhase($payload);
        $payload['current_phase'] = $phase?->value;
        $payload['currentPhase'] = $phase?->value;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $periodTrackingData
     */
    private static function resolveLastPeriodDate(array $periodTrackingData): ?string
    {
        $explicit = $periodTrackingData['last_period_date'] ?? $periodTrackingData['lastPeriodDate'] ?? null;

        if (is_string($explicit) && $explicit !== '') {
            return $explicit;
        }

        $logged = $periodTrackingData['logged_periods'] ?? $periodTrackingData['loggedPeriods'] ?? [];

        return self::resolveLastPeriodDateFromLogs(is_array($logged) ? $logged : []);
    }

    /**
     * @param  array<int, array{start: string, end?: string}>  $loggedPeriods
     */
    private static function resolveLastPeriodDateFromLogs(array $loggedPeriods): ?string
    {
        if ($loggedPeriods === []) {
            return null;
        }

        usort($loggedPeriods, static fn (array $a, array $b): int => strcmp($b['start'], $a['start']));

        return $loggedPeriods[0]['start'] ?? null;
    }
}
