export const WHEEL_ITEM_HEIGHT = 44;

export const WHEEL_VISIBLE_COUNT = 5;

export const WHEEL_HEIGHT = WHEEL_ITEM_HEIGHT * WHEEL_VISIBLE_COUNT;

export const MONTH_LABELS = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];

/**
 * @param {number} [minAge]
 * @param {number} [maxAge]
 * @returns {number[]}
 */
export function buildYearOptions(minAge = 13, maxAge = 100) {
    const currentYear = new Date().getFullYear();
    const minYear = currentYear - maxAge;
    const maxYear = currentYear - minAge;
    const years = [];

    for (let year = minYear; year <= maxYear; year += 1) {
        years.push(year);
    }

    return years;
}

/**
 * @param {number} month 1-12
 * @param {number} year
 */
export function daysInMonth(month, year) {
    return new Date(year, month, 0).getDate();
}

/**
 * @param {number} month 1-12
 * @param {number} year
 * @returns {number[]}
 */
export function buildDayOptions(month, year) {
    const count = daysInMonth(month, year);

    return Array.from({ length: count }, (_, index) => index + 1);
}

/**
 * @param {number} day
 * @param {number} month 1-12
 * @param {number} year
 */
export function clampDay(day, month, year) {
    return Math.min(Math.max(day, 1), daysInMonth(month, year));
}

/**
 * @param {{ month: number; day: number; year: number }} parts
 */
export function toIsoDate({ month, day, year }) {
    const paddedMonth = String(month).padStart(2, '0');
    const paddedDay = String(day).padStart(2, '0');

    return `${year}-${paddedMonth}-${paddedDay}`;
}

/**
 * @param {string | null | undefined} iso
 * @returns {{ month: number; day: number; year: number } | null}
 */
export function parseIsoDate(iso) {
    if (typeof iso !== 'string' || iso.length === 0) {
        return null;
    }

    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso);
    if (!match) {
        return null;
    }

    return {
        year: Number(match[1]),
        month: Number(match[2]),
        day: Number(match[3]),
    };
}

/**
 * Default picker value for new customers (~32 years old, March 3 per design mock).
 */
export function defaultBirthdayValue() {
    const currentYear = new Date().getFullYear();

    return {
        month: 3,
        day: 3,
        year: currentYear - 32,
    };
}
