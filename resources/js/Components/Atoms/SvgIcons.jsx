/**
 * Inline SVG icon primitives (edit, delete, layout toggles, etc.).
 * Kept separate from the `Icons/` atom folder (RoundIconButton, SquareCheckbox) to avoid path/name clashes.
 */
export function IconEdit({ className }) {
    return (
        <svg className={className} width={18} height={18} viewBox="0 0 24 24" fill="none" aria-hidden>
            <path d="M12 20h9" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
            <path
                d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinejoin="round"
            />
        </svg>
    );
}

export function IconDelete({ className }) {
    return (
        <svg className={className} width={18} height={18} viewBox="0 0 24 24" fill="none" aria-hidden>
            <path d="M3 6h18" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
            <path
                d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinejoin="round"
            />
            <path
                d="M6 6l1 16a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-16"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinejoin="round"
            />
            <path d="M10 11v6M14 11v6" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
        </svg>
    );
}

export function IconClock({ className }) {
    return (
        <svg className={className} width={16} height={16} viewBox="0 0 24 24" fill="none" aria-hidden>
            <circle cx="12" cy="12" r="9" stroke="currentColor" strokeWidth="2" />
            <path d="M12 7v6l4 2" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

export function IconEye({ className }) {
    return (
        <svg className={className} width={18} height={18} viewBox="0 0 24 24" fill="none" aria-hidden>
            <path
                d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinejoin="round"
            />
            <circle cx="12" cy="12" r="3" stroke="currentColor" strokeWidth="2" />
        </svg>
    );
}

export function IconEyeOff({ className }) {
    return (
        <svg className={className} width={18} height={18} viewBox="0 0 24 24" fill="none" aria-hidden>
            <path
                d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinejoin="round"
            />
            <circle cx="12" cy="12" r="3" stroke="currentColor" strokeWidth="2" />
            <path d="M3.5 3.5l17 17" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" />
            <path
                d="M9.2 9.2a3 3 0 0 1 5.6 1.6"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
            />
        </svg>
    );
}

export function IconChevronDown({ className }) {
    return (
        <svg className={className} width={16} height={16} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M6 9l6 6 6-6" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

/** Grid layout (e.g. meal library view toggle). */
export function IconLayoutGrid({ className }) {
    return (
        <svg className={className} width={20} height={20} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path
                d="M4 4h7v7H4V4zm9 0h7v7h-7V4zM4 13h7v7H4v-7zm9 0h7v7h-7v-7z"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinejoin="round"
            />
        </svg>
    );
}

/** List layout (e.g. meal library view toggle). */
export function IconLayoutList({ className }) {
    return (
        <svg className={className} width={20} height={20} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M8 6h13M8 12h13M8 18h13" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
            <path
                d="M4 6h.01M4 12h.01M4 18h.01"
                stroke="currentColor"
                strokeWidth="3"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}
