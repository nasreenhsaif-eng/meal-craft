const ICON_BASE = 'block h-4 w-4 shrink-0';

/**
 * @param {{ className?: string }} props
 */
export function IconDairy({ className = '' }) {
    return (
        <svg
            className={`${ICON_BASE} ${className}`.trim()}
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
        >
            <path
                d="M9 3h6l1.5 3.5V20a2.5 2.5 0 0 1-2.5 2.5h-3.5A2.5 2.5 0 0 1 8 20V6.5L9 3Z"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinejoin="round"
            />
            <path d="M9 3h6" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
            <path d="M10 10h4" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
        </svg>
    );
}

/**
 * @param {{ className?: string }} props
 */
export function IconGluten({ className = '' }) {
    return (
        <svg
            className={`${ICON_BASE} ${className}`.trim()}
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
        >
            <path d="M12 3v18" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
            <path d="M12 6c-2.9 0-5 2.2-5 5 2.9 0 5-2.2 5-5Z" stroke="currentColor" strokeWidth="1.75" />
            <path d="M12 10.6c-2.9 0-5 2.2-5 5 2.9 0 5-2.2 5-5Z" stroke="currentColor" strokeWidth="1.75" />
            <path d="M12 15.2c-2.6 0-4.6 2-4.6 4.6 2.6 0 4.6-2 4.6-4.6Z" stroke="currentColor" strokeWidth="1.75" />
        </svg>
    );
}

/**
 * @param {{ className?: string }} props
 */
export function IconEggs({ className = '' }) {
    return (
        <svg
            className={`${ICON_BASE} ${className}`.trim()}
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
        >
            <path
                d="M8.5 6.5c0-2.5 1.6-4.5 3.5-4.5s3.5 2 3.5 4.5c0 3.8-1.6 6.5-3.5 6.5S8.5 10.3 8.5 6.5Z"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinejoin="round"
            />
            <path
                d="M15.5 8.5c0-2.2 1.4-4 3-4s3 1.8 3 4c0 3.4-1.4 5.8-3 5.8s-3-2.4-3-5.8Z"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinejoin="round"
            />
        </svg>
    );
}

/**
 * @param {{ className?: string }} props
 */
export function IconSoy({ className = '' }) {
    return (
        <svg
            className={`${ICON_BASE} ${className}`.trim()}
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
        >
            <path
                d="M12 20c-4-2.5-7-5.5-7-9.5C5 7 8 4.5 12 4.5S19 7 19 10.5c0 4-3 7-7 9.5Z"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinejoin="round"
            />
            <circle cx="9.5" cy="11" r="1" fill="currentColor" />
            <circle cx="12" cy="13.5" r="1" fill="currentColor" />
            <circle cx="14.5" cy="10.5" r="1" fill="currentColor" />
        </svg>
    );
}

/**
 * @param {{ className?: string }} props
 */
export function IconNightshades({ className = '' }) {
    return (
        <svg
            className={`${ICON_BASE} ${className}`.trim()}
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
        >
            <path
                d="M12 4c-3.5 0-6 3-6 6.5 0 2.2 1 4.1 2.5 5.2V19a1 1 0 0 0 1 1h5a1 1 0 0 0 1-1v-3.3C17 14.6 18 12.7 18 10.5 18 7 15.5 4 12 4Z"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinejoin="round"
            />
            <path d="M12 4V2.5" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
            <path d="M10 2.5h4" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
        </svg>
    );
}

/**
 * @param {{ className?: string }} props
 */
export function IconBeans({ className = '' }) {
    return (
        <svg
            className={`${ICON_BASE} ${className}`.trim()}
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
        >
            <path
                d="M9.5 5.5c-2.5 1.2-4 3.8-4 6.8 0 4.2 2.9 7.2 6.5 7.2s6.5-3 6.5-7.2c0-3-1.5-5.6-4-6.8-1.2-.6-2.4-.8-2.5-.8s-1.3.2-2.5.8Z"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinejoin="round"
            />
            <path d="M12 5.5v13.5" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
        </svg>
    );
}

/**
 * @param {{ className?: string }} props
 */
export function IconNuts({ className = '' }) {
    return (
        <svg
            className={`${ICON_BASE} ${className}`.trim()}
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
        >
            <path
                d="M12 4c3 0 5.5 2.2 5.5 5.6 0 2.2-1 3.7-2.2 5-1.2 1.3-2 2.3-2 3.9 0 1.4-1.2 2.5-2.8 2.5S8.7 19.9 8.7 18.5c0-1.6-.8-2.6-2-3.9-1.2-1.3-2.2-2.8-2.2-5C4.5 6.2 7 4 12 4Z"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinejoin="round"
            />
            <path d="M12 4v15" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
        </svg>
    );
}

/**
 * @param {{ className?: string }} props
 */
export function IconSpicy({ className = '' }) {
    return (
        <svg
            className={`${ICON_BASE} ${className}`.trim()}
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
        >
            <path d="M12 3v2.5" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
            <path d="M9.5 3h5" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
            <path
                d="M12 7.5c2.9 0 5.2 2.3 5.2 5.6 0 2.6-1 4.6-2.6 6-.8.7-1.6 1.3-2 2.9h-1.2c-.4-1.6-1.2-2.2-2-2.9-1.6-1.4-2.6-3.4-2.6-6 0-3.3 2.3-5.6 5.2-5.6Z"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinejoin="round"
            />
        </svg>
    );
}

/**
 * @param {{ className?: string }} props
 */
export function IconShellfish({ className = '' }) {
    return (
        <svg
            className={`${ICON_BASE} ${className}`.trim()}
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
        >
            <path
                d="M6 13c0-3.5 2.7-7 6-7s6 3.5 6 7-2.7 5-6 5-6-1.5-6-5Z"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinejoin="round"
            />
            <path d="M8 13h8" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
            <path d="M9.5 10.5h5" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
            <path d="M12 6v1.5" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
            <path d="M10 6.5l1-1.5M14 6.5l-1-1.5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
        </svg>
    );
}

/**
 * @param {{ className?: string }} props
 */
export function IconOther({ className = '' }) {
    return (
        <svg
            className={`${ICON_BASE} ${className}`.trim()}
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
        >
            <path d="M12 8v8M8 12h8" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
            <path d="M5 6h14v12H5z" stroke="currentColor" strokeWidth="1.75" strokeLinejoin="round" />
            <path d="M8 17h2M14 17h2" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
        </svg>
    );
}
