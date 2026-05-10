import { MEAL_PLAN_HEADERS, MEAL_PLAN_KEYS } from './logisticsMealColumns.js';
import { partitionSafetyNotes } from './parseSafetyNotes.js';

/**
 * @param {string | number | null | undefined} val
 */
function stripPlaceholderForSheet(val) {
    const t = String(val ?? '').trim();
    if (t === '—' || t === '-') {
        return '';
    }
    return t;
}

/**
 * @param {string | number | null | undefined} val
 */
export function csvEscape(val) {
    const s = String(val ?? '');
    if (/[",\r\n]/.test(s)) {
        return `"${s.replace(/"/g, '""')}"`;
    }
    return s;
}

/**
 * RFC-style rows for spreadsheets (UTF-8 BOM optional for Excel).
 *
 * @param {import('./logisticsMockData.js').UserSubmissionRow[]} submissions
 * @param {{ includeBom?: boolean }} [opts]
 */
export function buildUserSubmissionsCsv(submissions, opts = {}) {
    const { includeBom = true } = opts;
    const headers = [
        'Submission Date (ISO)',
        'Name',
        'Craft',
        'Plan',
        ...MEAL_PLAN_HEADERS,
        'Allergies',
        'Special requests',
        'Submission ID',
    ];
    const lines = [headers.join(',')];
    for (const s of submissions) {
        const mealCells = MEAL_PLAN_KEYS.map((k) => csvEscape(stripPlaceholderForSheet(s[k])));
        const { allergies, dislikes } = partitionSafetyNotes({
            allergyNotes: s.allergyNotes,
            specialRequests: s.specialRequests ?? '',
        });
        const allergyCol = allergies.length ? allergies.join(' | ') : '';
        const dislikeCol = dislikes.length ? dislikes.join(' | ') : '';
        lines.push(
            [
                csvEscape(s.submittedAt),
                csvEscape(s.name),
                csvEscape(s.craft),
                csvEscape(s.plan),
                ...mealCells,
                csvEscape(allergyCol),
                csvEscape(dislikeCol),
                csvEscape(s.submissionId),
            ].join(','),
        );
    }
    const body = lines.join('\r\n');
    return includeBom ? `\uFEFF${body}` : body;
}

/**
 * @param {import('./logisticsMockData.js').KitchenDailyRow[]} rows
 * @param {string} productionDate
 * @param {{ includeBom?: boolean }} [opts]
 */
export function buildKitchenDailySheetCsv(rows, productionDate, opts = {}) {
    const { includeBom = true } = opts;
    const headers = [
        'Production Date',
        'Name',
        ...MEAL_PLAN_HEADERS,
        'Cutlery',
        'Special Requests',
        'Allergies',
    ];
    const lines = [headers.join(',')];
    for (const r of rows) {
        const mealCells = MEAL_PLAN_KEYS.map((k) => csvEscape(stripPlaceholderForSheet(r[k])));
        const { allergies, dislikes } = partitionSafetyNotes({
            allergies: r.allergies,
            specialRequests: r.specialRequests ?? '',
        });
        const allergyCol = allergies.length ? allergies.join(' | ') : '';
        const dislikeCol = dislikes.length ? dislikes.join(' | ') : '';
        lines.push(
            [
                csvEscape(productionDate ?? ''),
                csvEscape(r.name),
                ...mealCells,
                csvEscape(r.cutlery),
                csvEscape(dislikeCol),
                csvEscape(allergyCol),
            ].join(','),
        );
    }
    const body = lines.join('\r\n');
    return includeBom ? `\uFEFF${body}` : body;
}

/**
 * @param {string} filename
 * @param {string} csv
 */
export function downloadCsv(filename, csv) {
    if (typeof document === 'undefined' || typeof Blob === 'undefined') {
        return;
    }
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.rel = 'noopener';
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
}
