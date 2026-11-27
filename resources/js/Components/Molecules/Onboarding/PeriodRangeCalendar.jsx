import { useId, useMemo, useState } from 'react';
import { buildCalendarCells, isIsoDateDisabled, parseIsoDate, toIsoDate } from '../Calendar/calendarDateUtils.js';
import CycleCalendarLegend from './CycleCalendarLegend.jsx';
import {
    buildCycleSyncCalendarState,
    LOGGED_BLEED_COLOR,
    resolveCycleSyncDayVisualState,
} from './cycleSyncPhaseUtils.js';
import { canViewNextForecastMonth } from './cyclePredictionUtils.js';

const gridCols7 = 'grid w-full min-w-0 [grid-template-columns:repeat(7,minmax(0,1fr))]';

const cellBase =
    'relative flex aspect-square min-h-[44px] w-full min-w-0 flex-col items-center justify-center gap-0.5 rounded-[12px] border border-transparent font-sans text-sm font-medium tabular-nums';
const cellFocus =
    'outline-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-status-error focus-visible:ring-offset-2';

const WEEKDAY_LABELS_MON_FIRST = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

/**
 * @param {{
 *   logged: boolean;
 *   phase: string | null;
 *   color: string | null;
 * }} visualState
 * @param {boolean} inMonth
 * @param {boolean} isClickDisabled
 * @param {boolean} isToday
 */
function dayButtonClasses(visualState, inMonth, isClickDisabled, isToday) {
    const muted = !inMonth ? 'text-form-label/60' : 'text-body';

    if (visualState.logged && inMonth) {
        return `${cellBase} ${cellFocus} bg-status-error text-white ring-1 ring-status-error hover:bg-status-error`;
    }

    if (!inMonth) {
        return `${cellBase} ${cellFocus} bg-white hover:bg-surface-soft ${muted}`;
    }

    if (visualState.color) {
        return `${cellBase} ${cellFocus} text-body hover:opacity-90`;
    }

    if (isToday) {
        return `${cellBase} ${cellFocus} bg-white ring-1 ring-border-light hover:bg-surface-soft ${muted}`;
    }

    if (isClickDisabled) {
        return `${cellBase} ${cellFocus} cursor-default bg-white text-form-label/70 hover:bg-white`;
    }

    return `${cellBase} ${cellFocus} bg-white hover:bg-surface-soft ${muted}`;
}

/**
 * Period-tracking calendar — day toggles with live Cycle Sync phase coloring.
 *
 * @param {{
 *   loggedPeriods?: Array<{ start: string; end: string }>;
 *   onToggleBleedDay?: (iso: string) => void;
 *   cycleLength?: number;
 *   defaultMonth?: Date;
 *   maxDate?: string | null;
 *   className?: string;
 *   id?: string;
 *   'aria-label'?: string;
 * }} props
 */
export default function PeriodRangeCalendar({
    loggedPeriods = [],
    onToggleBleedDay,
    cycleLength,
    defaultMonth,
    maxDate = null,
    className = '',
    id,
    'aria-label': ariaLabel = 'Period tracking calendar',
}) {
    const monthHeadingId = useId();
    const referenceDate = useMemo(() => new Date(), []);
    const todayIso = toIsoDate(referenceDate);
    const effectiveMaxDate = maxDate ?? todayIso;

    const cycleState = useMemo(
        () => buildCycleSyncCalendarState(loggedPeriods, cycleLength, referenceDate),
        [loggedPeriods, cycleLength, referenceDate],
    );

    const initialMonth =
        defaultMonth ??
        parseIsoDate(cycleState.cycleStart ?? '') ??
        parseIsoDate(loggedPeriods[0]?.start ?? '') ??
        referenceDate;
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
    const canGoNext = canViewNextForecastMonth(viewMonth, referenceDate);

    const goPrev = () => {
        setViewMonth((d) => new Date(d.getFullYear(), d.getMonth() - 1, 1));
    };

    const goNext = () => {
        if (!canGoNext) {
            return;
        }

        setViewMonth((d) => new Date(d.getFullYear(), d.getMonth() + 1, 1));
    };

    const handleDayClick = (iso) => {
        if (isIsoDateDisabled(iso, undefined, effectiveMaxDate)) {
            return;
        }

        onToggleBleedDay?.(iso);
    };

    return (
        <div
            id={id}
            className={`relative box-border w-full min-w-0 md:mx-auto md:max-w-[400px] ${className}`.trim()}
        >
            <div
                className="box-border w-full overflow-hidden rounded-[12px] border border-border-light bg-white shadow-sm"
                role="group"
                aria-label={ariaLabel}
            >
                <div className="flex items-center justify-between gap-2 border-b border-border-light bg-white px-3 py-3 sm:px-4">
                    <button
                        type="button"
                        onClick={goPrev}
                        className="inline-flex h-10 min-w-[40px] items-center justify-center rounded-[12px] border border-border-light bg-white font-sans text-lg font-semibold text-brand-primary-pressed shadow-sm outline-none transition-colors hover:border-brand-primary/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary focus-visible:ring-offset-2"
                        aria-label="Previous month"
                    >
                        ‹
                    </button>
                    <h2
                        className="m-0 text-center font-montserrat text-base font-bold leading-snug tracking-tight text-brand-primary-pressed"
                        id={monthHeadingId}
                    >
                        {monthLabel}
                    </h2>
                    <button
                        type="button"
                        onClick={goNext}
                        disabled={!canGoNext}
                        className="inline-flex h-10 min-w-[40px] items-center justify-center rounded-[12px] border border-border-light bg-white font-sans text-lg font-semibold text-brand-primary-pressed shadow-sm outline-none transition-colors hover:border-brand-primary/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40"
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
                                className="flex min-w-0 items-center justify-center font-sans text-[11px] font-bold uppercase tracking-wide text-form-label"
                            >
                                {wd}
                            </div>
                        ))}
                    </div>

                    <div className="w-full min-w-0" role="rowgroup">
                        {weeks.map((week, wi) => (
                            <div key={wi} role="row" className={`${gridCols7} mb-1 gap-x-0 gap-y-1 last:mb-0`}>
                                {week.map(({ date, inMonth }) => {
                                    const iso = toIsoDate(date);
                                    const dNum = date.getDate();
                                    const isToday = iso === todayIso;
                                    const isClickDisabled = iso > todayIso;
                                    const visualState = resolveCycleSyncDayVisualState(
                                        iso,
                                        loggedPeriods,
                                        cycleState,
                                    );
                                    const tintStyle =
                                        inMonth && !visualState.logged && visualState.color
                                            ? {
                                                  backgroundColor: `${visualState.color}33`,
                                              }
                                            : inMonth && visualState.logged
                                              ? { backgroundColor: LOGGED_BLEED_COLOR }
                                              : undefined;

                                    return (
                                        <div key={`${iso}-${wi}-${dNum}`} className="box-border min-w-0 p-0.5">
                                            <button
                                                type="button"
                                                role="gridcell"
                                                tabIndex={isClickDisabled ? -1 : 0}
                                                onClick={() => handleDayClick(iso)}
                                                disabled={isClickDisabled}
                                                className={dayButtonClasses(
                                                    visualState,
                                                    inMonth,
                                                    isClickDisabled,
                                                    isToday,
                                                )}
                                                style={tintStyle}
                                                aria-label={new Intl.DateTimeFormat(undefined, {
                                                    weekday: 'long',
                                                    month: 'long',
                                                    day: 'numeric',
                                                    year: 'numeric',
                                                }).format(date)}
                                                aria-selected={visualState.logged}
                                                aria-current={isToday && inMonth ? 'date' : undefined}
                                            >
                                                <span>{dNum}</span>
                                                <span className="block h-1.5 w-1.5 shrink-0" aria-hidden="true" />
                                            </button>
                                        </div>
                                    );
                                })}
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            <CycleCalendarLegend className="mt-3 w-full" />
        </div>
    );
}
