import { describe, expect, it } from 'vitest';
import {
    averagePeriodBleedingDays,
    buildHistoricalCyclePhase,
    buildProjectedCycle,
    buildProjectedCycles,
    DEFAULT_CYCLE_LENGTH_DAYS,
    projectedPeriodStartIso,
    resolveAnchorDate,
    resolveAverageCycleLengthMetric,
    resolveCycleLengthDays,
    resolveDayCellVisualState,
} from './cyclePredictionUtils.js';

describe('resolveAnchorDate', () => {
    it('returns the most recent logged period start', () => {
        const anchor = resolveAnchorDate([
            { start: '2026-03-18', end: '2026-03-23' },
            { start: '2026-04-20', end: '2026-04-26' },
        ]);

        expect(anchor).toBe('2026-04-20');
    });
});

describe('resolveCycleLengthDays', () => {
    it('falls back to 28 days when average cycle length is under 21', () => {
        const length = resolveCycleLengthDays([
            { start: '2026-03-01', end: '2026-03-05' },
            { start: '2026-03-10', end: '2026-03-14' },
        ]);

        expect(length).toBe(DEFAULT_CYCLE_LENGTH_DAYS);
    });

    it('uses the rounded average when cycles are at least 21 days apart', () => {
        const length = resolveCycleLengthDays([
            { start: '2026-03-18', end: '2026-03-23' },
            { start: '2026-04-20', end: '2026-04-26' },
        ]);

        expect(length).toBe(33);
    });
});

describe('averagePeriodBleedingDays', () => {
    it('defaults to five days when no periods are logged', () => {
        expect(averagePeriodBleedingDays([])).toBe(5);
    });

    it('averages bleeding duration across every logged period', () => {
        expect(
            averagePeriodBleedingDays([
                { start: '2026-01-01', end: '2026-01-05' },
                { start: '2026-02-01', end: '2026-02-04' },
                { start: '2026-03-03', end: '2026-03-08' },
            ]),
        ).toBe(5);
    });
});

describe('resolveAverageCycleLengthMetric', () => {
    it('defaults to the standard length when fewer than two periods are logged', () => {
        expect(resolveAverageCycleLengthMetric([])).toEqual({
            days: DEFAULT_CYCLE_LENGTH_DAYS,
            isStandard: true,
        });
    });

    it('averages consecutive start-date gaps and rounds to the nearest whole day', () => {
        expect(
            resolveAverageCycleLengthMetric([
                { start: '2026-01-01', end: '2026-01-05' },
                { start: '2026-02-01', end: '2026-02-05' },
                { start: '2026-03-03', end: '2026-03-07' },
                { start: '2026-03-31', end: '2026-04-04' },
            ]),
        ).toEqual({
            days: 30,
            isStandard: false,
        });
    });
});

describe('buildHistoricalCyclePhase', () => {
    it('counts backward from the logged period start date', () => {
        expect(buildHistoricalCyclePhase({ start: '2026-04-20', end: '2026-04-26' })).toEqual({
            loggedPeriodStart: '2026-04-20',
            pastOvulation: '2026-04-06',
            pastFertileStart: '2026-04-01',
            pastFertileEnd: '2026-04-07',
        });
    });
});

describe('buildProjectedCycle', () => {
    it('derives all phase dates from the anchor and cycle index', () => {
        const cycle = buildProjectedCycle('2026-03-31', 1, 28);

        expect(cycle).toEqual({
            cycleIndex: 1,
            periodStart: '2026-04-28',
            periodEnd: '2026-05-02',
            nextPeriodStart: '2026-05-26',
            ovulationDate: '2026-05-12',
            fertileStart: '2026-05-07',
            fertileEnd: '2026-05-13',
        });
    });
});

describe('buildProjectedCycles', () => {
    const referenceDate = new Date('2026-05-23T12:00:00');
    const loggedPeriods = [
        { start: '2026-03-18', end: '2026-03-23' },
        { start: '2026-04-20', end: '2026-04-26' },
    ];

    it('returns a flat array of projected cycles within the forecast horizon', () => {
        const projection = buildProjectedCycles(loggedPeriods, referenceDate);

        expect(projection.anchorDate).toBe('2026-04-20');
        expect(projection.cycleLength).toBe(33);
        expect(projection.projectedCycles.length).toBeGreaterThan(0);
        expect(projection.projectedCycles[0].periodStart).toBe(
            projectedPeriodStartIso('2026-04-20', 0, 33),
        );
        expect(projection.projectedCycles[1].periodStart).toBe(
            projectedPeriodStartIso('2026-04-20', 1, 33),
        );
    });
});

describe('resolveDayCellVisualState', () => {
    const loggedPeriods = [{ start: '2026-04-20', end: '2026-04-26' }];
    const referenceDate = new Date('2026-05-23T12:00:00');
    const projection = buildProjectedCycles(loggedPeriods, referenceDate);

    it('prioritizes logged periods over predictions', () => {
        expect(
            resolveDayCellVisualState(
                '2026-04-22',
                { start: null, end: null },
                loggedPeriods,
                projection,
                referenceDate,
            ).logged,
        ).toBe(true);
    });

    it('marks ovulation day with ovulation and fertile flags', () => {
        const ovulationDay = projection.projectedCycles[0].ovulationDate;
        const state = resolveDayCellVisualState(
            ovulationDay,
            { start: null, end: null },
            loggedPeriods,
            projection,
            referenceDate,
        );

        expect(state.isOvulation).toBe(true);
        expect(state.isFertile).toBe(true);
    });

    it('marks predicted period days without ovulation styling', () => {
        const periodDay = projection.projectedCycles[1].periodStart;
        const state = resolveDayCellVisualState(
            periodDay,
            { start: null, end: null },
            loggedPeriods,
            projection,
            referenceDate,
        );

        expect(state.isPredictedPeriod).toBe(true);
        expect(state.isOvulation).toBe(false);
    });

    it('marks fertile days that are not ovulation', () => {
        const fertileDay = projection.projectedCycles[0].fertileStart;
        const state = resolveDayCellVisualState(
            fertileDay,
            { start: null, end: null },
            loggedPeriods,
            projection,
            referenceDate,
        );

        expect(state.isFertile).toBe(true);
        expect(state.isOvulation).toBe(false);
    });

    it('renders historical ovulation and fertile markers for past months', () => {
        const historicalPeriods = [
            { start: '2026-03-18', end: '2026-03-23' },
            { start: '2026-04-20', end: '2026-04-26' },
        ];
        const historicalProjection = buildProjectedCycles(historicalPeriods, referenceDate);
        const aprilPhase = historicalProjection.historicalPhases.find(
            (phase) => phase.loggedPeriodStart === '2026-04-20',
        );

        const ovulationState = resolveDayCellVisualState(
            aprilPhase.pastOvulation,
            { start: null, end: null },
            historicalPeriods,
            historicalProjection,
            referenceDate,
        );

        expect(ovulationState.isOvulation).toBe(true);
        expect(ovulationState.isFertile).toBe(true);

        const fertileState = resolveDayCellVisualState(
            aprilPhase.pastFertileStart,
            { start: null, end: null },
            historicalPeriods,
            historicalProjection,
            referenceDate,
        );

        expect(fertileState.isFertile).toBe(true);
        expect(fertileState.isOvulation).toBe(false);
    });

    it('keeps logged period styling above historical fertile overlays', () => {
        const historicalPeriods = [{ start: '2026-04-20', end: '2026-04-26' }];
        const historicalProjection = buildProjectedCycles(historicalPeriods, referenceDate);

        expect(
            resolveDayCellVisualState(
                '2026-04-22',
                { start: null, end: null },
                historicalPeriods,
                historicalProjection,
                referenceDate,
            ),
        ).toEqual({
            logged: true,
            isOvulation: false,
            isFertile: false,
            isPredictedPeriod: false,
        });
    });

    it('marks the current-cycle fertile window in the anchor month', () => {
        const referenceDate = new Date('2026-06-09T12:00:00');
        const periods = [{ start: '2026-06-01', end: '2026-06-05' }];
        const projection = buildProjectedCycles(periods, referenceDate);
        const currentCycle = projection.projectedCycles[0];

        expect(currentCycle.periodStart).toBe('2026-06-01');
        expect(currentCycle.fertileStart).toBe('2026-06-10');
        expect(currentCycle.fertileEnd).toBe('2026-06-16');

        const fertileState = resolveDayCellVisualState(
            currentCycle.fertileStart,
            { start: null, end: null },
            periods,
            projection,
            referenceDate,
        );

        expect(fertileState.isFertile).toBe(true);
        expect(fertileState.isOvulation).toBe(false);
    });

    it('uses every logged month when averaging cycle length', () => {
        const periods = [
            { start: '2026-01-01', end: '2026-01-05' },
            { start: '2026-02-01', end: '2026-02-05' },
            { start: '2026-03-03', end: '2026-03-07' },
            { start: '2026-04-02', end: '2026-04-06' },
        ];

        expect(resolveAverageCycleLengthMetric(periods)).toEqual({
            days: 30,
            isStandard: false,
        });
    });
});
