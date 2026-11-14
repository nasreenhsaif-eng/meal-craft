import { formatCalorieRange } from '../../../meal-craft/dailyTargetsCalculator.js';

/**
 * @param {unknown} value
 * @returns {string}
 */
export function displayValue(value) {
    if (value == null || value === '') {
        return '—';
    }

    if (typeof value === 'number' && Number.isFinite(value)) {
        return String(value);
    }

    return String(value);
}

/**
 * @param {string | null | undefined} slug
 * @returns {string}
 */
export function humanizeSlug(slug) {
    if (slug == null || slug === '') {
        return '—';
    }

    return slug.replaceAll('_', ' ');
}

/**
 * @param {string[] | null | undefined} items
 * @returns {string}
 */
export function listDisplay(items) {
    if (!Array.isArray(items) || items.length === 0) {
        return '—';
    }

    return items.map((item) => humanizeSlug(item)).join(', ');
}

/**
 * @param {{
 *   dailyCaloriesMin?: number | null;
 *   dailyCaloriesMax?: number | null;
 *   dailyCalorieTarget?: number | null;
 * } | null | undefined} details
 */
export function caloriesDisplay(details) {
    const min = Number(details?.dailyCaloriesMin);
    const max = Number(details?.dailyCaloriesMax);

    if (Number.isFinite(min) && Number.isFinite(max) && min > 0 && max > 0) {
        return formatCalorieRange(min, max);
    }

    const snapped = Number(details?.dailyCalorieTarget);

    return Number.isFinite(snapped) && snapped > 0 ? String(snapped) : '—';
}

/**
 * @param {{
 *   title: string;
 *   rows: { label: string; value: string; preserveCase?: boolean }[];
 *   action?: import('react').ReactNode;
 *   className?: string;
 * }} props
 */
export default function CustomerDetailSection({ title, rows, action = null, className = '' }) {
    return (
        <section
            className={[
                'w-full min-w-0 rounded-[16px] border border-gray-200 bg-white px-4 py-6 shadow-sm sm:p-8',
                className,
            ]
                .filter(Boolean)
                .join(' ')}
        >
            <div className="flex items-start justify-between gap-4">
                <h2 className="font-montserrat text-lg font-semibold tracking-tight text-[#262A22]">{title}</h2>
                {action}
            </div>
            <dl className="mt-3 divide-y divide-gray-100">
                {rows.map((row) => (
                    <div key={row.label} className="grid gap-1 py-3 sm:grid-cols-[10rem_minmax(0,1fr)] sm:gap-4">
                        <dt className="font-montserrat text-xs font-bold uppercase tracking-[0.08em] text-[#6B7280]">
                            {row.label}
                        </dt>
                        <dd
                            className={[
                                'min-w-0 font-body text-base text-[#262A22]',
                                row.preserveCase ? '' : 'capitalize',
                            ]
                                .filter(Boolean)
                                .join(' ')}
                        >
                            {row.value}
                        </dd>
                    </div>
                ))}
            </dl>
        </section>
    );
}
