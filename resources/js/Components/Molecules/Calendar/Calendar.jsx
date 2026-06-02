import { useId, useMemo, useState } from 'react';
import MealCraftLogo from '../../Atoms/Logo/MealCraftLogo.jsx';
import { buildCalendarCells, isIsoDateDisabled, parseIsoDate, toIsoDate } from './calendarDateUtils.js';

/** Seven equal columns — non-negotiable `repeat(7, minmax(0, 1fr))` for layout stability */
const gridCols7 = 'grid w-full min-w-0 [grid-template-columns:repeat(7,minmax(0,1fr))]';

const cellBase =
    'relative flex aspect-square min-h-[44px] w-full min-w-0 items-center justify-center rounded-[12px] border border-transparent font-sans text-sm font-medium tabular-nums transition-colors duration-150';
const cellFocus =
    'outline-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6E8C47] focus-visible:ring-offset-2';

const WEEKDAY_LABELS_MON_FIRST = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

/**
 * Dashboard meal-plan calendar — single day or range selection (Logistics green chrome).
 *
 * @param {{
 *   mode?: 'single' | 'range';
 *   value?: string | null;
 *   onChange?: (iso: string | null) => void;
 *   rangeValue?: { start: string | null; end: string | null };
 *   onRangeChange?: (range: { start: string | null; end: string | null }) => void;
 *   defaultMonth?: Date;
 *   minDate?: string | null;
 *   maxDate?: string | null;
 *   identityState?: 'none' | 'loading' | 'success';
 *   identityMessage?: string;
 *   className?: string;
 *   id?: string;
 *   'aria-label'?: string;
 * }} props
 */
export default function Calendar({
    mode = 'single',
    value = null,
    onChange,
    rangeValue = { start: null, end: null },
    onRangeChange,
    defaultMonth,
    minDate = null,
    maxDate = null,
    identityState = 'none',
    identityMessage = '',
    className = '',
    id,
    'aria-label': ariaLabel = 'Calendar',
}) {
    const monthHeadingId = useId();

    const initialMonth =
        defaultMonth ?? parseIsoDate(value ?? '') ?? parseIsoDate(rangeValue?.start ?? '') ?? new Date();
    const [viewMonth, setViewMonth] = useState(() => new Date(initialMonth.getFullYear(), initialMonth.getMonth(), 1));

    const cells = useMemo(() => buildCalendarCells(viewMonth), [viewMonth]);
    const weeks = useMemo(() => {
        const rows = [];
        for (let i = 0; i < cells.length; i += 7) {
            rows.push(cells.slice(i, i + 7));
        }

        return rows;
    }, [cells]);

    const monthLabel = new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric' }).format(viewMonth);
    const todayIso = toIsoDate(new Date());

    const goPrev = () => {
        setViewMonth((d) => new Date(d.getFullYear(), d.getMonth() - 1, 1));
    };

    const goNext = () => {
        setViewMonth((d) => new Date(d.getFullYear(), d.getMonth() + 1, 1));
    };

    const handleDayClick = (iso) => {
        if (isIsoDateDisabled(iso, minDate ?? undefined, maxDate ?? undefined)) {
            return;
        }
        if (mode === 'single') {
            onChange?.(iso);
            return;
        }
        const start = rangeValue?.start ?? null;
        const end = rangeValue?.end ?? null;
        if (!start || (start && end)) {
            onRangeChange?.({ start: iso, end: null });
            return;
        }
        if (iso < start) {
            onRangeChange?.({ start: iso, end: start });
            return;
        }
        onRangeChange?.({ start, end: iso });
    };

    /**
     * @param {string} iso
     * @param {boolean} inMonth
     */
    const dayButtonClasses = (iso, inMonth) => {
        const disabled = isIsoDateDisabled(iso, minDate ?? undefined, maxDate ?? undefined);
        const muted = !inMonth ? 'text-[#364153]/35' : 'text-[#364153]';

        if (disabled) {
            return `${cellBase} ${cellFocus} cursor-not-allowed bg-white opacity-40 hover:bg-white ${muted}`;
        }

        if (mode === 'single') {
            if (value === iso) {
                return `${cellBase} ${cellFocus} bg-[#6E8C47] text-white shadow-[inset_0_0_0_1px_rgba(110,140,71,0.25)] hover:bg-[#6E8C47]`;
            }
            if (iso === todayIso && inMonth) {
                return `${cellBase} ${cellFocus} bg-white ring-1 ring-[#6E8C47]/35 hover:bg-[#6E8C47]/10 ${muted}`;
            }

            return `${cellBase} ${cellFocus} bg-white hover:bg-[#6E8C47]/10 ${muted}`;
        }

        const s = rangeValue?.start;
        const e = rangeValue?.end;
        const inRange = Boolean(s && e && iso >= s && iso <= e);
        const isStart = s === iso;
        const isEnd = e === iso;

        if (isStart && isEnd) {
            return `${cellBase} ${cellFocus} bg-[#6E8C47] text-white shadow-[inset_0_0_0_1px_rgba(255,255,255,0.2)] hover:bg-[#6E8C47]`;
        }
        if (isStart || isEnd) {
            let corners = '';
            if (s && e && s !== e) {
                if (iso === s) {
                    corners = ' rounded-r-none rounded-l-[12px]';
                } else if (iso === e) {
                    corners = ' rounded-l-none rounded-r-[12px]';
                }
            }

            return `${cellBase} ${cellFocus} bg-[#6E8C47] text-white shadow-[inset_0_0_0_1px_rgba(255,255,255,0.2)] hover:bg-[#6E8C47]${corners}`;
        }
        if (inRange && inMonth && s && e && iso > s && iso < e) {
            return `${cellBase} ${cellFocus} rounded-none bg-[#6E8C47]/15 hover:bg-[#6E8C47]/25 ${muted}`;
        }
        if (iso === todayIso && inMonth) {
            return `${cellBase} ${cellFocus} bg-white ring-1 ring-[#6E8C47]/35 hover:bg-[#6E8C47]/10 ${muted}`;
        }

        return `${cellBase} ${cellFocus} bg-white hover:bg-[#6E8C47]/10 ${muted}`;
    };

    const ariaSelectedFor = (iso, inMonth) => {
        if (mode === 'single') {
            return value === iso;
        }
        const s = rangeValue?.start;
        const e = rangeValue?.end;
        if (s && !e) {
            return iso === s;
        }
        if (s && e) {
            return iso === s || iso === e;
        }

        return false;
    };

    return (
        <div
            id={id}
            className={`relative box-border w-full min-w-[320px] max-w-[400px] ${className}`.trim()}
        >
            {identityState === 'loading' ? (
                <div
                    className="absolute inset-0 z-20 flex flex-col items-center justify-center gap-3 rounded-[12px] border border-[#E5E7EB] bg-white/90 px-4 py-6 backdrop-blur-[2px]"
                    role="status"
                    aria-live="polite"
                    aria-busy="true"
                    aria-label={identityMessage || 'Loading calendar'}
                >
                    <MealCraftLogo variant="minimal-animated" width={100} className="h-auto max-w-full" alt="" />
                    {identityMessage ? (
                        <p className="text-center font-sans text-sm font-medium leading-snug text-[#364153]">{identityMessage}</p>
                    ) : null}
                </div>
            ) : null}

            {identityState === 'success' ? (
                <div
                    className="absolute inset-0 z-20 flex flex-col items-center justify-center gap-2 rounded-[12px] border border-[#6E8C47]/30 bg-white/95 px-4 py-5 shadow-sm backdrop-blur-[1px]"
                    role="status"
                    aria-live="polite"
                    aria-label={identityMessage || 'Date confirmed'}
                >
                    <div className="flex h-14 w-14 items-center justify-center">
                        <MealCraftLogo variant="minimal-animated" width={56} className="h-14 w-14" alt="" />
                    </div>
                    <p className="text-center font-sans text-sm font-semibold leading-snug text-[#6E8C47]">
                        {identityMessage || 'Date saved'}
                    </p>
                </div>
            ) : null}

            <div
                className="box-border w-full min-w-[320px] overflow-hidden rounded-[12px] border border-[#E5E7EB] bg-white shadow-sm"
                role="group"
                aria-label={ariaLabel}
            >
                <div className="flex items-center justify-between gap-2 border-b border-[#E5E7EB] bg-white px-3 py-3 sm:px-4">
                    <button
                        type="button"
                        onClick={goPrev}
                        className="inline-flex h-10 min-w-[40px] items-center justify-center rounded-[12px] border border-[#E5E7EB] bg-white font-sans text-sm font-semibold text-[#364153] shadow-sm outline-none transition-colors hover:border-[#6E8C47]/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6E8C47] focus-visible:ring-offset-2"
                        aria-label="Previous month"
                    >
                        ‹
                    </button>
                    <h2
                        className="m-0 text-center font-sans text-base font-bold leading-snug tracking-tight text-[#364153]"
                        id={monthHeadingId}
                    >
                        {monthLabel}
                    </h2>
                    <button
                        type="button"
                        onClick={goNext}
                        className="inline-flex h-10 min-w-[40px] items-center justify-center rounded-[12px] border border-[#E5E7EB] bg-white font-sans text-sm font-semibold text-[#364153] shadow-sm outline-none transition-colors hover:border-[#6E8C47]/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6E8C47] focus-visible:ring-offset-2"
                        aria-label="Next month"
                    >
                        ›
                    </button>
                </div>

                <div className="w-full min-w-0 p-2 sm:p-3" role="grid" aria-labelledby={monthHeadingId}>
                    <div className={`${gridCols7} mb-2 gap-1`} role="row">
                        {WEEKDAY_LABELS_MON_FIRST.map((wd) => (
                            <div
                                key={wd}
                                role="columnheader"
                                className="flex min-w-0 items-center justify-center font-sans text-[11px] font-bold uppercase tracking-wide text-[#555555]"
                            >
                                {wd}
                            </div>
                        ))}
                    </div>

                    <div className="w-full min-w-0" role="rowgroup">
                        {weeks.map((week, wi) => (
                            <div key={wi} role="row" className={`${gridCols7} mb-1 gap-y-1 gap-x-0 last:mb-0`}>
                                {week.map(({ date, inMonth }) => {
                                    const iso = toIsoDate(date);
                                    const dNum = date.getDate();
                                    const isToday = iso === todayIso;
                                    const disabled = isIsoDateDisabled(iso, minDate ?? undefined, maxDate ?? undefined);

                                    return (
                                        <div key={`${iso}-${wi}-${dNum}`} className="box-border min-w-0 p-0.5">
                                            <button
                                                type="button"
                                                role="gridcell"
                                                tabIndex={disabled ? -1 : 0}
                                                onClick={() => handleDayClick(iso)}
                                                disabled={disabled}
                                                className={dayButtonClasses(iso, inMonth)}
                                                aria-label={new Intl.DateTimeFormat(undefined, {
                                                    weekday: 'long',
                                                    month: 'long',
                                                    day: 'numeric',
                                                    year: 'numeric',
                                                }).format(date)}
                                                aria-selected={ariaSelectedFor(iso, inMonth)}
                                                aria-current={isToday && inMonth ? 'date' : undefined}
                                            >
                                                {dNum}
                                            </button>
                                        </div>
                                    );
                                })}
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}
