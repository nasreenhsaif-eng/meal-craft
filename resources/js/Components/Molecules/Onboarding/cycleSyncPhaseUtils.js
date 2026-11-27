import { addDaysToIso, daysBetweenIso, toIsoDate } from '../Calendar/calendarDateUtils.js';
import { isDateInAnyLoggedPeriod } from './periodTrackingUtils.js';

/** @typedef {import('./periodTrackingUtils.js').LoggedPeriod} LoggedPeriod */

/** @typedef {'power_phase_1' | 'manifestation' | 'power_phase_2' | 'nurture_phase'} CycleSyncPhaseId */
/** @typedef {'non_fasting' | 'fasting'} CycleProtocolMode */

export const DEFAULT_CYCLE_LENGTH_DAYS = 30;
export const MIN_CYCLE_LENGTH_DAYS = 21;
export const MAX_CYCLE_LENGTH_DAYS = 45;
export const LOGGED_BLEED_COLOR = '#C44F5D';
export const DEFAULT_PROTOCOL_MODE = /** @type {CycleProtocolMode} */ ('non_fasting');
export const NURTURE_DAYS = 11;
export const POWER_PHASE_2_DAYS = 4;
export const MANIFESTATION_DAYS = 5;

export const FASTING_SAFETY_NOTE =
    'Fasting is not recommended if you are pregnant, breastfeeding, trying to conceive, underweight, or if you have a history of disordered eating or medical contraindications. Choose what feels safe for your body.';

/** @type {Record<CycleSyncPhaseId, string>} */
export const CYCLE_SYNC_PHASE_COLORS = {
    power_phase_1: '#6E8C47',
    manifestation: '#D8A933',
    power_phase_2: '#8F55A8',
    nurture_phase: '#2F4C9B',
};

/**
 * @typedef {{
 *   phaseName: string;
 *   biologicalPhase: string;
 *   subLabel: string;
 *   fastingWindow: string;
 *   fastingType: 'fasting' | 'no_fasting';
 *   foodApproach: string;
 *   whyDescription: string;
 *   protocolMode: CycleProtocolMode;
 *   schedulesFullDailyMeals: boolean;
 *   mealPlanCtaLabel: string;
 *   color: string;
 *   label: string;
 *   fasting: string;
 *   food: string;
 * }} CycleSyncPhaseGuidance
 */

/** @type {Record<CycleSyncPhaseId, Omit<CycleSyncPhaseGuidance, 'fastingWindow' | 'fastingType' | 'whyDescription' | 'protocolMode' | 'schedulesFullDailyMeals' | 'mealPlanCtaLabel' | 'fasting'>>} */
const CYCLE_SYNC_PHASE_BASE = {
    power_phase_1: {
        phaseName: 'Power Phase',
        biologicalPhase: 'Follicular Phase',
        subLabel: 'Follicular · Menstruation',
        foodApproach: 'Ketobiotic',
        color: '#6E8C47',
        label: 'Power Phase',
        food: 'Ketobiotic',
    },
    manifestation: {
        phaseName: 'Manifestation',
        biologicalPhase: 'Ovulation Window',
        subLabel: 'Late Follicular · Ovulation',
        foodApproach: 'Hormone Feasting',
        color: '#D8A933',
        label: 'Manifestation',
        food: 'Hormone Feasting',
    },
    power_phase_2: {
        phaseName: 'Power Phase',
        biologicalPhase: 'Early Luteal Phase',
        subLabel: 'Early Luteal',
        foodApproach: 'Ketobiotic',
        color: '#8F55A8',
        label: 'Power Phase',
        food: 'Ketobiotic',
    },
    nurture_phase: {
        phaseName: 'Nurture Phase',
        biologicalPhase: 'Mid–Late Luteal Phase',
        subLabel: 'Mid–Late Luteal',
        foodApproach: 'Hormone Feasting',
        color: '#2F4C9B',
        label: 'Nurture Phase',
        food: 'Hormone Feasting',
    },
};

/** @type {Record<CycleSyncPhaseId, { fastingWindow: string; fastingType: 'fasting' | 'no_fasting'; whyDescription: string }>} */
const FASTING_PHASE_DEFAULTS = {
    power_phase_1: {
        fastingWindow: '13–72 hours',
        fastingType: 'fasting',
        whyDescription:
            'Estrogen is rising in the follicular phase — lean into Ketobiotic meals with an optional 13–72 hour fasting window when it feels right for your body.',
    },
    manifestation: {
        fastingWindow: '13–15 hours',
        fastingType: 'fasting',
        whyDescription:
            'Around ovulation energy peaks — Hormone Feasting fuels performance while a lighter 13–15 hour overnight fast stays gentle.',
    },
    power_phase_2: {
        fastingWindow: '13–72 hours',
        fastingType: 'fasting',
        whyDescription:
            'Early luteal is still a strong metabolic window — keep Ketobiotic meals and the optional 13–72 hour fasting range if you opted in.',
    },
    nurture_phase: {
        fastingWindow: 'No Fasting',
        fastingType: 'no_fasting',
        whyDescription:
            'Mid–late luteal asks for nourishment — Hormone Feasting with regular meals and no fasting.',
    },
};

/** @type {Record<CycleSyncPhaseId, string>} */
const NON_FASTING_WHY = {
    power_phase_1:
        'Estrogen is rising in the follicular phase — focus on Ketobiotic meals with regular daily eating and no fasting windows.',
    manifestation:
        'Around ovulation energy peaks — Hormone Feasting supports you with regular meals and no fasting windows.',
    power_phase_2:
        'Early luteal still favors Ketobiotic meals — keep a full daily meal rhythm without fasting windows.',
    nurture_phase: 'Mid–late luteal asks for nourishment — Hormone Feasting with regular meals and no fasting.',
};

/** @type {Record<CycleSyncPhaseId, string>} */
const MEAL_PLAN_CTA_LABELS = {
    power_phase_1: 'Generate my follicular meal plan',
    manifestation: 'Generate my ovulation meal plan',
    power_phase_2: 'Generate my early luteal meal plan',
    nurture_phase: 'Generate my luteal meal plan',
};

/**
 * @param {unknown} value
 * @returns {CycleProtocolMode}
 */
export function normalizeProtocolMode(value) {
    return value === 'fasting' ? 'fasting' : DEFAULT_PROTOCOL_MODE;
}

/**
 * Nurture always resolves to no fasting, even when protocol_mode is fasting.
 *
 * @param {CycleSyncPhaseId} phaseId
 * @param {CycleProtocolMode | string | null | undefined} protocolMode
 */
export function effectiveFastingForPhase(phaseId, protocolMode = DEFAULT_PROTOCOL_MODE) {
    const mode = normalizeProtocolMode(protocolMode);
    const phaseRequiresNoFasting = phaseId === 'nurture_phase';
    const fastingActive = mode === 'fasting' && !phaseRequiresNoFasting;

    if (!fastingActive) {
        return {
            fastingWindow: 'Regular Meals · No Fasting',
            fastingType: /** @type {'no_fasting'} */ ('no_fasting'),
            whyDescription: NON_FASTING_WHY[phaseId],
            schedulesFullDailyMeals: true,
        };
    }

    const fasting = FASTING_PHASE_DEFAULTS[phaseId];

    return {
        fastingWindow: fasting.fastingWindow,
        fastingType: fasting.fastingType,
        whyDescription: fasting.whyDescription,
        schedulesFullDailyMeals: false,
    };
}

/**
 * @param {CycleSyncPhaseId} phaseId
 * @param {CycleProtocolMode | string | null | undefined} [protocolMode]
 * @returns {CycleSyncPhaseGuidance}
 */
export function phaseGuidanceFor(phaseId, protocolMode = DEFAULT_PROTOCOL_MODE) {
    const mode = normalizeProtocolMode(protocolMode);
    const base = CYCLE_SYNC_PHASE_BASE[phaseId];
    const effective = effectiveFastingForPhase(phaseId, mode);

    return {
        ...base,
        fastingWindow: effective.fastingWindow,
        fastingType: effective.fastingType,
        whyDescription: effective.whyDescription,
        protocolMode: mode,
        schedulesFullDailyMeals: effective.schedulesFullDailyMeals,
        mealPlanCtaLabel: MEAL_PLAN_CTA_LABELS[phaseId],
        fasting: effective.fastingWindow,
    };
}

/**
 * @param {CycleSyncPhaseId} phaseId
 * @param {CycleProtocolMode | string | null | undefined} [protocolMode]
 */
export function mealPlanGenerationPayloadFor(phaseId, protocolMode = DEFAULT_PROTOCOL_MODE) {
    const mode = normalizeProtocolMode(protocolMode);
    const guidance = phaseGuidanceFor(phaseId, mode);

    return {
        protocol_mode: mode,
        phase_key: phaseId,
        biological_phase: guidance.biologicalPhase,
        food_approach: guidance.foodApproach,
        fasting_window: guidance.fastingWindow,
        fasting_type: guidance.fastingType,
        schedules_full_daily_meals: guidance.schedulesFullDailyMeals,
        craft_key: guidance.schedulesFullDailyMeals ? 'full' : 'intermittent',
    };
}

/** Default catalog uses fasting-capable windows; callers should prefer phaseGuidanceFor(mode). */
/** @type {Record<CycleSyncPhaseId, CycleSyncPhaseGuidance>} */
export const CYCLE_SYNC_PHASE_GUIDANCE = {
    power_phase_1: phaseGuidanceFor('power_phase_1', 'fasting'),
    manifestation: phaseGuidanceFor('manifestation', 'fasting'),
    power_phase_2: phaseGuidanceFor('power_phase_2', 'fasting'),
    nurture_phase: phaseGuidanceFor('nurture_phase', 'fasting'),
};

/**
 * @param {number | null | undefined} cycleLength
 * @returns {number}
 */
export function normalizeCycleLength(cycleLength) {
    if (cycleLength == null || Number.isNaN(cycleLength)) {
        return DEFAULT_CYCLE_LENGTH_DAYS;
    }

    return Math.max(MIN_CYCLE_LENGTH_DAYS, Math.min(MAX_CYCLE_LENGTH_DAYS, Math.round(cycleLength)));
}

/**
 * @param {LoggedPeriod[]} periods
 * @returns {string[]}
 */
export function expandLoggedDates(periods) {
    /** @type {string[]} */
    const dates = [];

    for (const period of periods) {
        if (!period?.start) {
            continue;
        }

        const end = period.end || period.start;
        let cursor = period.start;
        const last = end < period.start ? period.start : end;

        while (cursor && cursor <= last) {
            dates.push(cursor);
            cursor = addDaysToIso(cursor, 1);
            if (!cursor) {
                break;
            }
        }
    }

    return [...new Set(dates)].sort();
}

/**
 * @param {string[]} dates
 * @returns {LoggedPeriod[]}
 */
export function collapseDatesToLoggedPeriods(dates) {
    const sorted = [...new Set(dates)].sort();

    if (sorted.length === 0) {
        return [];
    }

    /** @type {LoggedPeriod[]} */
    const periods = [];
    let start = sorted[0];
    let end = sorted[0];

    for (let index = 1; index < sorted.length; index += 1) {
        const current = sorted[index];
        const nextExpected = addDaysToIso(end, 1);

        if (current === nextExpected) {
            end = current;
            continue;
        }

        periods.push({ start, end });
        start = current;
        end = current;
    }

    periods.push({ start, end });

    return periods;
}

/**
 * Earliest date of the latest contiguous bleed cluster.
 *
 * @param {LoggedPeriod[]} periods
 * @returns {string | null}
 */
export function resolveCycleStart(periods) {
    const dates = expandLoggedDates(periods);

    if (dates.length === 0) {
        return null;
    }

    let clusterStart = dates[dates.length - 1];

    for (let index = dates.length - 2; index >= 0; index -= 1) {
        if (daysBetweenIso(dates[index], dates[index + 1]) === 1) {
            clusterStart = dates[index];
            continue;
        }

        break;
    }

    return clusterStart;
}

/**
 * @param {number} cycleLength
 * @returns {Record<CycleSyncPhaseId, { start: number; end: number }>}
 */
export function phaseRangesForCycleLength(cycleLength) {
    const length = normalizeCycleLength(cycleLength);
    const nurtureStart = Math.max(1, length - NURTURE_DAYS + 1);
    const power2End = nurtureStart - 1;
    const power2Start = Math.max(1, power2End - POWER_PHASE_2_DAYS + 1);
    const manifestEnd = power2Start - 1;
    const manifestStart = Math.max(1, manifestEnd - MANIFESTATION_DAYS + 1);
    const power1End = Math.max(0, manifestStart - 1);

    return {
        power_phase_1: { start: 1, end: power1End },
        manifestation: { start: manifestStart, end: manifestEnd },
        power_phase_2: { start: power2Start, end: power2End },
        nurture_phase: { start: nurtureStart, end: length },
    };
}

/**
 * @param {number} cycleDay
 * @param {number} cycleLength
 * @returns {CycleSyncPhaseId}
 */
export function phaseForCycleDay(cycleDay, cycleLength) {
    if (cycleDay < 1) {
        return 'power_phase_1';
    }

    const length = normalizeCycleLength(cycleLength);
    const ranges = phaseRangesForCycleLength(length);

    if (cycleDay > length) {
        return 'nurture_phase';
    }

    /** @type {CycleSyncPhaseId[]} */
    const order = ['nurture_phase', 'power_phase_2', 'manifestation', 'power_phase_1'];

    for (const phase of order) {
        const range = ranges[phase];

        if (cycleDay >= range.start && cycleDay <= range.end) {
            return phase;
        }
    }

    return 'power_phase_1';
}

/**
 * @param {string} cycleStart
 * @param {string} isoDate
 * @returns {number}
 */
export function cycleDayForDate(cycleStart, isoDate) {
    return daysBetweenIso(cycleStart, isoDate) + 1;
}

/**
 * @param {boolean} logged
 * @param {CycleSyncPhaseId} phase
 * @returns {string}
 */
export function colorForDate(logged, phase) {
    if (logged) {
        return LOGGED_BLEED_COLOR;
    }

    return CYCLE_SYNC_PHASE_COLORS[phase];
}

/**
 * @param {LoggedPeriod[]} loggedPeriods
 * @param {number} [cycleLength]
 * @param {Date} [referenceDate]
 */
export function buildCycleSyncCalendarState(
    loggedPeriods,
    cycleLength = DEFAULT_CYCLE_LENGTH_DAYS,
    referenceDate = new Date(),
    protocolMode = DEFAULT_PROTOCOL_MODE,
) {
    const length = normalizeCycleLength(cycleLength);
    const mode = normalizeProtocolMode(protocolMode);
    const cycleStart = resolveCycleStart(loggedPeriods);
    const loggedDates = expandLoggedDates(loggedPeriods);
    const phaseRanges = phaseRangesForCycleLength(length);
    const todayIso = toIsoDate(referenceDate);

    /** @type {Map<string, { date: string; cycleDay: number; phase: CycleSyncPhaseId; color: string; logged: boolean }>} */
    const byDate = new Map();

    const phaseCatalog = /** @type {CycleSyncPhaseId[]} */ ([
        'power_phase_1',
        'manifestation',
        'power_phase_2',
        'nurture_phase',
    ]).map((phaseId) => ({
        id: phaseId,
        ...phaseGuidanceFor(phaseId, mode),
        start: phaseRanges[phaseId].start,
        end: phaseRanges[phaseId].end,
    }));

    if (!cycleStart) {
        return {
            cycleStart: null,
            cycleLength: length,
            cycleDay: null,
            currentPhase: null,
            currentPhaseMetadata: null,
            protocolMode: mode,
            mealPlanGenerationPayload: null,
            phaseRanges,
            phaseCatalog,
            byDate,
            dates: [],
        };
    }

    const horizonDays = Math.max(
        length + 14,
        daysBetweenIso(cycleStart, todayIso) + 7,
    );

    /** @type {Array<{ date: string; cycleDay: number; phase: CycleSyncPhaseId; color: string; logged: boolean }>} */
    const dates = [];

    for (let offset = 0; offset <= horizonDays; offset += 1) {
        const date = addDaysToIso(cycleStart, offset);

        if (!date) {
            break;
        }

        const cycleDay = cycleDayForDate(cycleStart, date);
        const phase = phaseForCycleDay(cycleDay, length);
        const logged = loggedDates.includes(date);
        const entry = {
            date,
            cycleDay,
            phase,
            color: colorForDate(logged, phase),
            logged,
        };

        dates.push(entry);
        byDate.set(date, entry);
    }

    const todayCycleDay = cycleDayForDate(cycleStart, todayIso);
    const currentPhase = phaseForCycleDay(todayCycleDay, length);
    const currentPhaseMetadata = phaseGuidanceFor(currentPhase, mode);

    return {
        cycleStart,
        cycleLength: length,
        cycleDay: todayCycleDay,
        currentPhase,
        currentPhaseMetadata,
        protocolMode: mode,
        mealPlanGenerationPayload: mealPlanGenerationPayloadFor(currentPhase, mode),
        phaseRanges,
        phaseCatalog,
        byDate,
        dates,
    };
}

/**
 * Toggle a bleed day and collapse contiguous dates into logged period ranges.
 *
 * @param {LoggedPeriod[]} loggedPeriods
 * @param {string} iso
 * @returns {LoggedPeriod[]}
 */
export function toggleLoggedBleedDay(loggedPeriods, iso) {
    const dates = new Set(expandLoggedDates(loggedPeriods));

    if (dates.has(iso)) {
        dates.delete(iso);
    } else {
        dates.add(iso);
    }

    return collapseDatesToLoggedPeriods([...dates]);
}

/**
 * @param {string} iso
 * @param {LoggedPeriod[]} loggedPeriods
 * @param {ReturnType<typeof buildCycleSyncCalendarState>} state
 * @returns {{ logged: boolean; phase: CycleSyncPhaseId | null; color: string | null; cycleDay: number | null }}
 */
export function resolveCycleSyncDayVisualState(iso, loggedPeriods, state) {
    const logged = isDateInAnyLoggedPeriod(iso, loggedPeriods) || Boolean(state.byDate.get(iso)?.logged);
    const entry = state.byDate.get(iso);

    if (entry) {
        return {
            logged,
            phase: entry.phase,
            color: logged ? LOGGED_BLEED_COLOR : entry.color,
            cycleDay: entry.cycleDay,
        };
    }

    if (!state.cycleStart) {
        return {
            logged,
            phase: null,
            color: logged ? LOGGED_BLEED_COLOR : null,
            cycleDay: null,
        };
    }

    const cycleDay = cycleDayForDate(state.cycleStart, iso);

    if (cycleDay < 1) {
        return {
            logged,
            phase: null,
            color: logged ? LOGGED_BLEED_COLOR : null,
            cycleDay,
        };
    }

    const phase = phaseForCycleDay(cycleDay, state.cycleLength);

    return {
        logged,
        phase,
        color: colorForDate(logged, phase),
        cycleDay,
    };
}
