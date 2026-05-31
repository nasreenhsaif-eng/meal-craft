import { formatPeriodRangeLabel, periodEntryKey } from './periodTrackingUtils.js';

function IconPeriodDrop() {
    return (
        <svg className="block h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path
                d="M12 21c-3.5-2.5-6-6.5-6-10a6 6 0 1 1 12 0c0 3.5-2.5 7.5-6 10Z"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinejoin="round"
            />
        </svg>
    );
}

function IconTrash() {
    return (
        <svg className="block h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M4 7h16" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
            <path d="M9 7V5.5A1.5 1.5 0 0 1 10.5 4h3A1.5 1.5 0 0 1 15 5.5V7" stroke="currentColor" strokeWidth="1.75" />
            <path
                d="M7 7l.8 12.2A1.8 1.8 0 0 0 9.6 21h4.8a1.8 1.8 0 0 0 1.8-1.8L17 7"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinejoin="round"
            />
        </svg>
    );
}

/**
 * @param {{
 *   periods?: Array<{ start: string; end: string }>;
 *   onRemove?: (key: string) => void;
 *   className?: string;
 * }} props
 */
export function LoggedPeriodsList({ periods = [], onRemove, className = '' }) {
    return (
        <section className={`w-full ${className}`.trim()} aria-labelledby="logged-periods-heading">
            <h2
                id="logged-periods-heading"
                className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-grey-94"
            >
                Logged periods
            </h2>

            {periods.length === 0 ? (
                <p className="mt-3 rounded-[12px] border border-border-light bg-grey-96 px-4 py-3 text-sm text-grey-94">
                    Select a start and end date on the calendar to log a period.
                </p>
            ) : (
                <ul className="mt-3 flex flex-col gap-2">
                    {periods.map((period) => {
                        const key = periodEntryKey(period);

                        return (
                            <li key={key}>
                                <div className="flex items-center justify-between gap-3 rounded-[12px] border border-border-light bg-grey-96 px-4 py-3">
                                    <div className="flex min-w-0 items-center gap-2 text-status-error">
                                        <IconPeriodDrop />
                                        <span className="truncate font-montserrat text-sm font-semibold text-grey-33">
                                            {formatPeriodRangeLabel(period.start, period.end)}
                                        </span>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => onRemove?.(key)}
                                        className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] text-grey-94 transition-colors hover:bg-white hover:text-grey-33 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-status-error focus-visible:ring-offset-2"
                                        aria-label={`Remove logged period ${formatPeriodRangeLabel(period.start, period.end)}`}
                                    >
                                        <IconTrash />
                                    </button>
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}
        </section>
    );
}

export default LoggedPeriodsList;
