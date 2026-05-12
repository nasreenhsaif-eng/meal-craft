import SquareCheckbox from './Atoms/Icons/SquareCheckbox.jsx';

function fmtNum(v) {
    if (v === null || v === undefined || v === '') {
        return '—';
    }
    const n = Number(v);
    if (Number.isNaN(n)) {
        return String(v);
    }
    if (Math.abs(n) >= 100) {
        return String(Math.round(n));
    }
    return n % 1 === 0 ? String(n) : String(Math.round(n * 10) / 10);
}

/**
 * Admin meal library list row — selection checkbox + compact macro columns.
 *
 * @param {{
 *   meal: { id: string; title?: string; mealType?: string; category?: string; macros?: { calories?: number; protein?: number; carbs?: number; fat?: number } };
 *   selected: boolean;
 *   onToggleSelected: () => void;
 *   className?: string;
 * }} props
 */
export default function MealListRow({ meal, selected, onToggleSelected, className = '' }) {
    const m = meal.macros ?? {};
    const macroStr = [fmtNum(m.protein), fmtNum(m.carbs), fmtNum(m.fat)].every((x) => x === '—')
        ? '—'
        : `P ${fmtNum(m.protein)} · C ${fmtNum(m.carbs)} · F ${fmtNum(m.fat)}`;

    return (
        <tr className={`border-b border-gray-200 text-[#1F2937] transition-colors hover:bg-[#F8F9F6] ${className}`.trim()}>
            <td className="w-[52px] px-3 py-3 align-middle">
                <button
                    type="button"
                    className="inline-flex items-center rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-offset-2"
                    onClick={() => onToggleSelected()}
                    aria-pressed={selected}
                    aria-label={selected ? `Deselect ${meal.title}` : `Select ${meal.title}`}
                >
                    <SquareCheckbox checked={selected} presentational />
                </button>
            </td>
            <td className="min-w-0 px-4 py-3 align-middle font-body text-sm font-medium text-[#1F2937]">
                <span className="line-clamp-2">{meal.title ?? '—'}</span>
            </td>
            <td className="w-[120px] whitespace-nowrap px-3 py-3 align-middle font-body text-sm text-[#374151]">
                {meal.mealType ?? '—'}
            </td>
            <td className="w-[100px] whitespace-nowrap px-3 py-3 text-right font-body text-sm tabular-nums text-[#374151]">
                {fmtNum(m.calories)}
            </td>
            <td className="min-w-[180px] px-3 py-3 font-body text-xs tabular-nums text-[#555555]">
                {macroStr}
            </td>
        </tr>
    );
}
