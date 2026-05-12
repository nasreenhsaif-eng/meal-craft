/**
 * Client-side mirrors of `App\Support\IngredientAllergenCatalog` and
 * `App\Services\RecipeNutritionCalculator::sickleCellProgramMealHighlight` — keep thresholds aligned.
 */

export const ALLERGEN_SLUG_TO_LABEL: Record<string, string> = {
    peanuts: 'Contains: Peanuts',
    tree_nuts: 'Contains: Tree nuts',
    dairy: 'Contains: Dairy',
    eggs: 'Contains: Eggs',
    soy: 'Contains: Soy',
    wheat: 'Contains: Wheat / Gluten',
    fish: 'Contains: Fish',
    shellfish: 'Contains: Shellfish',
    sesame: 'Contains: Sesame',
};

function normalizeIngredientName(name: string): string {
    return name.trim().toLowerCase().replace(/\s+/g, ' ');
}

export function labelsFromAllergenSlugs(slugs: readonly string[] | null | undefined): string[] {
    if (!slugs?.length) {
        return [];
    }
    const uniq: Record<string, true> = {};
    for (const raw of slugs) {
        const key = String(raw).trim().toLowerCase();
        const label = ALLERGEN_SLUG_TO_LABEL[key];
        if (label) {
            uniq[label] = true;
        }
    }
    return Object.keys(uniq).sort();
}

export type IngredientAllergenSource = {
    id?: number;
    name: string;
    common_allergens?: readonly string[];
};

export type IngredientRowForSafety = {
    ingredientId: number | null;
    selectedName: string;
    nameQuery: string;
};

export function collectSafetyAlertLabelsFromIngredientSelection(
    rows: readonly IngredientRowForSafety[],
    profiles: readonly IngredientAllergenSource[],
): string[] {
    const byId = new Map<number, IngredientAllergenSource>();
    for (const p of profiles) {
        if (typeof p.id === 'number' && Number.isFinite(p.id)) {
            byId.set(p.id, p);
        }
    }
    const byName = new Map<string, IngredientAllergenSource>();
    for (const p of profiles) {
        byName.set(normalizeIngredientName(p.name), p);
    }

    const labels: Record<string, true> = {};
    for (const r of rows) {
        let p: IngredientAllergenSource | undefined;
        if (r.ingredientId != null && Number.isFinite(r.ingredientId)) {
            p = byId.get(r.ingredientId);
        }
        if (!p) {
            const name = (r.selectedName || r.nameQuery || '').trim();
            p = byName.get(normalizeIngredientName(name));
        }
        if (!p?.common_allergens?.length) {
            continue;
        }
        for (const lab of labelsFromAllergenSlugs([...p.common_allergens])) {
            labels[lab] = true;
        }
    }
    return Object.keys(labels).sort();
}

export function sickleCellHighlightsTotals(n: Record<string, number>): Record<string, boolean> {
    return {
        folate: (n.b9_folate ?? 0) > 100,
        b12: (n.b12 ?? 0) > 2,
        magnesium: (n.magnesium ?? 0) > 100,
        iron: (n.iron ?? 0) > 5,
    };
}

/** @see \App\Services\RecipeNutritionCalculator::sickleCellProgramMealHighlight */
export function sickleCellProgramMealHighlight(n: Record<string, number>): boolean {
    const base = sickleCellHighlightsTotals(n);
    if (Object.values(base).some(Boolean)) {
        return true;
    }
    const iron = n.iron ?? 0;
    const vitaminC = n.vitamin_c ?? 0;
    if (iron >= 4.5 && vitaminC >= 25) {
        return true;
    }
    const zinc = n.zinc ?? 0;
    const vitaminE = n.vitamin_e ?? 0;
    if (zinc >= 2.5 && vitaminE >= 1.5) {
        return true;
    }
    return false;
}
