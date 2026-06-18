import type { MealNutritionalData } from '../Components/Molecules/MealDetailView/MealDetailView.tsx';

export function formatTrimmedDecimal(value: number, decimals: number): string {
    if (!Number.isFinite(value)) {
        return '0';
    }

    const formatted = value.toFixed(decimals);
    const trimmed = formatted.replace(/\.?0+$/, '');

    return trimmed === '' ? '0' : trimmed;
}

/**
 * Scale batch nutrition totals to per-100 g using finished cooked weight.
 * Mirrors `BaseIngredientService` / `RecipeNutritionCalculator` yield logic on the server.
 */
export function scaleNutritionToPer100g(
    batchNutrition: Record<string, number>,
    finishedWeightGrams: number,
): Record<string, number> | null {
    if (!Number.isFinite(finishedWeightGrams) || finishedWeightGrams <= 0) {
        return null;
    }

    const factor = 100 / finishedWeightGrams;
    const scaled: Record<string, number> = {};

    for (const [key, value] of Object.entries(batchNutrition)) {
        const n = typeof value === 'number' && Number.isFinite(value) ? value : 0;
        scaled[key] = Math.round(n * factor * 100) / 100;
    }

    return scaled;
}

/** Mirrors `IngredientLibraryController::nutritionalDataPer100gSidebar`. */
export function buildNutritionalDataPer100gSidebar(nutrition: Record<string, number>): MealNutritionalData {
    const calories = nutrition.calories ?? 0;
    const protein = nutrition.protein ?? 0;
    const carbs = nutrition.carbs ?? 0;
    const fat = nutrition.fat ?? 0;
    const fiber = nutrition.fiber ?? 0;
    const sugar = nutrition.sugar ?? 0;
    const netCarbs = Math.max(0, carbs - fiber);

    return {
        valueColumnLabel: 'Per 100 g',
        sections: [
            {
                title: 'Macros',
                rows: [
                    { label: 'Total calories', value: String(Math.round(calories)) },
                    { label: 'Protein (g)', value: formatTrimmedDecimal(protein, 1), valueClass: 'text-[#916A00]' },
                    { label: 'Fats (g)', value: formatTrimmedDecimal(fat, 1), valueClass: 'text-[#2F4C9B]' },
                    { label: 'Net carbs (g)', value: formatTrimmedDecimal(netCarbs, 1), valueClass: 'text-[#8F55A8]' },
                    { label: 'Fiber (g)', value: formatTrimmedDecimal(fiber, 1) },
                    { label: 'Sugar (g)', value: formatTrimmedDecimal(sugar, 1) },
                ],
            },
            {
                title: 'Vitamins',
                rows: [
                    { label: 'Vitamin A (mcg RAE)', value: formatTrimmedDecimal(nutrition.vitamin_a ?? 0, 1) },
                    { label: 'Vitamin C (mg)', value: formatTrimmedDecimal(nutrition.vitamin_c ?? 0, 1) },
                    { label: 'Vitamin D (mcg)', value: formatTrimmedDecimal(nutrition.vitamin_d ?? 0, 1) },
                    { label: 'Vitamin E (mg)', value: formatTrimmedDecimal(nutrition.vitamin_e ?? 0, 1) },
                    { label: 'Vitamin K (mcg)', value: formatTrimmedDecimal(nutrition.vitamin_k ?? 0, 1) },
                    { label: 'Folate B9 (mcg)', value: formatTrimmedDecimal(nutrition.b9_folate ?? 0, 1) },
                    { label: 'Vitamin B12 (mcg)', value: formatTrimmedDecimal(nutrition.b12 ?? 0, 1) },
                    { label: 'Vitamin B6 (mg)', value: formatTrimmedDecimal(nutrition.b6 ?? 0, 1) },
                ],
            },
            {
                title: 'Minerals',
                rows: [
                    { label: 'Calcium (mg)', value: formatTrimmedDecimal(nutrition.calcium ?? 0, 1) },
                    { label: 'Iron (mg)', value: formatTrimmedDecimal(nutrition.iron ?? 0, 1) },
                    { label: 'Magnesium (mg)', value: formatTrimmedDecimal(nutrition.magnesium ?? 0, 1) },
                    { label: 'Potassium (mg)', value: formatTrimmedDecimal(nutrition.potassium ?? 0, 1) },
                    { label: 'Zinc (mg)', value: formatTrimmedDecimal(nutrition.zinc ?? 0, 1) },
                    { label: 'Sodium (mg)', value: formatTrimmedDecimal(nutrition.sodium ?? 0, 1) },
                ],
            },
        ],
    };
}
