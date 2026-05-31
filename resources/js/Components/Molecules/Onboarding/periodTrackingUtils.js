import { parseIsoDate } from '../Calendar/calendarDateUtils.js';

/**
 * @typedef {{ start: string; end: string }} LoggedPeriod
 */

/**
 * @param {string} iso
 * @param {string | null | undefined} start
 * @param {string | null | undefined} end
 */
export function isDateInRange(iso, start, end) {
    if (!start) {
        return false;
    }

    const rangeEnd = end ?? start;

    return iso >= start && iso <= rangeEnd;
}

/**
 * @param {string} iso
 * @param {{ start: string | null; end?: string | null }} range
 * @returns {'single' | 'endpoint' | 'middle' | null}
 */
export function getDayRangeRole(iso, range) {
    const start = range.start;
    const end = range.end ?? null;

    if (!start || !isDateInRange(iso, start, end)) {
        return null;
    }

    if (!end || start === end) {
        return 'single';
    }

    if (iso === start || iso === end) {
        return 'endpoint';
    }

    return 'middle';
}

/**
 * @param {LoggedPeriod[]} periods
 * @param {string} iso
 */
export function isDateInAnyLoggedPeriod(iso, periods) {
    return periods.some((period) => isDateInRange(iso, period.start, period.end));
}

/**
 * Resolve highlight role from draft selection and/or logged historical ranges.
 *
 * @param {string} iso
 * @param {{ start: string | null; end: string | null }} draftRange
 * @param {LoggedPeriod[]} loggedPeriods
 * @returns {'single' | 'endpoint' | 'middle' | null}
 */
export function resolveCombinedDayRangeRole(iso, draftRange, loggedPeriods) {
    const roles = [];

    const draftRole = getDayRangeRole(iso, draftRange);

    if (draftRole) {
        roles.push(draftRole);
    }

    for (const period of loggedPeriods) {
        const loggedRole = getDayRangeRole(iso, period);

        if (loggedRole) {
            roles.push(loggedRole);
        }
    }

    if (roles.length === 0) {
        return null;
    }

    if (roles.includes('single')) {
        return 'single';
    }

    if (roles.includes('endpoint')) {
        return 'endpoint';
    }

    return 'middle';
}

/**
 * @param {string} iso
 * @param {{ start: string | null; end: string | null }} draftRange
 * @param {LoggedPeriod[]} loggedPeriods
 * @returns {{ start: string; end: string } | null}
 */
export function findContainingRange(iso, draftRange, loggedPeriods) {
    if (isDateInRange(iso, draftRange.start, draftRange.end)) {
        return {
            start: draftRange.start,
            end: draftRange.end ?? draftRange.start,
        };
    }

    for (const period of loggedPeriods) {
        if (isDateInRange(iso, period.start, period.end)) {
            return period;
        }
    }

    return null;
}

/**
 * @param {string} iso
 * @param {{ start: string; end: string }} range
 */
export function endpointCornerClass(iso, range) {
    const { start, end } = range;

    if (start === end) {
        return '';
    }

    if (iso === start) {
        return ' rounded-r-none rounded-l-[12px]';
    }

    if (iso === end) {
        return ' rounded-l-none rounded-r-[12px]';
    }

    return '';
}

/**
 * @param {string} iso
 * @param {LoggedPeriod[]} periods
 * @returns {LoggedPeriod | null}
 */
export function findLoggedPeriodContaining(iso, periods) {
    return periods.find((period) => isDateInRange(iso, period.start, period.end)) ?? null;
}

/**
 * @param {string} iso
 * @param {string | null} start
 * @param {string | null} end
 */
export function isInActiveSelection(iso, start, end) {
    return isDateInRange(iso, start, end);
}

/**
 * @param {string} startIso
 * @param {string} endIso
 */
export function formatPeriodRangeLabel(startIso, endIso) {
    const start = parseIsoDate(startIso);
    const end = parseIsoDate(endIso);

    if (!start || !end) {
        return '';
    }

    const formatter = new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric' });

    return `${formatter.format(start)} - ${formatter.format(end)}`;
}

/**
 * @param {LoggedPeriod} period
 */
export function periodEntryKey(period) {
    return `${period.start}_${period.end}`;
}

/**
 * @param {LoggedPeriod[]} periods
 * @param {LoggedPeriod} candidate
 */
export function appendPeriodIfMissing(periods, candidate) {
    const key = periodEntryKey(candidate);

    if (periods.some((entry) => periodEntryKey(entry) === key)) {
        return periods;
    }

    return [...periods, candidate];
}

/**
 * @param {LoggedPeriod[]} periods
 * @param {string} key
 */
export function removePeriodByKey(periods, key) {
    return periods.filter((entry) => periodEntryKey(entry) !== key);
}
