import { describe, expect, it } from 'vitest';
import {
    buildCycleSyncCalendarState,
    CYCLE_SYNC_PHASE_COLORS,
    CYCLE_SYNC_PHASE_GUIDANCE,
    DEFAULT_CYCLE_LENGTH_DAYS,
    effectiveFastingForPhase,
    LOGGED_BLEED_COLOR,
    mealPlanGenerationPayloadFor,
    phaseGuidanceFor,
    phaseRangesForCycleLength,
    resolveCycleStart,
    resolveCycleSyncDayVisualState,
    toggleLoggedBleedDay,
} from './cycleSyncPhaseUtils.js';

describe('cycleSyncPhaseUtils', () => {
    it('defaults cycle length to 30', () => {
        expect(DEFAULT_CYCLE_LENGTH_DAYS).toBe(30);
    });

    it('exposes biological subtitles for every protocol phase', () => {
        expect(CYCLE_SYNC_PHASE_GUIDANCE.power_phase_1).toMatchObject({
            phaseName: 'Power Phase',
            biologicalPhase: 'Follicular Phase',
            subLabel: 'Follicular · Menstruation',
            fastingType: 'fasting',
        });
        expect(CYCLE_SYNC_PHASE_GUIDANCE.manifestation).toMatchObject({
            biologicalPhase: 'Ovulation Window',
            subLabel: 'Late Follicular · Ovulation',
        });
        expect(CYCLE_SYNC_PHASE_GUIDANCE.power_phase_2).toMatchObject({
            biologicalPhase: 'Early Luteal Phase',
            subLabel: 'Early Luteal',
        });
        expect(CYCLE_SYNC_PHASE_GUIDANCE.nurture_phase).toMatchObject({
            biologicalPhase: 'Mid–Late Luteal Phase',
            subLabel: 'Mid–Late Luteal',
            fastingType: 'no_fasting',
        });
    });

    it('respects non_fasting and fasting protocol modes across all four phases', () => {
        /** @type {Array<'power_phase_1' | 'manifestation' | 'power_phase_2' | 'nurture_phase'>} */
        const phases = ['power_phase_1', 'manifestation', 'power_phase_2', 'nurture_phase'];

        for (const phase of phases) {
            const nonFasting = effectiveFastingForPhase(phase, 'non_fasting');
            const fasting = effectiveFastingForPhase(phase, 'fasting');

            expect(nonFasting).toMatchObject({
                fastingType: 'no_fasting',
                fastingWindow: 'Regular Meals · No Fasting',
                schedulesFullDailyMeals: true,
            });
            expect(nonFasting.whyDescription).not.toMatch(/13–72|13–15/);

            if (phase === 'nurture_phase') {
                expect(fasting.fastingType).toBe('no_fasting');
                expect(fasting.schedulesFullDailyMeals).toBe(true);
            } else {
                expect(fasting.fastingType).toBe('fasting');
                expect(fasting.schedulesFullDailyMeals).toBe(false);
            }

            expect(mealPlanGenerationPayloadFor(phase, 'non_fasting').craft_key).toBe('full');
            expect(mealPlanGenerationPayloadFor(phase, 'fasting').craft_key).toBe(
                phase === 'nurture_phase' ? 'full' : 'intermittent',
            );
            expect(phaseGuidanceFor(phase, 'non_fasting').protocolMode).toBe('non_fasting');
        }
    });

    it('updates active phase metadata immediately when protocol mode changes', () => {
        const periods = [{ start: '2026-04-20', end: '2026-04-21' }];
        const referenceDate = new Date(2026, 3, 25);
        const nonFasting = buildCycleSyncCalendarState(periods, 30, referenceDate, 'non_fasting');
        const fasting = buildCycleSyncCalendarState(periods, 30, referenceDate, 'fasting');

        expect(nonFasting.currentPhase).toBe('power_phase_1');
        expect(nonFasting.currentPhaseMetadata?.fastingWindow).toBe('Regular Meals · No Fasting');
        expect(nonFasting.mealPlanGenerationPayload?.craft_key).toBe('full');
        expect(fasting.currentPhaseMetadata?.fastingWindow).toBe('13–72 hours');
        expect(fasting.mealPlanGenerationPayload?.craft_key).toBe('intermittent');
    });

    it('builds luteal-anchored ranges for common cycle lengths', () => {
        expect(phaseRangesForCycleLength(30)).toEqual({
            power_phase_1: { start: 1, end: 10 },
            manifestation: { start: 11, end: 15 },
            power_phase_2: { start: 16, end: 19 },
            nurture_phase: { start: 20, end: 30 },
        });
        expect(phaseRangesForCycleLength(26).power_phase_1).toEqual({ start: 1, end: 6 });
        expect(phaseRangesForCycleLength(34).power_phase_1).toEqual({ start: 1, end: 14 });
    });

    it('toggles bleed days and immediately updates crimson and phase colors', () => {
        const referenceDate = new Date(2026, 3, 22);
        let periods = [];

        periods = toggleLoggedBleedDay(periods, '2026-04-20');
        expect(periods).toEqual([{ start: '2026-04-20', end: '2026-04-20' }]);

        periods = toggleLoggedBleedDay(periods, '2026-04-21');
        expect(periods).toEqual([{ start: '2026-04-20', end: '2026-04-21' }]);

        const state = buildCycleSyncCalendarState(periods, 30, referenceDate);
        const logged = resolveCycleSyncDayVisualState('2026-04-20', periods, state);
        const power1 = resolveCycleSyncDayVisualState('2026-04-25', periods, state);
        const manifestation = resolveCycleSyncDayVisualState('2026-04-30', periods, state);

        expect(logged).toMatchObject({
            logged: true,
            color: LOGGED_BLEED_COLOR,
            phase: 'power_phase_1',
            cycleDay: 1,
        });
        expect(power1).toMatchObject({
            logged: false,
            color: CYCLE_SYNC_PHASE_COLORS.power_phase_1,
            phase: 'power_phase_1',
        });
        expect(manifestation).toMatchObject({
            logged: false,
            color: CYCLE_SYNC_PHASE_COLORS.manifestation,
            phase: 'manifestation',
        });
    });

    it('resets day one when a new bleed start is logged after a gap', () => {
        let periods = [{ start: '2026-03-01', end: '2026-03-03' }];

        periods = toggleLoggedBleedDay(periods, '2026-04-01');

        expect(resolveCycleStart(periods)).toBe('2026-04-01');

        const state = buildCycleSyncCalendarState(periods, 30, new Date(2026, 3, 2));

        expect(state.cycleStart).toBe('2026-04-01');
        expect(state.byDate.get('2026-04-01')?.cycleDay).toBe(1);
        expect(state.byDate.get('2026-04-01')?.color).toBe(LOGGED_BLEED_COLOR);
    });

    it('keeps days past cycle length in nurture phase', () => {
        const periods = [{ start: '2026-04-01', end: '2026-04-03' }];
        const state = buildCycleSyncCalendarState(periods, 30, new Date(2026, 4, 5));
        const pastLength = resolveCycleSyncDayVisualState('2026-05-05', periods, state);

        expect(pastLength.phase).toBe('nurture_phase');
        expect(pastLength.color).toBe(CYCLE_SYNC_PHASE_COLORS.nurture_phase);
        expect(pastLength.cycleDay).toBeGreaterThan(30);
    });
});
