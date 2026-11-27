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

/** Hamburger / mobile nav. */
export function IconMenu({ className }) {
    return (
        <svg className={className} width={24} height={24} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
        </svg>
    );
}

/** Close / dismiss (X). */
export function IconClose({ className }) {
    return (
        <svg className={className} width={24} height={24} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M18 6 6 18M6 6l12 12" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
        </svg>
    );
}

/** Search magnifier. */
export function IconSearch({ className }) {
    return (
        <svg className={className} width={18} height={18} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="11" cy="11" r="7" stroke="currentColor" strokeWidth="2" />
            <path d="M20 20l-3.5-3.5" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
        </svg>
    );
}

/** Landing account / person silhouette. */
export function IconPerson({ className }) {
    return (
        <svg className={className} width={22} height={22} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="8" r="3.25" stroke="currentColor" strokeWidth="1.8" />
            <path
                d="M5.5 19.25c1.4-3.1 3.7-4.75 6.5-4.75s5.1 1.65 6.5 4.75"
                stroke="currentColor"
                strokeWidth="1.8"
                strokeLinecap="round"
            />
        </svg>
    );
}

/** Check inside a circle (landing plan bullets). */
export function IconCheckCircle({ className }) {
    return (
        <svg className={className} width={16} height={16} viewBox="0 0 16 16" fill="none" aria-hidden="true">
            <circle cx="8" cy="8" r="7" stroke="currentColor" strokeWidth="1.4" />
            <path
                d="M4.7 8.2 6.8 10.2 11.3 5.8"
                stroke="currentColor"
                strokeWidth="1.4"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

/** Customer app tab — home. */
export function IconHome({ className }) {
    return (
        <svg className={className} width={22} height={22} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path
                d="M4 11.5 12 4l8 7.5V20a2 2 0 0 1-2 2h-4v-6H10v6H6a2 2 0 0 1-2-2v-8.5Z"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinejoin="round"
            />
        </svg>
    );
}

/** Customer app tab — meal plan (fork/knife). */
export function IconMeals({ className }) {
    return (
        <svg className={className} width={22} height={22} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path
                d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2M7 2v20M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h2l1 5M15 15h4"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

/** Customer app tab — favourites (heart). */
export function IconFavourites({ className }) {
    return (
        <svg className={className} width={22} height={22} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path
                d="M19.5 12.572 12 20l-7.5-7.428A5 5 0 1 1 12 6.013a5 5 0 1 1 7.5 6.559Z"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinejoin="round"
            />
        </svg>
    );
}

/** Customer app tab — shop bag. */
export function IconShop({ className }) {
    return (
        <svg className={className} width={22} height={22} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path
                d="M6 7h12l-1 13H7L6 7ZM9 7V5a3 3 0 0 1 6 0v2"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

/** Customer app tab — tools / wrench. */
export function IconTools({ className }) {
    return (
        <svg className={className} width={22} height={22} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path
                d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18v3h3l6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.1-2.1 2.6-2.4Z"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

/** Customer app tab — profile. */
export function IconProfile({ className }) {
    return (
        <svg className={className} width={22} height={22} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path
                d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

/** Cart (customer header). */
export function IconCart({ className }) {
    return (
        <svg className={className} width={18} height={18} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path
                d="M6 6h15l-1.5 9h-12L6 6Zm0 0L5 3H2"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <circle cx="9" cy="20" r="1.25" fill="currentColor" />
            <circle cx="18" cy="20" r="1.25" fill="currentColor" />
        </svg>
    );
}

/** Six-dot drag handle. */
export function IconDragHandle({ className }) {
    return (
        <svg
            className={className}
            width={16}
            height={16}
            viewBox="0 0 16 16"
            fill="currentColor"
            aria-hidden="true"
        >
            <circle cx="5" cy="4" r="1.25" />
            <circle cx="11" cy="4" r="1.25" />
            <circle cx="5" cy="8" r="1.25" />
            <circle cx="11" cy="8" r="1.25" />
            <circle cx="5" cy="12" r="1.25" />
            <circle cx="11" cy="12" r="1.25" />
        </svg>
    );
}

/** Info circle (onboarding inline copy). */
export function IconInfo({ className }) {
    return (
        <svg className={className} width={15} height={15} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="9" stroke="currentColor" strokeWidth="1.5" />
            <path d="M12 11v5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
            <circle cx="12" cy="8" r="0.75" fill="currentColor" />
        </svg>
    );
}

/** Gender — male. */
export function IconMale({ className }) {
    return (
        <svg className={className} width={22} height={22} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="10" cy="14" r="4" stroke="currentColor" strokeWidth="1.75" />
            <path d="M14 10l6-6M16 4h4v4" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
        </svg>
    );
}

/** Gender — female. */
export function IconFemale({ className }) {
    return (
        <svg className={className} width={22} height={22} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="9" r="4" stroke="currentColor" strokeWidth="1.75" />
            <path d="M12 13v7M9 17h6" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
        </svg>
    );
}

/** Landing / marketing — calendar. */
export function IconCalendar({ className }) {
    return (
        <svg className={className} width={22} height={22} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <rect x="4" y="5.5" width="16" height="14" rx="2" stroke="currentColor" strokeWidth="1.8" />
            <path d="M4 10h16M8 3.5v4M16 3.5v4" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
        </svg>
    );
}

/** Landing / marketing — shield. */
export function IconShield({ className }) {
    return (
        <svg className={className} width={22} height={22} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path
                d="M12 3.5 19 6.2v5.4c0 4.1-2.8 6.8-7 8.1-4.2-1.3-7-4-7-8.1V6.2L12 3.5Z"
                stroke="currentColor"
                strokeWidth="1.8"
            />
        </svg>
    );
}

/** Landing / marketing — heart. */
export function IconHeart({ className }) {
    return (
        <svg className={className} width={22} height={22} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path
                d="M12 19.5s-6.2-3.8-6.2-8.2a3.6 3.6 0 0 1 6.2-2.4 3.6 3.6 0 0 1 6.2 2.4c0 4.4-6.2 8.2-6.2 8.2Z"
                stroke="currentColor"
                strokeWidth="1.8"
            />
        </svg>
    );
}

/** Landing / marketing — tag. */
export function IconTag({ className }) {
    return (
        <svg className={className} width={22} height={22} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path
                d="M4 12.5 11.2 5.3a2 2 0 0 1 1.4-.6H19v6.4a2 2 0 0 1-.6 1.4L11.2 19.7a2 2 0 0 1-2.8 0L4 15.3a2 2 0 0 1 0-2.8Z"
                stroke="currentColor"
                strokeWidth="1.8"
            />
            <circle cx="16" cy="8" r="1.2" fill="currentColor" />
        </svg>
    );
}
