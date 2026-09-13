/**
 * Daily reference intakes for % RDI on full-day micronutrient totals.
 * Labels match {@see MealLibraryController::nutritionalDataForDetailView} row labels.
 *
 * Sex- and activity-aware targets live in {@see calculateMicronutrientTargets}.
 * When no profile is provided, {@see NUTRIENT_RDI_BY_LABEL} uses female + not-active
 * so library / meal-plan-library views keep the 18 mg iron baseline.
 */

/** @typedef {'male' | 'female'} MicronutrientSex */

/**
 * @typedef {'sedentary' | 'lightly_active' | 'moderately_active' | 'very_active' | 'light' | 'moderate' | 'active'} MicronutrientActivityLevel
 */

/**
 * @param {string | null | undefined} activityLevel
 * @returns {'not_active' | 'somewhat_active' | 'highly_active' | 'extremely_active'}
 */
function activityBand(activityLevel) {
    const value = typeof activityLevel === 'string' ? activityLevel : '';

    if (value === 'sedentary') {
        return 'not_active';
    }

    if (value === 'moderately_active' || value === 'active') {
        return 'highly_active';
    }

    if (value === 'very_active') {
        return 'extremely_active';
    }

    if (value === 'lightly_active' || value === 'light' || value === 'moderate') {
        return 'somewhat_active';
    }

    return 'somewhat_active';
}

/**
 * @param {number} value
 */
function roundTarget(value) {
    return Math.round(value * 10) / 10;
}

/**
 * @param {number} base
 * @param {number} factor
 */
function scale(base, factor) {
    return roundTarget(base * factor);
}

/**
 * Adult (19–50) daily reference intakes by sex and activity.
 *
 * @param {{
 *   sex?: string | null;
 *   activityLevel?: string | null;
 *   dailyCalories?: number | null;
 * }} [options]
 * @returns {Record<string, number>}
 */
export function calculateMicronutrientTargets(options = {}) {
    const male = options.sex === 'male';
    const band = activityBand(options.activityLevel);
    const dailyCalories = Number(options.dailyCalories);

    const fiberBaseline = male ? 38 : 25;
    let fiber = fiberBaseline;
    if (Number.isFinite(dailyCalories) && dailyCalories > 0) {
        fiber = Math.max(fiberBaseline, roundTarget((dailyCalories / 1000) * 14));
    }

    const vitaminC = scale(male ? 90 : 75, band === 'highly_active' ? 1.2 : band === 'extremely_active' ? 1.4 : 1);
    const vitaminB6 = scale(1.3, band === 'highly_active' ? 1.15 : band === 'extremely_active' ? 1.3 : 1);
    const iron = scale(
        male ? 8 : 18,
        band === 'somewhat_active' ? 1.1 : band === 'highly_active' ? 1.3 : band === 'extremely_active' ? 1.5 : 1,
    );
    const magnesium = scale(male ? 420 : 320, band === 'highly_active' ? 1.1 : band === 'extremely_active' ? 1.2 : 1);
    const potassium = scale(male ? 3400 : 2600, band === 'highly_active' ? 1.15 : band === 'extremely_active' ? 1.25 : 1);
    const sodium = 2300 + (band === 'highly_active' ? 500 : band === 'extremely_active' ? 1000 : 0);

    return {
        'Fiber (g)': fiber,
        'Sugar (g)': male ? 36 : 25,
        'Vitamin A (mcg RAE)': male ? 900 : 700,
        'Vitamin C (mg)': vitaminC,
        'Vitamin D (mcg)': 15,
        'Vitamin E (mg)': 15,
        'Vitamin K2 (mcg)': male ? 120 : 90,
        'Folate B9 (mcg)': 400,
        'Vitamin B12 (mcg)': 2.4,
        'Vitamin B6 (mg)': vitaminB6,
        'Calcium (mg)': 1000,
        'Iron (mg)': iron,
        'Magnesium (mg)': magnesium,
        'Potassium (mg)': potassium,
        'Zinc (mg)': male ? 11 : 8,
        'Sodium (mg)': sodium,
    };
}

export const NUTRIENT_RDI_BY_LABEL = calculateMicronutrientTargets({
    sex: 'female',
    activityLevel: 'sedentary',
});

export const ENFORCED_MICRONUTRIENT_TIERS = [1500, 1800, 2000] as const;

export const INFORMATIONAL_MICRONUTRIENT_TIERS = [1000, 1200] as const;

export const FLOOR_RDI_TARGET_PERCENT = 98;

export const BEST_EFFORT_NUTRIENT_LABELS = new Set(['Vitamin D (mcg)']);

/**
 * @param {number} planTier
 */
export function isMicronutrientTierEnforced(planTier: number): boolean {
    return ENFORCED_MICRONUTRIENT_TIERS.includes(/** @type {typeof ENFORCED_MICRONUTRIENT_TIERS[number]} */ (Math.round(planTier)));
}

/**
 * @param {string} label
 * @param {number} total
 * @param {Record<string, number>} [targets]
 */
export function nutrientRdiPercent(label: string, total: number, targets: Record<string, number> = NUTRIENT_RDI_BY_LABEL): number | null {
    const rdi = targets[label];

    if (rdi == null || rdi <= 0 || !Number.isFinite(total)) {
        return null;
    }

    return (total / rdi) * 100;
}
