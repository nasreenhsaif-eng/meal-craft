/**
 * Mirrors `App\Support\SickleCellNutrientRdi` — per-serving ≥20% RDI = High Source badge.
 */

export const SICKLE_CELL_HIGH_SOURCE_FRACTION = 0.2;

export const SICKLE_CELL_RDI = {
    b9_folate: 1000,
    b12: 2.4,
    b6: 1.7,
    vitamin_d: 15,
    calcium: 1000,
    zinc: 11,
    vitamin_a: 900,
    vitamin_c: 90,
    vitamin_e: 15,
} as const;

export const SICKLE_CELL_BADGE = {
    Folate: 'Folate',
    B12: 'B12',
    B6: 'B6',
    VitaminDCalcium: 'Vitamin D & Calcium',
    Zinc: 'Zinc',
    Antioxidants: 'Antioxidants',
} as const;

export type SickleCellHighlightBadge = (typeof SICKLE_CELL_BADGE)[keyof typeof SICKLE_CELL_BADGE];

export const SICKLE_CELL_BADGE_TOOLTIPS: Record<SickleCellHighlightBadge, string> = {
    Folate: 'Supports the continuous production of fresh, healthy red blood cells to maintain optimal energy.',
    B12: 'Essential for DNA synthesis and works with Folate to ensure robust red blood cell formation.',
    B6: 'Promotes efficient hemoglobin function and boosts daily energy metabolism.',
    'Vitamin D & Calcium':
        'Strengthens bone density and supports skeletal resilience and a powerful immune system.',
    Zinc: 'Key for cellular repair, efficient wound healing, and maintaining high-performing immune defense.',
    Antioxidants: 'Protects red blood cell membranes and promotes resilience against oxidative stress.',
};

export function sickleCellHighSourceThreshold(rdi: number): number {
    return rdi * SICKLE_CELL_HIGH_SOURCE_FRACTION;
}

export function isSickleCellHighSource(amount: number, rdi: number): boolean {
    return rdi > 0 && amount >= sickleCellHighSourceThreshold(rdi);
}

/** Per-serving nutrition (bulk: batch total ÷ servings). */
export function sickleCellHighlightBadgeLabels(n: Record<string, number>): SickleCellHighlightBadge[] {
    const badges: SickleCellHighlightBadge[] = [];

    if (isSickleCellHighSource(n.b9_folate ?? 0, SICKLE_CELL_RDI.b9_folate)) {
        badges.push(SICKLE_CELL_BADGE.Folate);
    }
    if (isSickleCellHighSource(n.b12 ?? 0, SICKLE_CELL_RDI.b12)) {
        badges.push(SICKLE_CELL_BADGE.B12);
    }
    if (isSickleCellHighSource(n.b6 ?? 0, SICKLE_CELL_RDI.b6)) {
        badges.push(SICKLE_CELL_BADGE.B6);
    }
    if (
        isSickleCellHighSource(n.vitamin_d ?? 0, SICKLE_CELL_RDI.vitamin_d) &&
        isSickleCellHighSource(n.calcium ?? 0, SICKLE_CELL_RDI.calcium)
    ) {
        badges.push(SICKLE_CELL_BADGE.VitaminDCalcium);
    }
    if (isSickleCellHighSource(n.zinc ?? 0, SICKLE_CELL_RDI.zinc)) {
        badges.push(SICKLE_CELL_BADGE.Zinc);
    }
    if (
        isSickleCellHighSource(n.vitamin_a ?? 0, SICKLE_CELL_RDI.vitamin_a) ||
        isSickleCellHighSource(n.vitamin_c ?? 0, SICKLE_CELL_RDI.vitamin_c) ||
        isSickleCellHighSource(n.vitamin_e ?? 0, SICKLE_CELL_RDI.vitamin_e)
    ) {
        badges.push(SICKLE_CELL_BADGE.Antioxidants);
    }

    return badges;
}

export function sickleCellHasAnyHighlight(n: Record<string, number>): boolean {
    return sickleCellHighlightBadgeLabels(n).length > 0;
}
