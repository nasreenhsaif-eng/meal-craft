/** Uniform stroke for all diet protocol option icons */
export const DIET_PROTOCOL_ICON_STROKE = 1.75;

const iconClass = (className = '') => `block h-[22px] w-[22px] shrink-0 ${className}`.trim();

/**
 * Classic symmetrical balance scale — nutritional equilibrium.
 *
 * @param {{ className?: string }} props
 */
export function IconBalanced({ className = '' }) {
    const s = DIET_PROTOCOL_ICON_STROKE;

    return (
        <svg viewBox="0 0 24 24" fill="none" aria-hidden className={iconClass(className)}>
            <path d="M12 5v13" stroke="currentColor" strokeWidth={s} strokeLinecap="round" />
            <path d="M9 18h6" stroke="currentColor" strokeWidth={s} strokeLinecap="round" />
            <path d="M4.5 7.5h15" stroke="currentColor" strokeWidth={s} strokeLinecap="round" />
            <path d="M6 7.5V10.5" stroke="currentColor" strokeWidth={s} strokeLinecap="round" />
            <path d="M18 7.5V10.5" stroke="currentColor" strokeWidth={s} strokeLinecap="round" />
            <circle cx="6" cy="13.25" r="2.35" stroke="currentColor" strokeWidth={s} />
            <circle cx="18" cy="13.25" r="2.35" stroke="currentColor" strokeWidth={s} />
        </svg>
    );
}

/**
 * Side-profile avocado with stem and seed — ketobiotic protocol.
 *
 * @param {{ className?: string }} props
 */
export function IconKetobiotic({ className = '' }) {
    const s = DIET_PROTOCOL_ICON_STROKE;

    return (
        <svg viewBox="0 0 24 24" fill="none" aria-hidden className={iconClass(className)}>
            <path
                d="M12 3.25v1.75"
                stroke="currentColor"
                strokeWidth={s}
                strokeLinecap="round"
            />
            <path
                d="M12 5.35c-3.35 0-5.75 3-5.75 6.85 0 2.85 1.75 5.45 5.75 7.15 4-1.7 5.75-4.3 5.75-7.15 0-3.85-2.4-6.85-5.75-6.85Z"
                stroke="currentColor"
                strokeWidth={s}
                strokeLinejoin="round"
            />
            <circle cx="12" cy="12.15" r="2.5" stroke="currentColor" strokeWidth={s} />
        </svg>
    );
}

/**
 * Three sequential moon phases — cyclical rhythm (not brand logo).
 *
 * @param {{ className?: string }} props
 */
export function IconCycleSync({ className = '' }) {
    const s = DIET_PROTOCOL_ICON_STROKE;

    return (
        <svg viewBox="0 0 24 24" fill="none" aria-hidden className={iconClass(className)}>
            <path
                d="M6.5 12a3 3 0 1 0 0-6 3.2 3.2 0 0 0 0 6Z"
                stroke="currentColor"
                strokeWidth={s}
                strokeLinejoin="round"
            />
            <path
                d="M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z"
                stroke="currentColor"
                strokeWidth={s}
            />
            <path
                d="M17.5 12a3 3 0 1 0 0-6 3.2 3.2 0 0 0 0 6Z"
                stroke="currentColor"
                strokeWidth={s}
                strokeLinejoin="round"
            />
            <path
                d="M4 17.5c2.2 1.4 4.8 2.1 7.5 2.1s5.3-.7 7.5-2.1"
                stroke="currentColor"
                strokeWidth={s}
                strokeLinecap="round"
            />
        </svg>
    );
}

/**
 * Empowering health shield with cellular crest line-art.
 *
 * @param {{ className?: string }} props
 */
export function IconSickleCellWarrior({ className = '' }) {
    const s = DIET_PROTOCOL_ICON_STROKE;

    return (
        <svg viewBox="0 0 24 24" fill="none" aria-hidden className={iconClass(className)}>
            <path
                d="M12 2.75 5.25 6.1v5.55c0 4.15 2.85 8.05 6.75 9.35 3.9-1.3 6.75-5.2 6.75-9.35V6.1L12 2.75Z"
                stroke="currentColor"
                strokeWidth={s}
                strokeLinejoin="round"
            />
            <path
                d="M9.5 11.2c.55-.9 1.45-1.45 2.5-1.45s1.95.55 2.5 1.45"
                stroke="currentColor"
                strokeWidth={s}
                strokeLinecap="round"
            />
            <circle cx="10" cy="13.8" r=".85" stroke="currentColor" strokeWidth={s} />
            <circle cx="14" cy="13.8" r=".85" stroke="currentColor" strokeWidth={s} />
            <path
                d="M12 15.8v2.2M10.2 16.9h3.6"
                stroke="currentColor"
                strokeWidth={s}
                strokeLinecap="round"
            />
        </svg>
    );
}

/**
 * Butterfly silhouette — thyroid gland metaphor with clean geometric lobes.
 *
 * @param {{ className?: string }} props
 */
export function IconThyroid({ className = '' }) {
    const s = DIET_PROTOCOL_ICON_STROKE;

    return (
        <svg viewBox="0 0 24 24" fill="none" aria-hidden className={iconClass(className)}>
            <path
                d="M8.25 9.75c-2.35 0-4 1.85-4 4.05 0 2.45 2.05 4.45 4.55 4.2 1.05-.1 1.95-.65 2.45-1.45"
                stroke="currentColor"
                strokeWidth={s}
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <path
                d="M15.75 9.75c2.35 0 4 1.85 4 4.05 0 2.45-2.05 4.45-4.55 4.2-1.05-.1-1.95-.65-2.45-1.45"
                stroke="currentColor"
                strokeWidth={s}
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <path d="M12 9.5v5" stroke="currentColor" strokeWidth={s} strokeLinecap="round" />
            <path d="M10.75 12h2.5" stroke="currentColor" strokeWidth={s} strokeLinecap="round" />
        </svg>
    );
}

/** @deprecated Use {@link IconBalanced} */
export const IconBalancedPlate = IconBalanced;

/** @deprecated Use {@link IconCycleSync} */
export const IconCycleSyncSeal = IconCycleSync;

/** @deprecated Use {@link IconKetobiotic} */
export const IconKetogenic = IconKetobiotic;
