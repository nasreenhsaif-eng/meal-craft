/**
 * Raw vs cooked chicken breast — mirrors {@see App\Support\ChickenBreastYield}.
 *
 * Library convention:
 * - "Chicken Breast" amounts in meals = raw prep weight (g).
 * - Chicken (Base) recipe components = raw batch inputs.
 * - Chicken (Base) amounts in meals = cooked portion weight (g).
 */

export const CHICKEN_BREAST_RAW_NAME = 'Chicken Breast';

/** Per 100 g raw boneless skinless breast (USDA FDC 171077). */
export const CHICKEN_RAW_PROTEIN_PER_100G = 23;

export const CHICKEN_RAW_CALORIES_PER_100G = 120;

/** Moisture loss when grilled or baked to 74 °C. */
export const CHICKEN_RAW_TO_COOKED_RATIO = 0.75;

export function cookedGramsFromRawChicken(rawGrams: number): number {
    return Math.round(rawGrams * CHICKEN_RAW_TO_COOKED_RATIO * 10) / 10;
}

export function rawGramsFromCookedChicken(cookedGrams: number): number {
    if (CHICKEN_RAW_TO_COOKED_RATIO <= 0) {
        return 0;
    }

    return Math.round((cookedGrams / CHICKEN_RAW_TO_COOKED_RATIO) * 10) / 10;
}

export function rawGramsForChickenProtein(proteinGrams: number): number {
    if (CHICKEN_RAW_PROTEIN_PER_100G <= 0) {
        return 0;
    }

    return Math.round((proteinGrams / CHICKEN_RAW_PROTEIN_PER_100G) * 100 * 10) / 10;
}

export function cookedChickenProteinPer100g(): number {
    return Math.round((CHICKEN_RAW_PROTEIN_PER_100G / CHICKEN_RAW_TO_COOKED_RATIO) * 100) / 100;
}

/**
 * @returns {string | null} Helper line for meal/base editors when chicken breast is in the row.
 */
export function chickenBreastYieldHint(rawGrams: number): string | null {
    if (!Number.isFinite(rawGrams) || rawGrams <= 0) {
        return null;
    }

    const protein = Math.round((rawGrams / 100) * CHICKEN_RAW_PROTEIN_PER_100G * 10) / 10;
    const cooked = cookedGramsFromRawChicken(rawGrams);

    return `${rawGrams} g raw ≈ ${cooked} g cooked plain breast · ~${protein} g protein (water loss only; protein is conserved).`;
}
