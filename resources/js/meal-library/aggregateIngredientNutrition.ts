/**
 * Live nutrition totals for the Create Meal form: resolves rows by `ingredient_id`
 * or exact library name (aligned with `calculateMealNutrition` per-100g math).
 * Use this when CSV-string matching fails (e.g. partial search text before selection).
 */

import type { IngredientProfile } from './calculateMealNutrition';

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
    'vitamin_k',
] as const;

function microFromJson(m: Record<string, number> | undefined, key: string): number {
    const v = m?.[key];
    return typeof v === 'number' && !Number.isNaN(v) ? v : 0;
}

export function normalizeIngredientKey(name: string): string {
    return name.trim().toLowerCase().replace(/\s+/g, ' ');
}

export function gramsFromAmountAndUnit(amount: string, unit: string): number {
    const n = Number(amount);
    if (!Number.isFinite(n) || n <= 0) {
        return 0;
    }
    if (unit === 'kg') {
        return n * 1000;
    }
    if (unit === 'ltr') {
        return n * 1000;
    }
    return n;
}

export type IngredientRowForNutrition = {
    ingredientId: number | null;
    selectedName: string;
    nameQuery: string;
    amount: string;
    unit: string;
};

export function aggregateNutritionFromIngredientRows(
    rows: readonly IngredientRowForNutrition[],
    profiles: readonly IngredientProfile[],
): { nutrition: Record<string, number>; resolvedLineCount: number } {
    const byId = new Map<number, IngredientProfile>();
    for (const p of profiles) {
        if (typeof p.id === 'number' && Number.isFinite(p.id)) {
            byId.set(p.id, p);
        }
    }
    const byName = new Map<string, IngredientProfile>();
    for (const p of profiles) {
        const k = normalizeIngredientKey(p.name);
        if (!byName.has(k)) {
            byName.set(k, p);
        }
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
        vitamin_k: 0,
    };

    let resolvedLineCount = 0;

    for (const r of rows) {
        const grams = gramsFromAmountAndUnit(r.amount, r.unit);
        if (grams <= 0) {
            continue;
        }

        let ing: IngredientProfile | undefined;
        if (r.ingredientId != null && Number.isFinite(r.ingredientId)) {
            ing = byId.get(r.ingredientId);
        }
        if (!ing) {
            const label = (r.selectedName || r.nameQuery || '').trim();
            if (!label) {
                continue;
            }
            ing = byName.get(normalizeIngredientKey(label));
        }
        if (!ing) {
            continue;
        }

        resolvedLineCount += 1;
        const factor = grams / 100;
        const micros = ing.micronutrients ?? {};

        nutrition.calories += (ing.calories ?? 0) * factor;
        nutrition.protein += (ing.protein ?? 0) * factor;
        nutrition.carbs += (ing.carbs ?? 0) * factor;
        nutrition.fat += (ing.fat ?? 0) * factor;
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
        nutrition[k] = Math.round((nutrition[k] ?? 0) * 100) / 100;
    }

    return { nutrition, resolvedLineCount };
}
