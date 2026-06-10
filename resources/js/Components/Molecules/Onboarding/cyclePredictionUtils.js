import { addDaysToIso, addMonthsToDate, daysBetweenIso, toIsoDate } from '../Calendar/calendarDateUtils.js';
import { isDateInAnyLoggedPeriod } from './periodTrackingUtils.js';

/** @typedef {import('./periodTrackingUtils.js').LoggedPeriod} LoggedPeriod */

export const DEFAULT_CYCLE_LENGTH_DAYS = 28;
export const MIN_CYCLE_LENGTH_DAYS = 21;
export const PERIOD_BLEEDING_DAYS = 5;
export const PERIOD_END_OFFSET_DAYS = PERIOD_BLEEDING_DAYS - 1;
export const OVULATION_DAYS_BEFORE_NEXT_PERIOD = 14;
export const FERTILE_DAYS_BEFORE_OVULATION = 5;
export const FERTILE_DAYS_AFTER_OVULATION = 1;
export const FORECAST_MONTHS_AHEAD = 3;

/**
 * @typedef {{
 *   cycleIndex: number;
 *   periodStart: string;
 *   periodEnd: string;
 *   nextPeriodStart: string;
 *   ovulationDate: string;
 *   fertileStart: string;
 *   fertileEnd: string;
 * }} ProjectedCycle
 */

/**
 * @typedef {{
 *   loggedPeriodStart: string;
 *   pastOvulation: string;
 *   pastFertileStart: string;
 *   pastFertileEnd: string;
 * }} HistoricalCyclePhase
 */

/**
 * @typedef {{
 *   anchorDate: string | null;
 *   cycleLength: number;
 *   projectedCycles: ProjectedCycle[];
 *   historicalPhases: HistoricalCyclePhase[];
 * }} CycleProjection
 */

/**
 * @typedef {{
 *   logged: boolean;
 *   isOvulation: boolean;
 *   isFertile: boolean;
 *   isPredictedPeriod: boolean;
 * }} DayCellVisualState
 */

/**
 * @param {LoggedPeriod[]} periods
 */
export function resolveAnchorDate(periods) {
    if (periods.length === 0) {
        return null;
    }

    return [...periods].sort((a, b) => b.start.localeCompare(a.start))[0].start;
}

/**
 * @param {LoggedPeriod[]} periods
 */
export function averagePeriodBleedingDays(periods) {
    if (periods.length === 0) {
        return PERIOD_BLEEDING_DAYS;
    }

    let totalDays = 0;

    for (const period of periods) {
        totalDays += daysBetweenIso(period.start, period.end) + 1;
    }

    const average = Math.round(totalDays / periods.length);

    return average > 0 ? average : PERIOD_BLEEDING_DAYS;
}

/**
 * @param {LoggedPeriod[]} periods
 */
export function averageCycleLengthDays(periods) {
    if (periods.length < 2) {
        return DEFAULT_CYCLE_LENGTH_DAYS;
    }

    const sorted = [...periods].sort((a, b) => a.start.localeCompare(b.start));
    let totalDays = 0;

    for (let index = 1; index < sorted.length; index += 1) {
        totalDays += daysBetweenIso(sorted[index - 1].start, sorted[index].start);
    }

    const average = Math.round(totalDays / (sorted.length - 1));

    return average > 0 ? average : DEFAULT_CYCLE_LENGTH_DAYS;
}

/**
 * @typedef {{
 *   days: number;
 *   isStandard: boolean;
 * }} AverageCycleLengthMetric
 */

/**
 * Resolve the display and payload value for average cycle length.
 *
 * @param {LoggedPeriod[]} periods
 * @returns {AverageCycleLengthMetric}
 */
export function resolveAverageCycleLengthMetric(periods) {
    if (periods.length < 2) {
        return {
            days: DEFAULT_CYCLE_LENGTH_DAYS,
            isStandard: true,
        };
    }

    return {
        days: averageCycleLengthDays(periods),
        isStandard: false,
    };
}

/**
 * Clamp anomalous short test cycles to the standard default length.
 *
 * @param {LoggedPeriod[]} periods
 */
export function resolveCycleLengthDays(periods) {
    const average = averageCycleLengthDays(periods);

    return average < MIN_CYCLE_LENGTH_DAYS ? DEFAULT_CYCLE_LENGTH_DAYS : average;
}

/**
 * Linear projection: anchor + (cycleIndex * cycleLength).
 *
 * @param {string} anchorDate
 * @param {number} cycleIndex
 * @param {number} cycleLength
 */
export function projectedPeriodStartIso(anchorDate, cycleIndex, cycleLength) {
    return addDaysToIso(anchorDate, cycleIndex * cycleLength);
}

/**
 * @param {string} iso
 * @param {string} startIso
 * @param {string} endIso
 */
export function isIsoInInclusiveRange(iso, startIso, endIso) {
    return iso >= startIso && iso <= endIso;
}

/**
 * @param {string} anchorDate
 * @param {number} cycleIndex
 * @param {number} cycleLength
 * @param {number} [periodBleedingDays]
 * @returns {ProjectedCycle | null}
 */
export function buildProjectedCycle(
    anchorDate,
    cycleIndex,
    cycleLength,
    periodBleedingDays = PERIOD_BLEEDING_DAYS,
) {
    const periodStart = projectedPeriodStartIso(anchorDate, cycleIndex, cycleLength);

    if (!periodStart) {
        return null;
    }

    const bleedingOffset = Math.max(0, periodBleedingDays - 1);
    const periodEnd = addDaysToIso(periodStart, bleedingOffset);
    const nextPeriodStart = addDaysToIso(periodStart, cycleLength);
    const ovulationDate = nextPeriodStart
        ? addDaysToIso(nextPeriodStart, -OVULATION_DAYS_BEFORE_NEXT_PERIOD)
        : null;
    const fertileStart = ovulationDate ? addDaysToIso(ovulationDate, -FERTILE_DAYS_BEFORE_OVULATION) : null;
    const fertileEnd = ovulationDate ? addDaysToIso(ovulationDate, FERTILE_DAYS_AFTER_OVULATION) : null;

    if (!periodEnd || !nextPeriodStart || !ovulationDate || !fertileStart || !fertileEnd) {
        return null;
    }

    return {
        cycleIndex,
        periodStart,
        periodEnd,
        nextPeriodStart,
        ovulationDate,
        fertileStart,
        fertileEnd,
    };
}

/**
 * Derive historical ovulation and fertile phases backward from a logged period start.
 *
 * @param {LoggedPeriod} period
 * @returns {HistoricalCyclePhase | null}
 */
export function buildHistoricalCyclePhase(period) {
    const pastOvulation = addDaysToIso(period.start, -OVULATION_DAYS_BEFORE_NEXT_PERIOD);

    if (!pastOvulation) {
        return null;
    }

    const pastFertileStart = addDaysToIso(pastOvulation, -FERTILE_DAYS_BEFORE_OVULATION);
    const pastFertileEnd = addDaysToIso(pastOvulation, FERTILE_DAYS_AFTER_OVULATION);

    if (!pastFertileStart || !pastFertileEnd) {
        return null;
    }

    return {
        loggedPeriodStart: period.start,
        pastOvulation,
        pastFertileStart,
        pastFertileEnd,
    };
}

/**
 * Build historical phase markers for every logged period entry.
 *
 * @param {LoggedPeriod[]} loggedPeriods
 * @returns {HistoricalCyclePhase[]}
 */
export function buildHistoricalCyclePhases(loggedPeriods) {
    /** @type {HistoricalCyclePhase[]} */
    const historicalPhases = [];

    for (const period of loggedPeriods) {
        const phase = buildHistoricalCyclePhase(period);

        if (phase) {
            historicalPhases.push(phase);
        }
    }

    return historicalPhases;
}

/**
 * Build a flat array of future projected cycles for the forecast horizon.
 *
 * @param {LoggedPeriod[]} loggedPeriods
 * @param {Date} [referenceDate]
 * @param {number} [monthsAhead]
 * @returns {CycleProjection}
 */
export function buildProjectedCycles(
    loggedPeriods,
    referenceDate = new Date(),
    monthsAhead = FORECAST_MONTHS_AHEAD,
) {
    const anchorDate = resolveAnchorDate(loggedPeriods);
    const cycleLength = resolveCycleLengthDays(loggedPeriods);
    const periodBleedingDays = averagePeriodBleedingDays(loggedPeriods);
    const historicalPhases = buildHistoricalCyclePhases(loggedPeriods);
    /** @type {ProjectedCycle[]} */
    const projectedCycles = [];

    if (!anchorDate) {
        return {
            anchorDate,
            cycleLength,
            projectedCycles,
            historicalPhases,
        };
    }

    const horizonEndIso = toIsoDate(addMonthsToDate(referenceDate, monthsAhead));
    const spanDays = Math.max(daysBetweenIso(anchorDate, horizonEndIso), cycleLength);
    const maxCycleIndex = Math.ceil(spanDays / cycleLength) + 1;

    for (let cycleIndex = 0; cycleIndex <= maxCycleIndex; cycleIndex += 1) {
        const cycle = buildProjectedCycle(anchorDate, cycleIndex, cycleLength, periodBleedingDays);

        if (!cycle) {
            break;
        }

        if (cycleIndex > 0 && cycle.periodStart > horizonEndIso) {
            break;
        }

        projectedCycles.push(cycle);
    }

    return {
        anchorDate,
        cycleLength,
        projectedCycles,
        historicalPhases,
    };
}

/**
 * @param {string} iso
 * @param {HistoricalCyclePhase[]} historicalPhases
 * @param {string} todayIso
 */
function resolveHistoricalDayFlags(iso, historicalPhases, todayIso) {
    let isOvulation = false;
    let isFertile = false;

    if (iso > todayIso) {
        return { isOvulation, isFertile };
    }

    for (const phase of historicalPhases) {
        if (iso === phase.pastOvulation) {
            isOvulation = true;
        }

        if (isIsoInInclusiveRange(iso, phase.pastFertileStart, phase.pastFertileEnd)) {
            isFertile = true;
        }
    }

    return { isOvulation, isFertile };
}

/**
 * @param {string} iso
 * @param {ProjectedCycle[]} projectedCycles
 * @param {string} todayIso
 * @param {string} horizonEndIso
 * @param {LoggedPeriod[]} loggedPeriods
 */
function resolveProjectedDayFlags(iso, projectedCycles, todayIso, horizonEndIso, loggedPeriods) {
    let isOvulation = false;
    let isFertile = false;
    let isPredictedPeriod = false;

    for (const cycle of projectedCycles) {
        if (iso > horizonEndIso) {
            continue;
        }

        if (iso === cycle.ovulationDate) {
            isOvulation = true;
        }

        if (isIsoInInclusiveRange(iso, cycle.fertileStart, cycle.fertileEnd)) {
            isFertile = true;
        }

        if (
            iso >= todayIso &&
            isIsoInInclusiveRange(iso, cycle.periodStart, cycle.periodEnd) &&
            !isDateInAnyLoggedPeriod(iso, loggedPeriods)
        ) {
            isPredictedPeriod = true;
        }
    }

    return { isOvulation, isFertile, isPredictedPeriod };
}

/**
 * Resolve calendar cell styling flags against pre-computed projected and historical cycles.
 *
 * Priority for forecast overlays:
 * 1. Ovulation day marker (dot + purple ring/text; fertile tint also applies)
 * 2. Fertile window (includes ovulation day)
 * 3. Predicted period bleeding window
 *
 * Logged periods and draft selections always take precedence over all overlays.
 *
 * @param {string} iso
 * @param {{ start: string | null; end: string | null }} draftRange
 * @param {LoggedPeriod[]} loggedPeriods
 * @param {CycleProjection} projection
 * @param {Date} [referenceDate]
 * @returns {DayCellVisualState}
 */
export function resolveDayCellVisualState(iso, draftRange, loggedPeriods, projection, referenceDate = new Date()) {
    const inDraft =
        draftRange.start !== null &&
        draftRange.start !== undefined &&
        (draftRange.end
            ? iso >= draftRange.start && iso <= draftRange.end
            : iso === draftRange.start);

    if (isDateInAnyLoggedPeriod(iso, loggedPeriods) || inDraft) {
        return {
            logged: true,
            isOvulation: false,
            isFertile: false,
            isPredictedPeriod: false,
        };
    }

    const todayIso = toIsoDate(referenceDate);
    const horizonEndIso = toIsoDate(addMonthsToDate(referenceDate, FORECAST_MONTHS_AHEAD));
    const historical = resolveHistoricalDayFlags(iso, projection.historicalPhases, todayIso);
    const projected = resolveProjectedDayFlags(
        iso,
        projection.projectedCycles,
        todayIso,
        horizonEndIso,
        loggedPeriods,
    );

    return {
        logged: false,
        isOvulation: historical.isOvulation || projected.isOvulation,
        isFertile: historical.isFertile || projected.isFertile,
        isPredictedPeriod: projected.isPredictedPeriod,
    };
}

/**
 * @param {Date} viewMonth
 * @param {Date} [referenceDate]
 * @param {number} [monthsAhead]
 */
export function canViewNextForecastMonth(
    viewMonth,
    referenceDate = new Date(),
    monthsAhead = FORECAST_MONTHS_AHEAD,
) {
    const horizon = addMonthsToDate(referenceDate, monthsAhead);

    return (
        viewMonth.getFullYear() < horizon.getFullYear() ||
        (viewMonth.getFullYear() === horizon.getFullYear() && viewMonth.getMonth() < horizon.getMonth())
    );
}

/**
 * @param {string | null | undefined} iso
 * @param {Date} [referenceDate]
 */
export function isIsoInFuture(iso, referenceDate = new Date()) {
    if (!iso) {
        return false;
    }

    return iso > toIsoDate(referenceDate);
}
