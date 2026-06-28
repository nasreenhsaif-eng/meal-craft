/**
 * Daily reference intakes for % RDI on full-day micronutrient totals.
 * Labels match {@see MealLibraryController::nutritionalDataForDetailView} row labels.
 */
export const NUTRIENT_RDI_BY_LABEL: Record<string, number> = {
    'Fiber (g)': 28,
    'Sugar (g)': 50,
    'Vitamin A (mcg RAE)': 900,
    'Vitamin C (mg)': 90,
    'Vitamin D (mcg)': 15,
    'Vitamin E (mg)': 15,
    'Vitamin K2 (mcg)': 120,
    'Folate B9 (mcg)': 400,
    'Vitamin B12 (mcg)': 2.4,
    'Vitamin B6 (mg)': 1.7,
    'Calcium (mg)': 1000,
    'Iron (mg)': 18,
    'Magnesium (mg)': 420,
    'Potassium (mg)': 2600,
    'Zinc (mg)': 11,
    'Sodium (mg)': 2300,
};

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
 */
export function nutrientRdiPercent(label: string, total: number): number | null {
    const rdi = NUTRIENT_RDI_BY_LABEL[label];

    if (rdi == null || rdi <= 0 || !Number.isFinite(total)) {
        return null;
    }

    return (total / rdi) * 100;
}
