import { formatTrimmedDecimal } from './buildNutritionalDataPer100g.ts';

/**
 * Build MealDetailView nutritionalData sections from a flat nutrition map
 * (matches server-side MealLibraryController::nutritionalDataForDetailView).
 *
 * @param {Record<string, number | string | null | undefined> | null | undefined} nutrition
 */
export function buildNutritionalDataFromNutrition(nutrition) {
    const n = nutrition ?? {};
    const calories = Number(n.calories ?? 0);
    const protein = Number(n.protein ?? 0);
    const carbs = Number(n.carbs ?? 0);
    const fat = Number(n.fat ?? 0);
    const fiber = Number(n.fiber ?? 0);
    const sugar = Number(n.sugar ?? 0);
    const netCarbs = Math.max(0, carbs - fiber);

    return {
        valueColumnLabel: 'Per serving',
        sections: [
            {
                title: 'Macros',
                rows: [
                    { label: 'Total calories', value: String(Math.round(calories)) },
                    { label: 'Protein (g)', value: formatTrimmedDecimal(protein, 1), valueClass: 'text-[#916A00]' },
                    { label: 'Fats (g)', value: formatTrimmedDecimal(fat, 1), valueClass: 'text-[#2F4C9B]' },
                    { label: 'Carbs (g)', value: formatTrimmedDecimal(carbs, 1), valueClass: 'text-[#8F55A8]' },
                    { label: 'Net carbs (g)', value: formatTrimmedDecimal(netCarbs, 1) },
                    { label: 'Fiber (g)', value: formatTrimmedDecimal(fiber, 1) },
                    { label: 'Sugar (g)', value: formatTrimmedDecimal(sugar, 1) },
                ],
            },
            {
                title: 'Vitamins',
                rows: [
                    { label: 'Vitamin A (mcg RAE)', value: formatTrimmedDecimal(Number(n.vitamin_a ?? 0), 1) },
                    { label: 'Vitamin C (mg)', value: formatTrimmedDecimal(Number(n.vitamin_c ?? 0), 1) },
                    { label: 'Vitamin D (mcg)', value: formatTrimmedDecimal(Number(n.vitamin_d ?? 0), 1) },
                    { label: 'Vitamin E (mg)', value: formatTrimmedDecimal(Number(n.vitamin_e ?? 0), 1) },
                    { label: 'Vitamin K2 (mcg)', value: formatTrimmedDecimal(Number(n.vitamin_k2 ?? 0), 1) },
                    { label: 'Folate B9 (mcg)', value: formatTrimmedDecimal(Number(n.b9_folate ?? 0), 1) },
                    { label: 'Vitamin B12 (mcg)', value: formatTrimmedDecimal(Number(n.b12 ?? 0), 1) },
                    { label: 'Vitamin B6 (mg)', value: formatTrimmedDecimal(Number(n.b6 ?? 0), 1) },
                ],
            },
            {
                title: 'Minerals',
                rows: [
                    { label: 'Calcium (mg)', value: formatTrimmedDecimal(Number(n.calcium ?? 0), 1) },
                    { label: 'Iron (mg)', value: formatTrimmedDecimal(Number(n.iron ?? 0), 1) },
                    { label: 'Magnesium (mg)', value: formatTrimmedDecimal(Number(n.magnesium ?? 0), 1) },
                    { label: 'Potassium (mg)', value: formatTrimmedDecimal(Number(n.potassium ?? 0), 1) },
                    { label: 'Zinc (mg)', value: formatTrimmedDecimal(Number(n.zinc ?? 0), 1) },
                    { label: 'Sodium (mg)', value: formatTrimmedDecimal(Number(n.sodium ?? 0), 1) },
                ],
            },
        ],
    };
}
