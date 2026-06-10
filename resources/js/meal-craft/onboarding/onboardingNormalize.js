/** @typedef {import('./onboardingConstants.js').OnboardingActivityLevel} OnboardingActivityLevel */
/** @typedef {import('./onboardingConstants.js').OnboardingDietProtocol} OnboardingDietProtocol */

/**
 * @param {string | null | undefined} value
 * @returns {OnboardingActivityLevel}
 */
export function normalizeActivityLevel(value) {
    const map = {
        sedentary: 'sedentary',
        light: 'lightly_active',
        moderate: 'lightly_active',
        active: 'moderately_active',
        very_active: 'very_active',
        lightly_active: 'lightly_active',
        moderately_active: 'moderately_active',
    };

    if (typeof value === 'string' && map[value]) {
        return map[value];
    }

    return 'lightly_active';
}

/**
 * Maps store activity level to persisted CustomerActivityLevel enum values.
 *
 * @param {OnboardingActivityLevel} value
 * @returns {'sedentary' | 'light' | 'moderate' | 'active' | 'very_active'}
 */
export function activityLevelToServer(value) {
    const normalized = normalizeActivityLevel(value);

    return normalized;
}

/**
 * @param {string | null | undefined} value
 * @returns {OnboardingDietProtocol}
 */
export function normalizeDietProtocol(value) {
    const map = {
        balanced: 'balanced',
        ketogenic: 'ketobiotic',
        keto: 'ketobiotic',
        ketobiotic: 'ketobiotic',
        cycle_sync: 'cycle_sync',
        thyroid: 'thyroid',
        sickle_cell: 'sickle_cell_warrior',
        sickle_cell_warrior: 'sickle_cell_warrior',
    };

    if (typeof value === 'string' && map[value]) {
        return map[value];
    }

    return 'balanced';
}

/**
 * @param {OnboardingDietProtocol} value
 * @returns {string}
 */
export function dietProtocolToServer(value) {
    const normalized = normalizeDietProtocol(value);

    const legacy = {
        sickle_cell_warrior: 'sickle_cell',
    };

    return legacy[normalized] ?? normalized;
}
