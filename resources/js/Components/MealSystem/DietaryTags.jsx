const PURE_BG = '#F8F9F6';
const PURE_FG = '#5A6B44';

const BASE =
    'box-border inline-flex h-[26px] min-h-[26px] max-w-full shrink-0 items-center justify-center gap-2 rounded-full px-3 py-1 font-montserrat text-[11px] font-bold uppercase leading-none tracking-wide whitespace-nowrap';

const ICON_BASE = 'shrink-0 block';

function IconWheat({ className = '' }) {
    return (
        <svg
            className={`${ICON_BASE} ${className}`.trim()}
            width={16}
            height={16}
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
        >
            <path d="M12 3v18" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
            <path d="M12 6c-2.9 0-5 2.2-5 5 2.9 0 5-2.2 5-5Z" stroke="currentColor" strokeWidth="1.75" />
            <path d="M12 6c2.9 0 5 2.2 5 5-2.9 0-5-2.2-5-5Z" stroke="currentColor" strokeWidth="1.75" />
            <path d="M12 10.6c-2.9 0-5 2.2-5 5 2.9 0 5-2.2 5-5Z" stroke="currentColor" strokeWidth="1.75" />
            <path d="M12 10.6c2.9 0 5 2.2 5 5-2.9 0-5-2.2-5-5Z" stroke="currentColor" strokeWidth="1.75" />
            <path d="M12 15.2c-2.6 0-4.6 2-4.6 4.6 2.6 0 4.6-2 4.6-4.6Z" stroke="currentColor" strokeWidth="1.75" />
            <path d="M12 15.2c2.6 0 4.6 2 4.6 4.6-2.6 0-4.6-2-4.6-4.6Z" stroke="currentColor" strokeWidth="1.75" />
        </svg>
    );
}

function IconLeaf({ className = '' }) {
    return (
        <svg
            className={`${ICON_BASE} ${className}`.trim()}
            width={16}
            height={16}
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
        >
            <path
                d="M20 4c-7 0-14 5-14 12 0 2.2 1.8 4 4 4 7 0 12-7 12-14 0-1.1-.9-2-2-2Z"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinejoin="round"
            />
            <path d="M7 17c3-4 7-7 12-9" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
            <path d="M10.5 14.5c1.5-1.2 3.2-2.2 5.4-3.3" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
        </svg>
    );
}

function IconSprout({ className = '' }) {
    return (
        <svg
            className={`${ICON_BASE} ${className}`.trim()}
            width={16}
            height={16}
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
        >
            <path d="M12 21v-7" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
            <path
                d="M12 14c-4.5 0-7-3-7-7 4.5 0 7 3 7 7Z"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinejoin="round"
            />
            <path
                d="M12 14c4.5 0 7-3 7-7-4.5 0-7 3-7 7Z"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinejoin="round"
            />
        </svg>
    );
}

function IconCarton({ className = '' }) {
    return (
        <svg
            className={`${ICON_BASE} ${className}`.trim()}
            width={16}
            height={16}
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
        >
            <path
                d="M8 3h8l2 4v14H6V7l2-4Z"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinejoin="round"
            />
            <path d="M8 3l2 4h4l2-4" stroke="currentColor" strokeWidth="1.75" strokeLinejoin="round" />
            <path d="M9 12h6" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
        </svg>
    );
}

function IconNutOff({ className = '' }) {
    return (
        <svg
            className={`${ICON_BASE} ${className}`.trim()}
            width={16}
            height={16}
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
        >
            <path
                d="M12 4c3 0 5.5 2.2 5.5 5.6 0 2.2-1 3.7-2.2 5-1.2 1.3-2 2.3-2 3.9 0 1.4-1.2 2.5-1.3 2.5S10.7 19.9 10.7 18.5c0-1.6-.8-2.6-2-3.9-1.2-1.3-2.2-2.8-2.2-5C6.5 6.2 9 4 12 4Z"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinejoin="round"
            />
            <path d="M6 6l12 12" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" />
        </svg>
    );
}

function IconBicep({ className = '' }) {
    return (
        <svg
            className={`${ICON_BASE} ${className}`.trim()}
            width={20}
            height={20}
            viewBox="0 0 118 118"
            fill="none"
            style={{ display: 'block' }}
            aria-hidden="true"
        >
            <path
                d="M9.91915 99.832C15.4683 107.77 39.6232 114.002 51.0312 99.1407C63.3705 105.043 83.7231 103.213 100.293 93.9733C102.598 92.6881 104.778 91.0666 106.113 88.7906C109.127 83.6522 109.198 76.524 103.7 66.0058C94.5324 43.1216 78.0448 23.0341 71.3881 14.956C70.0213 13.7097 61.3021 11.9398 55.9847 10.2392C53.636 9.51146 49.2627 9.02903 44.032 15.9221C41.5522 19.1901 30.2873 27.2161 44.5804 32.6134C46.7948 33.1768 48.4225 34.2158 58.5247 32.3704C59.8404 32.1421 63.1252 32.3704 65.44 36.4334L70.2745 43.3472C70.7244 43.9909 71.0164 44.7314 71.1059 45.5116C71.9531 52.8813 71.9255 62.1079 76.0344 66.7797C69.6885 62.1905 53.1031 56.7399 40.6069 72.2495M9.84033 63.6207C15.7807 57.971 32.9982 49.0472 51.219 61.5631"
                stroke="#5A6B44"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
                vectorEffect="non-scaling-stroke"
            />
        </svg>
    );
}

function IconKeto({ className = '' }) {
    return (
        <svg
            className={`${ICON_BASE} ${className}`.trim()}
            width={16}
            height={16}
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
        >
            <path
                d="M12 3c-3.4 0-6 3-6 6.8 0 4.4 2.7 9.2 6 11.2 3.3-2 6-6.8 6-11.2C18 6 15.4 3 12 3Z"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinejoin="round"
            />
            <path
                d="M12 7c-2 0-3.5 1.8-3.5 4.1 0 3 1.8 6.3 3.5 7.5 1.7-1.2 3.5-4.5 3.5-7.5C15.5 8.8 14 7 12 7Z"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinejoin="round"
            />
            <path
                d="M12 12.2c-.9 0-1.6.8-1.6 1.8 0 1.2.8 2.4 1.6 2.9.8-.5 1.6-1.7 1.6-2.9 0-1-.7-1.8-1.6-1.8Z"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinejoin="round"
            />
        </svg>
    );
}

function IconLowCarbMark({ className = '' }) {
    return (
        <span
            className={`${ICON_BASE} font-montserrat text-[12px] font-bold leading-none ${className}`.trim()}
            aria-hidden="true"
        >
            C↓
        </span>
    );
}

function IconPaleo({ className = '' }) {
    return (
        <svg
            className={`${ICON_BASE} ${className}`.trim()}
            width={14}
            height={14}
            viewBox="-3 -3 30 30"
            fill="none"
            overflow="visible"
            style={{ overflow: 'visible', display: 'block' }}
            aria-hidden="true"
        >
            {/* Paleo drumstick + leaf (reference icon) */}
            <path
                d="M7.6 15.8c-1.7-1.7-1.7-4.5 0-6.2l4.6-4.6c1.8-1.8 4.8-1.6 6.3.4 1.4 1.9 1.1 4.6-.7 6.3l-4.3 4.3c-1.7 1.7-4.5 1.7-6.2 0Z"
                stroke="currentColor"
                strokeWidth="1.7"
                strokeLinejoin="round"
            />
            <path
                d="M17.2 6.8l3.3-3.3"
                stroke="currentColor"
                strokeWidth="1.7"
                strokeLinecap="round"
            />
            <path
                d="M19.6 3.8c.6-.6 1.6-.6 2.2 0 .6.6.6 1.6 0 2.2"
                stroke="currentColor"
                strokeWidth="1.7"
                strokeLinecap="round"
            />
            <path
                d="M12.2 12.1l6.4 6.4"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="round"
            />
            <path
                d="M4.4 9.6c2.3 0 4.2 1.9 4.2 4.2-2.3 0-4.2-1.9-4.2-4.2Z"
                stroke="currentColor"
                strokeWidth="1.7"
                strokeLinejoin="round"
            />
            <path
                d="M4.7 13.5c1.3-1.5 2.9-2.7 4.7-3.6"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="round"
            />
            <path
                d="M9.4 10.4l-2.4-2.4"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="round"
            />
            <path
                d="M9.4 10.4l-2.7.9"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="round"
            />
        </svg>
    );
}

function iconForLabel(label) {
    const key = String(label ?? '').trim().toLowerCase();

    if (!key) {
        return null;
    }

    if (key.includes('gluten')) {
        return IconWheat;
    }

    if (key.includes('dairy')) {
        return IconCarton;
    }

    if (key.includes('nut')) {
        return IconNutOff;
    }

    if (key.includes('protein')) {
        return IconBicep;
    }

    if (key.includes('low carb') || key.includes('low carbs')) {
        return IconLowCarbMark;
    }

    if (key.includes('keto')) {
        return IconKeto;
    }

    if (key.includes('paleo')) {
        return IconPaleo;
    }

    if (key.includes('vegetarian')) {
        return IconSprout;
    }

    if (key.includes('vegan')) {
        return IconLeaf;
    }

    return null;
}

/**
 * Storybook / UI option list (kept separate from rendering logic).
 * These labels render with the same Pure pill styling as existing dietary tags.
 */
export const DIETARY_TAG_OPTIONS = [
    // Meal plan tags (new)
    'Balanced',
    'Ketogenic',
    'Hormone Feast',
    'Sickle Cell Anemia',
    // Existing dietary tags (common set)
    'Gluten-Free',
    'Vegan',
    'Vegetarian',
    'Nut-Free',
    'Dairy-Free',
    'Low Carbs',
    'High Protein',
];

export function DietaryTag({ label, className = '' }) {
    const Icon = iconForLabel(label);
    const raw = String(label ?? '');
    const key = raw.trim().toLowerCase();
    const displayLabel = key === 'keto' ? 'KETO' : raw.toUpperCase();

    return (
        <span
            className={`${BASE} shadow-[0_1px_1px_rgba(0,0,0,0.06)] ${className}`.trim()}
            style={{ backgroundColor: PURE_BG, color: PURE_FG }}
        >
            {Icon ? <Icon className="shrink-0" /> : null}
            <span>{displayLabel}</span>
        </span>
    );
}

export default function DietaryTags({ tags, className = '' }) {
    if (!tags?.length) {
        return null;
    }
    return (
        <div className={`flex flex-wrap gap-2 ${className}`.trim()} role="list" aria-label="Dietary tags">
            {tags.map((t) => (
                <span key={t} role="listitem">
                    <DietaryTag label={t} />
                </span>
            ))}
        </div>
    );
}

export { DietaryTag as MealPlanTag };

