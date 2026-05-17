import { forwardRef } from 'react';
import SquareCheckbox from './Atoms/Icons/SquareCheckbox.jsx';
import IconDragHandle from './Atoms/Icons/IconDragHandle.jsx';

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
 * Admin meal library list row — drag handle, selection checkbox, compact macro columns.
 *
 * @param {{
 *   meal: { id: string; title?: string; mealType?: string; category?: string; macros?: { calories?: number; protein?: number; carbs?: number; fat?: number } };
 *   selected: boolean;
 *   onToggleSelected: () => void;
 *   className?: string;
 *   style?: import('react').CSSProperties;
 *   isDragging?: boolean;
 *   dragHandleProps?: Record<string, unknown>;
 * }} props
 */
const MealListRow = forwardRef(function MealListRow(
    {
        meal,
        selected,
        onToggleSelected,
        className = '',
        style,
        isDragging = false,
        dragHandleProps,
    },
    ref,
) {
    const m = meal.macros ?? {};
    const macroStr = [fmtNum(m.protein), fmtNum(m.carbs), fmtNum(m.fat)].every((x) => x === '—')
        ? '—'
        : `P ${fmtNum(m.protein)} · C ${fmtNum(m.carbs)} · F ${fmtNum(m.fat)}`;

    const rowClass = [
        'border-b border-gray-200 text-[#1F2937] transition-colors',
        isDragging ? 'relative z-10 bg-[#EEF4E8] shadow-md' : 'hover:bg-[#F8F9F6]',
        className,
    ]
        .filter(Boolean)
        .join(' ');

    return (
        <tr ref={ref} style={style} className={rowClass}>
            <td className="w-9 px-2 py-3 align-middle">
                <button
                    type="button"
                    className="inline-flex cursor-grab touch-none items-center justify-center rounded-md p-1 text-[#9CA3AF] hover:bg-black/[0.04] hover:text-[#5A6B44] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-offset-2 active:cursor-grabbing"
                    aria-label={`Drag to reorder ${meal.title ?? 'meal'}`}
                    {...(dragHandleProps ?? {})}
                >
                    <IconDragHandle />
                </button>
            </td>
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
            <td className="w-[100px] whitespace-nowrap px-3 py-3 text-right align-middle font-body text-sm tabular-nums text-[#374151]">
                {fmtNum(m.calories)}
            </td>
            <td className="min-w-[180px] px-3 py-3 align-middle font-body text-xs tabular-nums text-[#555555]">
                {macroStr}
            </td>
        </tr>
    );
});

export default MealListRow;
