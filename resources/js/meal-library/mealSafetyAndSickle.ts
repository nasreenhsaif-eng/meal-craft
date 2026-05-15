/**
 * Client-side mirrors of `App\Support\IngredientAllergenCatalog` and
 * `App\Support\SickleCellNutrientRdi` — keep highlight rules aligned.
 */

import {
    sickleCellHasAnyHighlight,
    sickleCellHighlightBadgeLabels,
    SICKLE_CELL_BADGE_TOOLTIPS,
} from './sickleCellNutrientRdi.ts';

export {
    sickleCellHasAnyHighlight,
    sickleCellHighlightBadgeLabels,
    SICKLE_CELL_BADGE_TOOLTIPS,
};

import { normalizeIngredientKey } from './aggregateIngredientNutrition.ts';
import { parseIngredientQuantityString } from './ingredientQuantityString.ts';

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

export const G6PD_TRIGGER_SAFETY_LABEL = 'G6PD Trigger';

export const G6PD_HIGHLIGHT_BADGE = 'G6PD Alert';

export type IngredientAllergenSource = {
    id?: number;
    name: string;
    common_allergens?: readonly string[];
    is_g6pd_trigger?: boolean;
};

export type IngredientRowForSafety = {
    ingredientId: number | null;
    selectedName: string;
    nameQuery: string;
};

function resolveProfileForRow(
    row: IngredientRowForSafety,
    byId: Map<number, IngredientAllergenSource>,
    byName: Map<string, IngredientAllergenSource>,
): IngredientAllergenSource | undefined {
    if (row.ingredientId != null && Number.isFinite(row.ingredientId)) {
        const byIdHit = byId.get(row.ingredientId);
        if (byIdHit) {
            return byIdHit;
        }
    }
    const name = (row.selectedName || row.nameQuery || '').trim();

    return byName.get(normalizeIngredientName(name));
}

function buildIngredientLookupMaps(profiles: readonly IngredientAllergenSource[]): {
    byId: Map<number, IngredientAllergenSource>;
    byName: Map<string, IngredientAllergenSource>;
} {
    const byId = new Map<number, IngredientAllergenSource>();
    const byName = new Map<string, IngredientAllergenSource>();
    for (const p of profiles) {
        if (typeof p.id === 'number' && Number.isFinite(p.id)) {
            byId.set(p.id, p);
        }
        byName.set(normalizeIngredientName(p.name), p);
    }

    return { byId, byName };
}

export function mealContainsG6pdTriggerFromSelection(
    rows: readonly IngredientRowForSafety[],
    profiles: readonly IngredientAllergenSource[],
): boolean {
    const { byId, byName } = buildIngredientLookupMaps(profiles);
    for (const r of rows) {
        const p = resolveProfileForRow(r, byId, byName);
        if (p?.is_g6pd_trigger) {
            return true;
        }
    }

    return false;
}

/** Scan a pipe/newline ingredient quantity string (before or after Apply). */
export function mealContainsG6pdTriggerFromIngredientString(
    ingredientsString: string,
    profiles: readonly IngredientAllergenSource[],
): boolean {
    const raw = ingredientsString.replace(/\r\n/g, '\n').trim();
    if (raw === '') {
        return false;
    }

    const byName = new Map<string, IngredientAllergenSource>();
    for (const p of profiles) {
        byName.set(normalizeIngredientKey(p.name), p);
    }

    for (const seg of parseIngredientQuantityString(raw)) {
        const p = byName.get(normalizeIngredientKey(seg.name));
        if (p?.is_g6pd_trigger) {
            return true;
        }
    }

    return false;
}

export function mealHasG6pdTriggerInEditor(
    rows: readonly IngredientRowForSafety[],
    ingredientsString: string,
    profiles: readonly IngredientAllergenSource[],
): boolean {
    return (
        mealContainsG6pdTriggerFromSelection(rows, profiles) ||
        mealContainsG6pdTriggerFromIngredientString(ingredientsString, profiles)
    );
}

export function collectSafetyAlertLabelsFromIngredientSelection(
    rows: readonly IngredientRowForSafety[],
    profiles: readonly IngredientAllergenSource[],
): string[] {
    const { byId, byName } = buildIngredientLookupMaps(profiles);

    const labels: Record<string, true> = {};
    for (const r of rows) {
        const p = resolveProfileForRow(r, byId, byName);
        if (!p?.common_allergens?.length) {
            continue;
        }
        for (const lab of labelsFromAllergenSlugs([...p.common_allergens])) {
            labels[lab] = true;
        }
    }

    if (mealContainsG6pdTriggerFromSelection(rows, profiles)) {
        labels[G6PD_TRIGGER_SAFETY_LABEL] = true;
    }

    return Object.keys(labels).sort();
}

/** @deprecated Use {@link sickleCellHasAnyHighlight} */
export function sickleCellProgramMealHighlight(n: Record<string, number>): boolean {
    return sickleCellHasAnyHighlight(n);
}
