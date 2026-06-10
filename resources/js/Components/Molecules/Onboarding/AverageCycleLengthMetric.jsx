function IconCycleArrow() {
    return (
        <svg
            width={20}
            height={20}
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
            className="size-5 shrink-0"
        >
            <path
                d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182"
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
            <span className="flex size-9 shrink-0 items-center justify-center rounded-full border border-border-light bg-white text-brand-primary-pressed">
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
