/** Thin-stroke information circle for inline onboarding copy */
function IconInfoCircle({ className = '' }) {
    return (
        <svg
            width="15"
            height="15"
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden
            className={`mt-0.5 block shrink-0 ${className}`.trim()}
        >
            <circle cx="12" cy="12" r="9" stroke="currentColor" strokeWidth="1.5" />
            <path d="M12 11v5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
            <circle cx="12" cy="8" r="0.75" fill="currentColor" />
        </svg>
    );
}

/**
 * Bold inline description shown beneath a selected onboarding option.
 *
 * @param {{
 *   children: import('react').ReactNode;
 *   id?: string;
 *   className?: string;
 * }} props
 */
export function OnboardingInlineDescription({ children, id, className = '' }) {
    return (
        <p
            id={id}
            className={[
                'flex items-start gap-1.5 px-1 pt-2 pb-1',
                'font-montserrat text-[13px] font-bold leading-snug text-protocol-selected',
                className,
            ].join(' ')}
        >
            <IconInfoCircle className="opacity-90" />
            <span>{children}</span>
        </p>
    );
}

export default OnboardingInlineDescription;
