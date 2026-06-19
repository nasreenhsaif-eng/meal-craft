import type { MealNutritionalData, MealNutritionRow } from '../Components/Molecules/MealDetailView/MealDetailView.tsx';
import { formatTrimmedDecimal } from './buildNutritionalDataPer100g.ts';
import { nutrientRdiPercent } from './nutrientDailyRdi.ts';

type MealWithNutrition = {
    detailView?: {
        nutritionalData?: MealNutritionalData;
    };
};

export type AggregatedNutrientRow = {
    sectionTitle: string;
    label: string;
    total: number;
    valueClass?: string;
    sectionOrder: number;
    rowOrder: number;
};

const MACRO_MICRONUTRIENT_LABELS = new Set(['Fiber (g)', 'Sugar (g)']);

/**
 * @param {string} value
 */
function parseNutritionRowValue(value: string): number | null {
    const parsed = Number.parseFloat(String(value).replace(/,/g, '').trim());

    return Number.isFinite(parsed) ? parsed : null;
}

/**
 * @param {string} sectionTitle
 */
function normalizedSectionKey(sectionTitle: string): string {
    return sectionTitle.trim().toLowerCase();
}

/**
 * @param {string} sectionTitle
 * @param {string} label
 */
function shouldIncludeMicronutrientRow(sectionTitle: string, label: string): boolean {
    const section = normalizedSectionKey(sectionTitle);

    if (section.includes('vitamin') || section.includes('mineral')) {
        return true;
    }

    if (section.includes('macro')) {
        return MACRO_MICRONUTRIENT_LABELS.has(label);
    }

    return false;
}

/**
 * @param {number} total
 * @param {string} label
 */
function formatAggregatedValue(total: number, label: string): string {
    if (label === 'Total calories') {
        return String(Math.round(total));
    }

    return formatTrimmedDecimal(total, 1);
}

/**
 * @param {Partial<Record<string, MealWithNutrition[]>> | null | undefined} categories
 */
export function aggregateDayNutrientTotals(
    categories: Partial<Record<string, MealWithNutrition[]>> | null | undefined,
): AggregatedNutrientRow[] {
    if (!categories) {
        return [];
    }

    /** @type {Map<string, AggregatedNutrientRow>} */
    const rowsByKey = new Map();
    /** @type {Map<string, number>} */
    const sectionOrderByTitle = new Map();
    /** @type {Map<string, number>} */
    const nextRowOrderBySection = new Map();
    let nextSectionOrder = 0;

    for (const meals of Object.values(categories)) {
        for (const meal of meals ?? []) {
            const sections = meal.detailView?.nutritionalData?.sections ?? [];

            for (const section of sections) {
                const sectionTitle = section.title ?? '';

                if (!sectionOrderByTitle.has(sectionTitle)) {
                    sectionOrderByTitle.set(sectionTitle, nextSectionOrder++);
                }

                const sectionOrder = sectionOrderByTitle.get(sectionTitle) ?? 0;

                for (const row of section.rows ?? []) {
                    const parsed = parseNutritionRowValue(row.value);
                    if (parsed === null) {
                        continue;
                    }

                    const key = `${sectionTitle}::${row.label}`;
                    const existing = rowsByKey.get(key);

                    if (existing) {
                        existing.total += parsed;
                        continue;
                    }

                    const rowOrder = nextRowOrderBySection.get(sectionTitle) ?? 0;
                    nextRowOrderBySection.set(sectionTitle, rowOrder + 1);

                    rowsByKey.set(key, {
                        sectionTitle,
                        label: row.label,
                        total: parsed,
                        valueClass: row.valueClass,
                        sectionOrder,
                        rowOrder,
                    });
                }
            }
        }
    }

    return [...rowsByKey.values()].sort((a, b) => {
        if (a.sectionOrder !== b.sectionOrder) {
            return a.sectionOrder - b.sectionOrder;
        }

        return a.rowOrder - b.rowOrder;
    });
}

export type DayMicronutrientRow = AggregatedNutrientRow & {
    formattedTotal: string;
    rdiPercent: number | null;
    formattedRdiPercent: string | null;
};

/**
 * @param {Partial<Record<string, MealWithNutrition[]>> | null | undefined} categories
 */
export function aggregateDayMicronutrientRows(
    categories: Partial<Record<string, MealWithNutrition[]>> | null | undefined,
): DayMicronutrientRow[] {
    return aggregateDayNutrientTotals(categories)
        .filter((row) => shouldIncludeMicronutrientRow(row.sectionTitle, row.label))
        .map((row) => {
            const rdiPercent = nutrientRdiPercent(row.label, row.total);

            return {
                ...row,
                formattedTotal: formatAggregatedValue(row.total, row.label),
                rdiPercent,
                formattedRdiPercent:
                    rdiPercent === null ? null : `${Math.round(rdiPercent)}%`,
            };
        });
}

/**
 * @param {AggregatedNutrientRow[]} rows
 * @param {(sectionTitle: string, label: string) => boolean} includeRow
 */
function buildMealNutritionalDataFromRows(
    rows: AggregatedNutrientRow[],
    includeRow: (sectionTitle: string, label: string) => boolean,
): MealNutritionalData | null {
    /** @type {Map<string, { title: string; order: number; rows: MealNutritionRow[] }>} */
    const sectionsByTitle = new Map();

    for (const row of rows) {
        if (!includeRow(row.sectionTitle, row.label)) {
            continue;
        }

        const section = sectionsByTitle.get(row.sectionTitle) ?? {
            title: row.sectionTitle,
            order: row.sectionOrder,
            rows: [],
        };

        section.rows.push({
            label: row.label,
            value: formatAggregatedValue(row.total, row.label),
            valueClass: row.valueClass,
        });

        sectionsByTitle.set(row.sectionTitle, section);
    }

    if (sectionsByTitle.size === 0) {
        return null;
    }

    const sections = [...sectionsByTitle.values()]
        .sort((a, b) => a.order - b.order)
        .map((section) => ({
            title: section.title,
            rows: section.rows,
        }));

    return {
        valueColumnLabel: 'Full day',
        sections,
    };
}

/**
 * Sum vitamins, minerals, fiber, and sugar from each meal's detail view for one delivery day.
 *
 * @param {Partial<Record<string, MealWithNutrition[]>> | null | undefined} categories
 */
export function aggregateDayNutritionalData(
    categories: Partial<Record<string, MealWithNutrition[]>> | null | undefined,
): MealNutritionalData | null {
    return buildMealNutritionalDataFromRows(aggregateDayNutrientTotals(categories), shouldIncludeMicronutrientRow);
}

export type MicronutrientRdiTableRow = {
    label: string;
    fullDay: string;
    rdiPercent: string;
    rdiPercentValue: number | null;
};

/**
 * @param {DayMicronutrientRow[]} rows
 */
export function micronutrientRowsForRdiTable(rows: DayMicronutrientRow[]): MicronutrientRdiTableRow[] {
    return rows.map((row) => ({
        label: row.label,
        fullDay: row.formattedTotal,
        rdiPercent: row.formattedRdiPercent ?? '—',
        rdiPercentValue: row.rdiPercent,
    }));
}
