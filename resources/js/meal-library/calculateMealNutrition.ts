/**
 * Client-side mirror of meal CSV auto-calculation (per-100g ingredient library).
 * Server-side import uses App\Services\MealCsvLibraryImportService — keep formulas aligned.
 */

import { gramsFromIngredientAmountAndUnit, parseIngredientQuantityString } from './ingredientQuantityString';

export type IngredientProfile = {
    /** Present when loaded from the verified ingredient library (meal library create form). */
    id?: number;
    /** Prepared base ingredient / base recipe row in the ingredient library. */
    is_prepared_base?: boolean;
    /** Canonical slugs aligned with `IngredientAllergenCatalog` (PHP). */
    common_allergens?: readonly string[];
    name: string;
    calories: number;
    protein: number;
    carbs: number;
    fat: number;
    b6?: number;
    b9_folate?: number;
    b12?: number;
    iron?: number;
    magnesium?: number;
    micronutrients?: Record<string, number>;
    /** g/ml for volume → mass (meal library ingredient payload). */
    density?: number;
};

/** Allowed Category values for meal-library CSV (matches PHP MealCsvLibraryImportService). */
export const MEAL_LIBRARY_CSV_CATEGORY_VALUES = [
    'Breakfast',
    'Meal',
    'Main Salad',
    'Base Recipe',
    'Side Salad',
    'Soup',
    'Dessert',
] as const;

export type MealLibraryCsvCategory = (typeof MEAL_LIBRARY_CSV_CATEGORY_VALUES)[number];

export type CsvMealRow = {
    meal_name: string;
    /** When set (including empty string), validated against allowed CSV categories. */
    category?: string;
    ingredient_quantities: string;
    instructions?: string;
    highlight?: string;
};

export type CalculateMealNutritionResult = {
    ok: boolean;
    pendingIngredients: string[];
    nutrition: Record<string, number>;
    healthScore: number;
    /** Resolved category when valid; null if not provided or invalid. */
    category: MealLibraryCsvCategory | null;
    /** Calorie band warnings for the resolved category (non-blocking on import). */
    categoryWarnings: string[];
    /** Set when `category` was supplied but empty or not in the allowed list. */
    categoryError: string | null;
};

const MICRO_KEYS = [
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

function normalizeName(name: string): string {
    return name.trim().toLowerCase().replace(/\s+/g, ' ');
}

function normalizeCategoryLabel(raw: string): string {
    return raw.trim().toLowerCase().replace(/\s+/g, ' ');
}

export function resolveMealLibraryCategory(raw: string | undefined): MealLibraryCsvCategory | null {
    if (raw === undefined) {
        return null;
    }

    const norm = normalizeCategoryLabel(raw);
    if (norm === '') {
        return null;
    }

    for (const v of MEAL_LIBRARY_CSV_CATEGORY_VALUES) {
        if (normalizeCategoryLabel(v) === norm) {
            return v;
        }
    }

    return null;
}

export function calorieWarningsForCategory(category: MealLibraryCsvCategory, totalCalories: number): string[] {
    const cal = Math.round(totalCalories * 10) / 10;
    const warnings: string[] = [];

    if (category === 'Breakfast' && totalCalories > 250) {
        warnings.push(`Breakfast meals are typically ≤250 kcal (this meal is ${cal} kcal).`);
    }

    if (category === 'Meal' && (totalCalories < 300 || totalCalories > 400)) {
        warnings.push(`“Meal” category targets are typically 300–400 kcal (this meal is ${cal} kcal).`);
    }

    if (category === 'Base Recipe' && (totalCalories < 300 || totalCalories > 400)) {
        warnings.push(
            `“Base Recipe” batches are often planned in the same 300–400 kcal band as mains (this meal is ${cal} kcal).`,
        );
    }

    if ((category === 'Side Salad' || category === 'Soup' || category === 'Dessert') && totalCalories > 175) {
        warnings.push(`${category} items are typically ≤175 kcal (this meal is ${cal} kcal).`);
    }

    return warnings;
}

function microFromJson(m: Record<string, number> | undefined, key: string): number {
    const v = m?.[key];
    return typeof v === 'number' && !Number.isNaN(v) ? v : 0;
}

const emptyFailure = (
    partial: Partial<CalculateMealNutritionResult> & Pick<CalculateMealNutritionResult, 'ok' | 'pendingIngredients'>,
): CalculateMealNutritionResult => ({
    ok: partial.ok,
    pendingIngredients: partial.pendingIngredients,
    nutrition: partial.nutrition ?? {},
    healthScore: partial.healthScore ?? 0,
    category: partial.category ?? null,
    categoryWarnings: partial.categoryWarnings ?? [],
    categoryError: partial.categoryError ?? null,
});

/**
 * @param csvRow - Parsed CSV columns
 * @param ingredientDatabase - Ingredient rows (same shape as your library / API payload)
 */
export function calculateMealNutrition(
    csvRow: CsvMealRow,
    ingredientDatabase: IngredientProfile[],
): CalculateMealNutritionResult {
    let categoryResolved: MealLibraryCsvCategory | null = null;
    let categoryError: string | null = null;

    if (Object.prototype.hasOwnProperty.call(csvRow, 'category')) {
        categoryResolved = resolveMealLibraryCategory(csvRow.category);
        if (categoryResolved === null) {
            categoryError = 'Invalid or Missing Category.';
        }
    }

    if (categoryError !== null) {
        return emptyFailure({
            ok: false,
            pendingIngredients: [],
            categoryError,
        });
    }

    const segments = parseIngredientQuantityString(csvRow.ingredient_quantities ?? '');
    if (segments.length === 0) {
        return emptyFailure({
            ok: false,
            pendingIngredients: [],
            category: categoryResolved,
        });
    }

    const byNorm = new Map<string, IngredientProfile>();
    for (const ing of ingredientDatabase) {
        byNorm.set(normalizeName(ing.name), ing);
    }

    const pendingNormSeen = new Set<string>();
    const pending: string[] = [];
    const gramsByNorm = new Map<string, number>();

    for (const seg of segments) {
        const key = normalizeName(seg.name);
        const row = byNorm.get(key);
        if (!row) {
            if (!pendingNormSeen.has(key)) {
                pendingNormSeen.add(key);
                pending.push(seg.name.trim());
            }
            continue;
        }
        const density = typeof row.density === 'number' && row.density > 0 ? row.density : 1;
        const grams = gramsFromIngredientAmountAndUnit(seg.amount, seg.unit, density);
        gramsByNorm.set(key, (gramsByNorm.get(key) ?? 0) + grams);
    }

    if (pending.length > 0) {
        return emptyFailure({
            ok: false,
            pendingIngredients: pending,
            category: categoryResolved,
        });
    }

    const nutrition: Record<string, number> = {
        calories: 0,
        protein: 0,
        carbs: 0,
        fat: 0,
        b6: 0,
        b9_folate: 0,
        b12: 0,
        iron: 0,
        magnesium: 0,
        fiber: 0,
        sugar: 0,
        calcium: 0,
        potassium: 0,
        sodium: 0,
        zinc: 0,
        vitamin_c: 0,
        vitamin_a: 0,
        vitamin_e: 0,
        vitamin_d: 0,
        vitamin_k2: 0,
    };

    for (const [norm, grams] of gramsByNorm) {
        const ing = byNorm.get(norm);
        if (!ing) continue;
        const factor = grams / 100;
        const micros = ing.micronutrients ?? {};

        nutrition.calories += ing.calories * factor;
        nutrition.protein += ing.protein * factor;
        nutrition.carbs += ing.carbs * factor;
        nutrition.fat += ing.fat * factor;
        nutrition.b6 += (ing.b6 ?? 0) * factor;
        nutrition.b9_folate += (ing.b9_folate ?? 0) * factor;
        nutrition.b12 += (ing.b12 ?? 0) * factor;
        nutrition.iron += (ing.iron ?? 0) * factor;
        nutrition.magnesium += (ing.magnesium ?? 0) * factor;

        for (const k of MICRO_KEYS) {
            nutrition[k] += microFromJson(micros, k) * factor;
        }
    }

    for (const k of Object.keys(nutrition)) {
        nutrition[k] = Math.round(nutrition[k] * 100) / 100;
    }

    const healthScore = computeMealHealthScore(nutrition);

    const categoryWarnings =
        categoryResolved !== null ? calorieWarningsForCategory(categoryResolved, nutrition.calories ?? 0) : [];

    return {
        ok: true,
        pendingIngredients: [],
        nutrition,
        healthScore,
        category: categoryResolved,
        categoryWarnings,
        categoryError: null,
    };
}

export function computeMealHealthScore(nutrition: Record<string, number>): number {
    const fiber = nutrition.fiber ?? 0;
    const vitC = nutrition.vitamin_c ?? 0;
    const folate = nutrition.b9_folate ?? 0;
    const mag = nutrition.magnesium ?? 0;
    const iron = nutrition.iron ?? 0;
    const zinc = nutrition.zinc ?? 0;
    const vitE = nutrition.vitamin_e ?? 0;
    const potassium = nutrition.potassium ?? 0;

    const raw =
        fiber * 1.2 +
        vitC * 0.03 +
        folate * 0.008 +
        mag * 0.012 +
        iron * 0.15 +
        zinc * 0.25 +
        vitE * 0.4 +
        potassium * 0.002;

    return Math.round(Math.min(100, Math.max(0, raw)) * 100) / 100;
}
