/**
 * Live nutrition totals for the Create Meal form: resolves rows by `ingredient_id`
 * or exact library name (aligned with `calculateMealNutrition` per-100g math).
 * Use this when CSV-string matching fails (e.g. partial search text before selection).
 */

import type { IngredientProfile } from './calculateMealNutrition';
import { gramsFromIngredientAmountAndUnit } from './ingredientQuantityString';
import { finalizeRecipeNutrition } from './recipeMacroRounding';

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

/** Matches PHP {@see App\Support\PureCookingFatNutrition::OIL_DENSITY_G_PER_ML}. */
export const PURE_OIL_DENSITY_G_PER_ML = 0.92;

const PURE_OIL_FAT_PER_100G = 100;
const PURE_OIL_CALORIES_PER_100G = 884;

function microFromJson(m: Record<string, number> | undefined, key: string): number {
    const v = m?.[key];
    return typeof v === 'number' && !Number.isNaN(v) ? v : 0;
}

export function normalizeIngredientKey(name: string): string {
    return name.trim().toLowerCase().replace(/\s+/g, ' ');
}

/** Strip trailing “(Base)” / “(Base Recipe)” labels used in meal CSV rows. */
export function stripBaseRecipeSuffix(label: string): string {
    return label
        .trim()
        .replace(/\s*\(\s*base(?:\s+recipe)?\s*\)\s*$/iu, '')
        .replace(/\s*-\s*base(?:\s+recipe)?\s*$/iu, '')
        .trim();
}

/**
 * Pure cooking oils / butter / ghee (aligned with PHP PureCookingFatNutrition).
 */
export function isPureCookingFatName(name: string): boolean {
    const n = normalizeIngredientKey(name);
    if (!n || n.includes('(base)')) {
        return false;
    }

    for (const excluded of [
        'peanut butter',
        'almond butter',
        'cashew butter',
        'coconut butter',
        'butter bean',
        'butternut',
        'cocoa butter',
        'shea butter',
        'tahini',
    ]) {
        if (n.includes(excluded)) {
            return false;
        }
    }

    if (/\boil\b/.test(n)) {
        return true;
    }
    if (n.includes('ghee')) {
        return true;
    }
    return /\bbutter\b/.test(n);
}

export function isVegetableOilName(name: string): boolean {
    return isPureCookingFatName(name) && /\boil\b/.test(normalizeIngredientKey(name));
}

function densityForProfile(profile: IngredientProfile | undefined, name: string): number {
    const stored = typeof profile?.density === 'number' && profile.density > 0 ? profile.density : 0;
    if (!isPureCookingFatName(name)) {
        return stored > 0 ? stored : 1;
    }
    if (stored >= 0.85 && stored <= 0.98) {
        return stored;
    }
    return isVegetableOilName(name) ? PURE_OIL_DENSITY_G_PER_ML : 0.91;
}

function macroPer100ForProfile(profile: IngredientProfile, name: string): {
    calories: number;
    protein: number;
    carbs: number;
    fat: number;
} {
    if (isVegetableOilName(name)) {
        return {
            calories: PURE_OIL_CALORIES_PER_100G,
            protein: 0,
            carbs: 0,
            fat: PURE_OIL_FAT_PER_100G,
        };
    }

    if (isPureCookingFatName(name)) {
        const fat = (profile.fat ?? 0) >= 80 ? profile.fat : name.toLowerCase().includes('ghee') ? 99.5 : 81.1;
        const calories = (profile.calories ?? 0) >= 700 ? profile.calories : Math.round(fat * 9 * 10) / 10;
        return {
            calories,
            protein: Math.max(0, profile.protein ?? 0),
            carbs: Math.max(0, profile.carbs ?? 0),
            fat,
        };
    }

    return {
        calories: profile.calories ?? 0,
        protein: profile.protein ?? 0,
        carbs: profile.carbs ?? 0,
        fat: profile.fat ?? 0,
    };
}

function resolveIngredientProfile(
    label: string,
    ingredientId: number | null,
    byId: Map<number, IngredientProfile>,
    byName: Map<string, IngredientProfile>,
): IngredientProfile | undefined {
    if (ingredientId != null && Number.isFinite(ingredientId)) {
        const byIdMatch = byId.get(ingredientId);
        if (byIdMatch) {
            return byIdMatch;
        }
    }

    const trimmed = label.trim();
    if (!trimmed) {
        return undefined;
    }

    const keys = [normalizeIngredientKey(trimmed), normalizeIngredientKey(stripBaseRecipeSuffix(trimmed))].filter(
        (k, i, arr) => k !== '' && arr.indexOf(k) === i,
    );

    for (const key of keys) {
        const match = byName.get(key);
        if (match) {
            return match;
        }
    }

    for (const profile of byName.values()) {
        if (profile.is_prepared_base && keys.some((k) => normalizeIngredientKey(profile.name) === k)) {
            return profile;
        }
    }

    return undefined;
}

/**
 * @deprecated Prefer {@link gramsFromAmountUnitAndDensity} — kept for call sites that only have kg/ltr.
 */
export function gramsFromAmountAndUnit(amount: string, unit: string): number {
    return gramsFromAmountUnitAndDensity(amount, unit, 1);
}

export function gramsFromAmountUnitAndDensity(amount: string, unit: string, densityGramsPerMl: number): number {
    const n = Number(amount);
    if (!Number.isFinite(n) || n <= 0) {
        return 0;
    }
    return gramsFromIngredientAmountAndUnit(n, unit, densityGramsPerMl);
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
        vitamin_k2: 0,
    };

    let resolvedLineCount = 0;
    let pureFatFloor = 0;
    let pureFatCalorieFloor = 0;

    for (const r of rows) {
        const label = (r.selectedName || r.nameQuery || '').trim();
        const ing = resolveIngredientProfile(label, r.ingredientId, byId, byName);
        if (!ing) {
            continue;
        }

        const density = densityForProfile(ing, label || ing.name);
        const grams = gramsFromAmountUnitAndDensity(r.amount, r.unit, density);
        if (grams <= 0) {
            continue;
        }

        resolvedLineCount += 1;
        const factor = grams / 100;
        const micros = ing.micronutrients ?? {};
        const macros = macroPer100ForProfile(ing, label || ing.name);

        nutrition.calories += macros.calories * factor;
        nutrition.protein += macros.protein * factor;
        nutrition.carbs += macros.carbs * factor;
        nutrition.fat += macros.fat * factor;
        nutrition.b6 += (ing.b6 ?? 0) * factor;
        nutrition.b9_folate += (ing.b9_folate ?? 0) * factor;
        nutrition.b12 += (ing.b12 ?? 0) * factor;
        nutrition.iron += (ing.iron ?? 0) * factor;
        nutrition.magnesium += (ing.magnesium ?? 0) * factor;

        for (const k of MICRO_KEYS) {
            nutrition[k] += microFromJson(micros, k) * factor;
        }

        if (isPureCookingFatName(label || ing.name)) {
            pureFatFloor += macros.fat * factor;
            pureFatCalorieFloor += macros.calories * factor;
        }
    }

    nutrition.fat = Math.max(nutrition.fat, pureFatFloor);
    nutrition.calories = Math.max(nutrition.calories, pureFatCalorieFloor);

    return { nutrition: finalizeRecipeNutrition(nutrition), resolvedLineCount };
}
