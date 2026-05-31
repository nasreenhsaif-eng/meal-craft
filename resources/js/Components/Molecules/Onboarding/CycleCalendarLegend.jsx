/**
 * Legend for period-tracking calendar forecast markers.
 */
export default function CycleCalendarLegend({ className = '' }) {
    return (
        <div
            className={`flex flex-wrap items-center justify-center gap-x-4 gap-y-2 rounded-[12px] border border-border-light bg-grey-96 px-4 py-3 ${className}`.trim()}
            aria-label="Calendar legend"
        >
            <div className="inline-flex items-center gap-2">
                <span
                    className="inline-block h-3 w-8 rounded-full bg-status-error/50"
                    aria-hidden="true"
                />
                <span className="font-montserrat text-xs font-semibold text-grey-33">Predicted Period</span>
            </div>

            <div className="inline-flex items-center gap-2">
                <span
                    className="inline-block h-3 w-8 rounded-full bg-brand-accent/20"
                    aria-hidden="true"
                />
                <span className="font-montserrat text-xs font-semibold text-grey-33">Fertile Window</span>
            </div>

            <div className="inline-flex items-center gap-2">
                <span className="inline-flex h-3 w-8 items-end justify-center" aria-hidden="true">
                    <span className="mb-0.5 block h-1.5 w-1.5 rounded-full bg-brand-accent" />
                </span>
                <span className="font-montserrat text-xs font-semibold text-grey-33">Ovulation Day</span>
            </div>
        </div>
    );
}
