import type { MealNutritionalData, MealNutritionRow } from '../Components/Molecules/MealDetailView/MealDetailView.tsx';
import { formatTrimmedDecimal } from './buildNutritionalDataPer100g.ts';

type MealWithNutrition = {
    detailView?: {
        nutritionalData?: MealNutritionalData;
    };
};

type AggregatedRow = {
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
function shouldIncludeRow(sectionTitle: string, label: string): boolean {
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
 * Sum vitamins, minerals, fiber, and sugar from each meal's detail view for one delivery day.
 *
 * @param {Partial<Record<string, MealWithNutrition[]>> | null | undefined} categories
 */
export function aggregateDayNutritionalData(
    categories: Partial<Record<string, MealWithNutrition[]>> | null | undefined,
): MealNutritionalData | null {
    if (!categories) {
        return null;
    }

    /** @type {Map<string, AggregatedRow>} */
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
                    if (!shouldIncludeRow(sectionTitle, row.label)) {
                        continue;
                    }

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

    if (rowsByKey.size === 0) {
        return null;
    }

    /** @type {Map<string, { title: string; order: number; rows: MealNutritionRow[] }>} */
    const sectionsByTitle = new Map();

    for (const row of [...rowsByKey.values()].sort((a, b) => {
        if (a.sectionOrder !== b.sectionOrder) {
            return a.sectionOrder - b.sectionOrder;
        }

        return a.rowOrder - b.rowOrder;
    })) {
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
