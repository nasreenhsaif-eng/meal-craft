import { useEffect, useId, useMemo, useRef, useState } from 'react';
import TextInput from '../../Atoms/TextInput/TextInput.jsx';
import Calendar from './Calendar.jsx';
import { parseIsoDate } from './calendarDateUtils.js';

/**
 * @param {string | null | undefined} iso
 * @returns {string}
 */
function formatLongDate(iso) {
    const date = parseIsoDate(iso);

    if (!date) {
        return '';
    }

    const day = date.getDate();
    const suffixes = ['th', 'st', 'nd', 'rd'];
    const v = day % 100;
    const suffix = suffixes[(v - 20) % 10] || suffixes[v] || suffixes[0];
    const month = new Intl.DateTimeFormat(undefined, { month: 'long' }).format(date);

    return `${day}${suffix} ${month}`;
}

/**
 * @param {{ start?: string | null; end?: string | null }} range
 * @returns {string}
 */
function formatRangeLabel(range) {
    const start = formatLongDate(range?.start);
    const end = formatLongDate(range?.end);

    if (start && end) {
        return `${start} to ${end}`;
    }

    if (start) {
        return `${start} to …`;
    }

    return '';
}

function IconCalendar() {
    return (
        <svg width={16} height={16} viewBox="0 0 24 24" fill="none" aria-hidden>
            <rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" strokeWidth="2" />
            <path d="M16 2v4M8 2v4M3 10h18" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
        </svg>
    );
}

/**
 * One-line field that opens a range Calendar dropdown.
 *
 * @param {{
 *   label?: string;
 *   rangeValue?: { start: string | null; end: string | null };
 *   onRangeChange: (range: { start: string | null; end: string | null }) => void;
 *   error?: string;
 *   placeholder?: string;
 *   minDate?: string | null;
 *   maxDate?: string | null;
 *   className?: string;
 * }} props
 */
export default function CalendarRangeField({
    label = 'Plan week',
    rangeValue = { start: null, end: null },
    onRangeChange,
    error,
    placeholder = 'Select week',
    minDate = null,
    maxDate = null,
    className = '',
}) {
    const [open, setOpen] = useState(false);
    const rootRef = useRef(null);
    const calendarId = useId();
    const displayValue = useMemo(() => formatRangeLabel(rangeValue), [rangeValue]);

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        const handlePointerDown = (event) => {
            if (rootRef.current && !rootRef.current.contains(event.target)) {
                setOpen(false);
            }
        };

        const handleKeyDown = (event) => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', handlePointerDown);
        document.addEventListener('keydown', handleKeyDown);

        return () => {
            document.removeEventListener('mousedown', handlePointerDown);
            document.removeEventListener('keydown', handleKeyDown);
        };
    }, [open]);

    const defaultMonth = parseIsoDate(rangeValue?.start ?? '') ?? new Date();

    return (
        <div ref={rootRef} className={`relative ${className}`.trim()}>
            <TextInput
                label={label}
                type="text"
                readOnly
                value={displayValue}
                placeholder={placeholder}
                error={error}
                onChange={() => {}}
                onClick={() => setOpen(true)}
                onFocus={() => setOpen(true)}
                aria-haspopup="dialog"
                aria-expanded={open}
                aria-controls={open ? calendarId : undefined}
                suffixButton={{
                    icon: <IconCalendar />,
                    ariaLabel: open ? 'Close calendar' : 'Open calendar',
                    onClick: () => setOpen((current) => !current),
                }}
                className="w-full !max-w-full"
            />
            {open ? (
                <div
                    id={calendarId}
                    role="dialog"
                    aria-label={label}
                    className="absolute right-0 z-40 mt-2 w-[min(100vw-2rem,400px)] max-w-[calc(100vw-2rem)]"
                >
                    <Calendar
                        mode="range"
                        rangeValue={rangeValue}
                        onRangeChange={(next) => {
                            onRangeChange(next);
                            if (next?.start && next?.end) {
                                setOpen(false);
                            }
                        }}
                        defaultMonth={defaultMonth}
                        minDate={minDate}
                        maxDate={maxDate}
                        aria-label={label}
                        className="w-full max-w-none shadow-lg"
                    />
                </div>
            ) : null}
        </div>
    );
}
