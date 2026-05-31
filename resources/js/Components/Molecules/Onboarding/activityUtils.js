export const ACTIVITY_LEVEL_OPTIONS = [
    {
        value: 'sedentary',
        label: 'Not Active',
        description: 'Little to no movement',
    },
    {
        value: 'moderate',
        label: 'Somewhat Active',
        description: 'Moderate movement (daily walks, light tasks)',
    },
    {
        value: 'active',
        label: 'Highly Active',
        description: 'Intense regular activity',
    },
    {
        value: 'very_active',
        label: 'Extremely Active',
        description: 'Vigorous daily activity',
    },
];

export const ACTIVITY_LEVEL_VALUES = ACTIVITY_LEVEL_OPTIONS.map((option) => option.value);

/** @returns {string} */
export function defaultActivityLevel() {
    return 'moderate';
}

/**
 * @param {string | null | undefined} value
 * @returns {string}
 */
export function resolveActivityLevel(value) {
    if (typeof value === 'string' && ACTIVITY_LEVEL_VALUES.includes(value)) {
        return value;
    }

    return defaultActivityLevel();
}

/**
 * @param {string} value
 * @returns {string}
 */
export function activityLabel(value) {
    return ACTIVITY_LEVEL_OPTIONS.find((option) => option.value === value)?.label ?? value;
}

/**
 * @param {string} value
 * @returns {string}
 */
export function activityDescription(value) {
    return ACTIVITY_LEVEL_OPTIONS.find((option) => option.value === value)?.description ?? '';
}
