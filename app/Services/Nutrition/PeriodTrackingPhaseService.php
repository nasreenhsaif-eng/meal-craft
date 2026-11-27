<?php

namespace App\Services\Nutrition;

use App\Enums\CyclePhase;
use App\Enums\CycleProtocolMode;
use App\Enums\CycleSyncPhase;

/**
 * Derives menstrual cycle phase from onboarding period tracking data.
 */
final class PeriodTrackingPhaseService
{
    /**
     * @param  array<string, mixed>|null  $periodTrackingData
     */
    public static function resolveCurrentPhase(?array $periodTrackingData): ?CyclePhase
    {
        if ($periodTrackingData === null || $periodTrackingData === []) {
            return null;
        }

        $syncPhase = self::resolveCurrentSyncPhase($periodTrackingData);

        return $syncPhase?->toLegacyCyclePhase();
    }

    /**
     * @param  array<string, mixed>|null  $periodTrackingData
     */
    public static function resolveCurrentSyncPhase(?array $periodTrackingData): ?CycleSyncPhase
    {
        if ($periodTrackingData === null || $periodTrackingData === []) {
            return null;
        }

        $explicitSync = $periodTrackingData['current_sync_phase']
            ?? $periodTrackingData['currentSyncPhase']
            ?? null;

        if (is_string($explicitSync) && $explicitSync !== '') {
            return CycleSyncPhase::tryFrom($explicitSync);
        }

        $explicit = $periodTrackingData['currentPhase'] ?? $periodTrackingData['current_phase'] ?? null;

        if (is_string($explicit) && $explicit !== '') {
            $sync = CycleSyncPhase::tryFrom($explicit);

            if ($sync !== null) {
                return $sync;
            }

            $legacy = CyclePhase::tryFrom($explicit);

            return match ($legacy) {
                CyclePhase::Menstrual => CycleSyncPhase::PowerPhase1,
                CyclePhase::Follicular => CycleSyncPhase::PowerPhase2,
                CyclePhase::Ovulatory => CycleSyncPhase::Manifestation,
                CyclePhase::Luteal => CycleSyncPhase::NurturePhase,
                default => null,
            };
        }

        $logged = $periodTrackingData['logged_periods'] ?? $periodTrackingData['loggedPeriods'] ?? [];
        $cycleLength = (int) (
            $periodTrackingData['cycleLength']
            ?? $periodTrackingData['cycle_length']
            ?? $periodTrackingData['average_cycle_length']
            ?? CycleSyncPhaseCalculator::DEFAULT_CYCLE_LENGTH
        );

        if (! is_array($logged) || $logged === []) {
            return null;
        }

        $payload = CycleSyncPhaseCalculator::buildPeriodTrackingData($logged, $cycleLength);

        return CycleSyncPhase::tryFrom($payload['current_sync_phase'] ?? '');
    }

    /**
     * @param  array<int, array{start: string, end?: string}>  $loggedPeriods
     * @return array<string, mixed>
     */
    public static function buildPeriodTrackingData(
        array $loggedPeriods,
        ?int $averageCycleLength = null,
        ?CyclePhase $currentPhase = null,
        CycleProtocolMode|string|null $protocolMode = null,
    ): array {
        $payload = CycleSyncPhaseCalculator::buildPeriodTrackingData(
            $loggedPeriods,
            $averageCycleLength,
            null,
            $protocolMode,
        );

        if ($currentPhase !== null) {
            $payload['current_phase'] = $currentPhase->value;
            $payload['currentPhase'] = $currentPhase->value;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $periodTrackingData
     */
    public static function resolveLastPeriodDate(array $periodTrackingData): ?string
    {
        $explicit = $periodTrackingData['last_period_date']
            ?? $periodTrackingData['lastPeriodDate']
            ?? $periodTrackingData['cycle_start']
            ?? null;

        if (is_string($explicit) && $explicit !== '') {
            return $explicit;
        }

        $logged = $periodTrackingData['logged_periods'] ?? $periodTrackingData['loggedPeriods'] ?? [];

        return CycleSyncPhaseCalculator::resolveCycleStart(is_array($logged) ? $logged : []);
    }
}
