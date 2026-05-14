/**
 * Planning targets in the Meal Library modal are always **per serving**.
 * Ingredient-backed nutrition for bulk recipes is rolled up as **batch totals**; divide by
 * `servingsCount` before comparing to targets or running per-serving calorie-band warnings.
 */

export type ServingsParseFn = (raw: string) => number | null;

/**
 * @param n - Nutrition object with numeric values
 * @param factor - Multiply every finite numeric value by this factor
 */
export function scaleNutritionRecord(n: Record<string, unknown> | null | undefined, factor: number): Record<string, number> {
    if (!n || !Number.isFinite(factor) || factor <= 0) {
        return {};
    }
    const out: Record<string, number> = {};
    for (const [k, v] of Object.entries(n)) {
        if (typeof v === 'number' && Number.isFinite(v)) {
            out[k] = Math.round(v * factor * 10000) / 10000;
        }
    }
    return out;
}

export type PerServingActualOptions = {
    isBulkRecipe: boolean;
    bulkServingsCountRaw: string;
    batchNutrition: Record<string, unknown> | null | undefined;
    parseServings: ServingsParseFn;
};

/**
 * Returns **per-serving** system nutrition for comparing to per-serving planning targets.
 * - Non-bulk: same as batch (one serving = whole meal).
 * - Bulk + valid servings: `batchNutrition / servings`.
 * - Bulk + invalid/missing servings: `null` (do not compare batch totals to per-serving targets).
 */
export function resolvePerServingActualForTargets({
    isBulkRecipe,
    bulkServingsCountRaw,
    batchNutrition,
    parseServings,
}: PerServingActualOptions): Record<string, number> | null {
    if (!batchNutrition || typeof batchNutrition !== 'object') {
        return null;
    }
    if (!isBulkRecipe) {
        const scaled = scaleNutritionRecord(batchNutrition, 1);
        return Object.keys(scaled).length > 0 ? scaled : null;
    }
    const svc = parseServings(bulkServingsCountRaw);
    if (svc == null || !Number.isFinite(svc) || svc <= 0) {
        return null;
    }
    const scaled = scaleNutritionRecord(batchNutrition, 1 / svc);
    return Object.keys(scaled).length > 0 ? scaled : null;
}

/**
 * Tailwind text classes for Δ = target − calculated (closer to zero is better).
 * Uses relative error vs target when target > 0.
 */
export function planningVarianceDeltaClass(target: number, calculated: number): string {
    if (!Number.isFinite(target) || !Number.isFinite(calculated) || target <= 0) {
        return 'text-[#6B7280]';
    }
    const rel = Math.abs(target - calculated) / target;
    if (rel <= 0.05) {
        return 'text-green-700';
    }
    if (rel <= 0.15) {
        return 'text-amber-700';
    }
    return 'text-red-700';
}
