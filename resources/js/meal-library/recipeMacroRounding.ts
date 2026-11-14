/**
 * Single-stage final rounding + Atwater (4-9-4) calorie audit.
 * Mirrors PHP {@see App\Support\RecipeMacroRounding}.
 */

export const PROTEIN_KCAL_PER_G = 4;
export const CARB_KCAL_PER_G = 4;
export const FAT_KCAL_PER_G = 9;
export const CALORIE_DRIFT_TOLERANCE_KCAL = 5;

const MACRO_KEYS_ONE_DECIMAL = ['protein', 'carbs', 'fat'] as const;

const MICRO_KEYS_FOUR_DECIMAL = [
    'b6',
    'b9_folate',
    'b12',
    'iron',
    'magnesium',
    'fiber',
    'sugar',
    'calcium',
    'potassium',
    'sodium',
    'zinc',
    'vitamin_c',
    'vitamin_a',
    'vitamin_e',
    'vitamin_d',
    'vitamin_k2',
] as const;

export function atwaterCalories(proteinG: number, carbsG: number, fatG: number): number {
    return proteinG * PROTEIN_KCAL_PER_G + carbsG * CARB_KCAL_PER_G + fatG * FAT_KCAL_PER_G;
}

export function finalizeRecipeNutrition(nutrition: Record<string, number>): Record<string, number> {
    const out: Record<string, number> = { ...nutrition };

    for (const key of MACRO_KEYS_ONE_DECIMAL) {
        out[key] = Math.round((out[key] ?? 0) * 10) / 10;
    }

    for (const key of MICRO_KEYS_FOUR_DECIMAL) {
        if (out[key] == null) {
            continue;
        }
        out[key] = Math.round((out[key] ?? 0) * 10000) / 10000;
    }

    out.calories = Math.round(out.calories ?? 0);

    return out;
}

/** True when |calories − Atwater(P,C,F)| exceeds ±5 kcal (recalculation sweep flag). */
export function needsCalorieRecalculationSweep(nutrition: Record<string, number>): boolean {
    const atwater = atwaterCalories(nutrition.protein ?? 0, nutrition.carbs ?? 0, nutrition.fat ?? 0);
    return Math.abs((nutrition.calories ?? 0) - atwater) > CALORIE_DRIFT_TOLERANCE_KCAL;
}
