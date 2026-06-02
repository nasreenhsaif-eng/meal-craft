import { useId, useMemo, useState } from 'react';
import { buildCalendarCells, isIsoDateDisabled, parseIsoDate, toIsoDate } from '../Calendar/calendarDateUtils.js';
import {
    buildProjectedCycles,
    canViewNextForecastMonth,
    isIsoInFuture,
    resolveDayCellVisualState,
} from './cyclePredictionUtils.js';
import CycleCalendarLegend from './CycleCalendarLegend.jsx';
import {
    endpointCornerClass,
    findContainingRange,
    findLoggedPeriodContaining,
    isInActiveSelection,
    periodEntryKey,
    resolveCombinedDayRangeRole,
} from './periodTrackingUtils.js';

const gridCols7 = 'grid w-full min-w-0 [grid-template-columns:repeat(7,minmax(0,1fr))]';

const cellBase =
    'relative flex aspect-square min-h-[44px] w-full min-w-0 flex-col items-center justify-center gap-0.5 rounded-[12px] border border-transparent font-sans text-sm font-medium tabular-nums transition-colors duration-150';
const cellFocus =
    'outline-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-status-error focus-visible:ring-offset-2';

const WEEKDAY_LABELS_MON_FIRST = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

/**
 * @param {import('./cyclePredictionUtils.js').DayCellVisualState} visualState
 * @param {boolean} inMonth
 * @param {{ start: string; end: string } | null} containingRange
 * @param {string} iso
 * @param {boolean} isClickDisabled
 * @param {boolean} isToday
 */
function dayButtonClasses(visualState, inMonth, containingRange, iso, isClickDisabled, isToday) {
    const muted = !inMonth ? 'text-grey-94/60' : 'text-grey-33';
    const futureCursor = isClickDisabled ? 'cursor-default' : '';

    if (visualState.logged && inMonth) {
        const corners = containingRange ? endpointCornerClass(iso, containingRange) : '';

        return `${cellBase} ${cellFocus} bg-status-error text-white ring-1 ring-status-error hover:bg-status-error${corners}`;
    }

    if (!inMonth) {
        return `${cellBase} ${cellFocus} bg-white hover:bg-grey-96 ${muted}`;
    }

    const layers = [cellBase, cellFocus];

    if (visualState.isPredictedPeriod) {
        layers.push('bg-status-error/50 hover:bg-status-error/50');
    }

    if (visualState.isFertile) {
        layers.push('bg-brand-accent/20 hover:bg-brand-accent/25');
    }

    if (visualState.isOvulation) {
        layers.push('text-brand-accent ring-1 ring-brand-accent');
    } else if (!visualState.isFertile && !visualState.isPredictedPeriod) {
        if (isToday) {
            layers.push('bg-white ring-1 ring-border-light hover:bg-grey-96', muted);
        } else if (isClickDisabled) {
            layers.push('cursor-default bg-white text-grey-94/70 hover:bg-white');
        } else {
            layers.push('bg-white hover:bg-grey-96', muted);
        }
    } else {
        layers.push('text-grey-33', futureCursor);
    }

    return layers.join(' ');
}

/**
 * Period-tracking calendar — logged ranges, pre-computed cycle projections, fertile window, ovulation markers.
 *
 * @param {{
 *   rangeValue?: { start: string | null; end: string | null };
 *   onRangeChange?: (range: { start: string | null; end: string | null }) => void;
 *   loggedPeriods?: Array<{ start: string; end: string }>;
 *   onLoggedPeriodRemove?: (key: string) => void;
 *   defaultMonth?: Date;
 *   maxDate?: string | null;
 *   className?: string;
 *   id?: string;
 *   'aria-label'?: string;
 * }} props
 */
export default function PeriodRangeCalendar({
    rangeValue = { start: null, end: null },
    onRangeChange,
    loggedPeriods = [],
    onLoggedPeriodRemove,
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

    const cycleProjection = useMemo(
        () => buildProjectedCycles(loggedPeriods, referenceDate),
        [loggedPeriods, referenceDate],
    );

    const initialMonth =
        defaultMonth ??
        parseIsoDate(rangeValue?.start ?? '') ??
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

        const start = rangeValue?.start ?? null;
        const end = rangeValue?.end ?? null;

        if (isInActiveSelection(iso, start, end)) {
            onRangeChange?.({ start: null, end: null });

            return;
        }

        const loggedMatch = findLoggedPeriodContaining(iso, loggedPeriods);

        if (loggedMatch) {
            onLoggedPeriodRemove?.(periodEntryKey(loggedMatch));

            return;
        }

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

    const ariaSelectedFor = (iso) => resolveCombinedDayRangeRole(iso, rangeValue, loggedPeriods) !== null;

    return (
        <div
            id={id}
            className={`relative mx-auto box-border w-full min-w-[280px] max-w-[400px] ${className}`.trim()}
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

                <div
                    className="w-full min-w-0 p-2 sm:p-3"
                    role="grid"
                    aria-labelledby={monthHeadingId}
                >
                    <div className={`${gridCols7} mb-2 gap-1`} role="row">
                        {WEEKDAY_LABELS_MON_FIRST.map((wd) => (
                            <div
                                key={wd}
                                role="columnheader"
                                className="flex min-w-0 items-center justify-center font-sans text-[11px] font-bold uppercase tracking-wide text-grey-94"
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
                                    const isClickDisabled = isIsoInFuture(iso, referenceDate);

                                    // Priority: logged/draft → ovulation → fertile → predicted period
                                    const visualState = resolveDayCellVisualState(
                                        iso,
                                        rangeValue,
                                        loggedPeriods,
                                        cycleProjection,
                                        referenceDate,
                                    );
                                    const containingRange = visualState.logged
                                        ? findContainingRange(iso, rangeValue, loggedPeriods)
                                        : null;
                                    const showOvulationDot = inMonth && visualState.isOvulation;

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
                                                    containingRange,
                                                    iso,
                                                    isClickDisabled,
                                                    isToday,
                                                )}
                                                aria-label={new Intl.DateTimeFormat(undefined, {
                                                    weekday: 'long',
                                                    month: 'long',
                                                    day: 'numeric',
                                                    year: 'numeric',
                                                }).format(date)}
                                                aria-selected={ariaSelectedFor(iso)}
                                                aria-current={isToday && inMonth ? 'date' : undefined}
                                            >
                                                <span>{dNum}</span>
                                                {showOvulationDot ? (
                                                    <span
                                                        className="block h-1.5 w-1.5 shrink-0 rounded-full bg-brand-accent"
                                                        aria-hidden="true"
                                                    />
                                                ) : (
                                                    <span className="block h-1.5 w-1.5 shrink-0" aria-hidden="true" />
                                                )}
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
