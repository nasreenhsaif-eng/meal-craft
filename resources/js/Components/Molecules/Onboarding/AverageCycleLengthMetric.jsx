function IconCycleArrow() {
    return (
        <svg className="block h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path
                d="M12 4a8 8 0 1 1-7.7 6"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinecap="round"
            />
            <path
                d="M4 4v4h4"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <path
                d="M12 20a8 8 0 1 0 7.7-6"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinecap="round"
            />
            <path
                d="M20 20v-4h-4"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

/**
 * @param {{
 *   days: number;
 *   isStandard?: boolean;
 *   className?: string;
 * }} props
 */
export default function AverageCycleLengthMetric({ days, isStandard = false, className = '' }) {
    const label = isStandard ? `${days} Days (Standard)` : `${days} Days`;

    return (
        <div
            className={`flex items-center gap-3 rounded-[12px] border border-border-light bg-grey-96 px-4 py-3 ${className}`.trim()}
            role="status"
            aria-live="polite"
        >
            <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-border-light bg-white text-brand-primary-pressed">
                <IconCycleArrow />
            </span>
            <p className="m-0 font-montserrat text-sm leading-snug text-grey-33">
                Your Calculated Cycle Length:{' '}
                <span className="font-bold text-brand-primary-pressed">{label}</span>
            </p>
        </div>
    );
}

export { AverageCycleLengthMetric };
