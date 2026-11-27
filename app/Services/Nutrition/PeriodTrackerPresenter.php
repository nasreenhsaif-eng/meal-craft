<?php

namespace App\Services\Nutrition;

use App\Enums\CustomerSex;
use App\Enums\CycleProtocolMode;
use App\Enums\CycleSyncPhase;
use App\Enums\DietProtocol;
use App\Models\CustomerProfile;
use Illuminate\Support\Carbon;

/**
 * Single payload for the Tools → Period Tracker screen (Analysis / Food / Exercise / Brain).
 */
final class PeriodTrackerPresenter
{
    /**
     * @return array<string, mixed>|null Null when the customer is not female.
     */
    public static function present(?CustomerProfile $profile): ?array
    {
        if ($profile === null || $profile->sex !== CustomerSex::Female) {
            return null;
        }

        $protocolMode = CycleProtocolMode::tryFromStored($profile->protocol_mode);
        $dietProtocol = DietProtocol::tryFromStored($profile->diet_protocol);
        $isCycleSync = $dietProtocol === DietProtocol::CycleSync;
        $loggedPeriods = is_array($profile->logged_periods) ? $profile->logged_periods : [];

        $tracking = CycleSyncPhaseCalculator::buildPeriodTrackingData(
            $loggedPeriods,
            $profile->average_cycle_length,
            null,
            $protocolMode,
        );

        $currentId = $tracking['current_sync_phase'] ?? null;
        $currentPhase = is_string($currentId) ? CycleSyncPhase::tryFrom($currentId) : null;
        $phaseRanges = $tracking['phase_ranges'] ?? [];

        $phases = [];

        foreach (CycleSyncPhase::cases() as $phase) {
            $range = $phaseRanges[$phase->value] ?? null;
            $exercise = $phase->exerciseGuidance();
            $brain = $phase->brainGuidance();
            $effective = $phase->effectiveFastingFor($protocolMode);

            $phases[] = [
                'id' => $phase->value,
                'phaseName' => $phase->phaseName(),
                'biologicalPhase' => $phase->biologicalPhase(),
                'subLabel' => $phase->subLabel(),
                'color' => $phase->color(),
                'startDay' => is_array($range) ? ($range['start'] ?? null) : null,
                'endDay' => is_array($range) ? ($range['end'] ?? null) : null,
                'analysisSummary' => $phase->analysisSummary(),
                'foodApproach' => $phase->foodApproach(),
                'fastingWindow' => $effective['fasting_window'],
                'fastingType' => $effective['fasting_type'],
                'whyDescription' => $effective['why_description'],
                'mealPlanCtaLabel' => $phase->mealPlanCtaLabel(),
                'exerciseFocus' => $exercise['focus'],
                'exerciseWhy' => $exercise['why'],
                'brainFocus' => $brain['focus'],
                'brainWhatToDo' => $brain['what_to_do'],
                'isCurrent' => $currentPhase === $phase,
            ];
        }

        $stored = is_array($profile->period_tracking_data) ? $profile->period_tracking_data : [];
        $effectiveFrom = $stored['meal_plan_effective_from'] ?? null;

        return [
            'cycleDay' => $tracking['cycle_day'] ?? null,
            'cycleLength' => $tracking['cycle_length'] ?? CycleSyncPhaseCalculator::DEFAULT_CYCLE_LENGTH,
            'cycleStart' => $tracking['cycle_start'] ?? null,
            'currentSyncPhase' => $currentId,
            'protocolMode' => $protocolMode->value,
            'dietProtocol' => $dietProtocol->value,
            'isCycleSync' => $isCycleSync,
            'loggedPeriods' => $loggedPeriods,
            'phaseCatalog' => $phases,
            'currentPhase' => collect($phases)->firstWhere('isCurrent', true),
            'mealPlanGenerationPayload' => $tracking['meal_plan_generation_payload'] ?? null,
            'mealPlanEffectiveFrom' => is_string($effectiveFrom) ? $effectiveFrom : null,
            'mealPlanChangeNotice' => is_string($effectiveFrom) && $effectiveFrom > Carbon::today()->toDateString()
                ? 'Your meal plan will change starting tomorrow.'
                : null,
        ];
    }
}
