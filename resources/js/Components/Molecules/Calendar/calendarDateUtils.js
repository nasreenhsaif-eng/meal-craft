/**
 * Calendar helpers — local-midnight `Date` values, ISO `YYYY-MM-DD` strings (meal-plan friendly).
 *
 * @param {Date} d
 * @returns {string}
 */
export function toIsoDate(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');

    return `${y}-${m}-${day}`;
}

/**
 * @param {string | null | undefined} s
 * @returns {Date | null}
 */
export function parseIsoDate(s) {
    if (s == null || s === '') {
        return null;
    }
    const parts = String(s).split('-').map(Number);
    if (parts.length !== 3 || parts.some((n) => Number.isNaN(n))) {
        return null;
    }
    const [y, m, day] = parts;

    return new Date(y, m - 1, day);
}

/**
 * Monday = 0 … Sunday = 6
 *
 * @param {Date} d
 * @returns {number}
 */
export function weekdayMondayFirst(d) {
    return (d.getDay() + 6) % 7;
}

/**
 * Build month grid cells (Mon-first weeks), including leading/trailing neighbour-month days.
 *
 * @param {Date} displayMonth First day of month to display (any day in that month).
 * @returns {{ date: Date; inMonth: boolean }[]}
 */
export function buildCalendarCells(displayMonth) {
    const y = displayMonth.getFullYear();
    const m = displayMonth.getMonth();
    const first = new Date(y, m, 1);
    const pad = weekdayMondayFirst(first);
    const dim = new Date(y, m + 1, 0).getDate();
    const cells = [];

    const prevLast = new Date(y, m, 0).getDate();
    for (let i = 0; i < pad; i++) {
        const day = prevLast - pad + i + 1;
        cells.push({ date: new Date(y, m - 1, day), inMonth: false });
    }
    for (let d = 1; d <= dim; d++) {
        cells.push({ date: new Date(y, m, d), inMonth: true });
    }
    const rem = cells.length % 7;
    const tail = rem === 0 ? 0 : 7 - rem;
    for (let i = 1; i <= tail; i++) {
        cells.push({ date: new Date(y, m + 1, i), inMonth: false });
    }

    return cells;
}

/**
 * @param {string | null | undefined} iso
 * @param {string | null | undefined} minIso
 * @param {string | null | undefined} maxIso
 * @returns {boolean}
 */
export function isIsoDateDisabled(iso, minIso, maxIso) {
    if (iso == null) {
        return true;
    }
    if (minIso != null && iso < minIso) {
        return true;
    }
    if (maxIso != null && iso > maxIso) {
        return true;
    }

    return false;
}
