import { Fragment, useMemo } from 'react';
import NutrientBadge from '../Atoms/MealSystem/NutrientBadge.jsx';
import SafetyAlerts from '../MealSystem/SafetyAlerts.jsx';
import { G6PD_HIGHLIGHT_BADGE } from '../../meal-library/mealSafetyAndSickle.ts';
import { aggregateDayMicronutrientRows } from '../../meal-library/aggregateDayNutritionalData.ts';
import {
    PLAN_MACRO_CATEGORY_ROWS,
    PlanMacroSummaryPanel,
    sumActiveDayMacros,
} from './ChooseYourMeals.jsx';

/** @typedef {'meals' | 'macronutrients' | 'micronutrients' | 'allergies' | 'sickle'} DaySummaryTabId */

export const DAY_SUMMARY_TABS = /** @type {const} */ ([
    { id: 'meals', label: 'Meals' },
    { id: 'macronutrients', label: 'Macronutrients' },
    { id: 'micronutrients', label: 'Micronutrients' },
    { id: 'allergies', label: 'Allergy & safety' },
    { id: 'sickle', label: 'Sickle cell' },
]);

/**
 * @param {Partial<Record<string, Array<{ title?: string; detailView?: Record<string, unknown> }>>>} categories
 */
export function aggregateDayNutritionInsights(categories) {
    /** @type {Map<string, { label: string; variant?: string }>} */
    const safetyByKey = new Map();
    /** @type {Set<string>} */
    const sickleBadges = new Set();
    let hasG6pdTrigger = false;

    for (const row of PLAN_MACRO_CATEGORY_ROWS) {
        const meals = categories?.[row.key] ?? [];

        for (const meal of meals) {
            const detailView = meal.detailView ?? {};
            const mealAlerts = Array.isArray(detailView.safetyAlerts) ? detailView.safetyAlerts : [];

            if (detailView.hasG6pdTrigger) {
                hasG6pdTrigger = true;
            }

            for (const alert of mealAlerts) {
                if (alert && typeof alert.label === 'string') {
                    const key = `${alert.variant ?? 'allergy'}:${alert.label}`;
                    safetyByKey.set(key, alert);
                }
            }

            const mealSickle = (Array.isArray(detailView.sickleCellHighlights) ? detailView.sickleCellHighlights : []).filter(
                (badge) => badge !== G6PD_HIGHLIGHT_BADGE,
            );
            mealSickle.forEach((badge) => sickleBadges.add(badge));
        }
    }

    return {
        safetyAlerts: [...safetyByKey.values()],
        sickleCellHighlights: [...sickleBadges],
        hasG6pdTrigger,
    };
}

/**
 * @param {Partial<Record<string, Array<{ id?: string; title?: string; macros?: object; detailView?: Record<string, unknown> }>>>} categories
 */
export function listDayMealsWithHighlights(categories) {
    /** @type {Array<{ id: string; title: string; category: string; macros?: object; safetyAlerts: object[]; sickleCellHighlights: string[]; hasG6pdTrigger: boolean; detailView?: object }>} */
    const meals = [];

    for (const row of PLAN_MACRO_CATEGORY_ROWS) {
        for (const meal of categories?.[row.key] ?? []) {
            const detailView = meal.detailView ?? {};
            const mealAlerts = Array.isArray(detailView.safetyAlerts) ? detailView.safetyAlerts : [];
            const mealSickle = (Array.isArray(detailView.sickleCellHighlights) ? detailView.sickleCellHighlights : []).filter(
                (badge) => badge !== G6PD_HIGHLIGHT_BADGE,
            );

            meals.push({
                id: String(meal.id ?? `${row.key}-${meal.title}`),
                title: meal.title ?? 'Meal',
                category: row.label,
                macros: meal.macros,
                safetyAlerts: mealAlerts,
                sickleCellHighlights: mealSickle,
                hasG6pdTrigger: Boolean(detailView.hasG6pdTrigger),
                detailView: meal.detailView,
            });
        }
    }

    return meals;
}

const SICKLE_RDI_FOOTNOTE = 'High Source: ≥20% of daily RDI per serving';

function SummarySection({ title, description, children, tone = 'white' }) {
    const toneClass =
        tone === 'muted' ? 'border-gray-200 bg-[#F8F9F6]' : 'border-gray-200 bg-white';

    return (
        <div className={`rounded-[12px] border px-4 py-4 sm:px-5 ${toneClass}`}>
            <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">{title}</p>
            {description ? <p className="mt-1 font-body text-xs text-[#6B7280]">{description}</p> : null}
            <div className="mt-4">{children}</div>
        </div>
    );
}

/**
 * @param {object} props
 * @param {Array<{ id: string; title: string; category: string; safetyAlerts: object[]; sickleCellHighlights: string[]; hasG6pdTrigger: boolean; detailView?: object }>} props.meals
 * @param {(meal: object) => void} [props.onOpenMeal]
 */
export function DayMealsTabPanel({ meals, onOpenMeal }) {
    if (meals.length === 0) {
        return <p className="font-body text-sm text-[#555555]">No meals assigned for this day.</p>;
    }

    /** @type {Record<string, typeof meals>} */
    const byCategory = meals.reduce((acc, meal) => {
        if (!acc[meal.category]) {
            acc[meal.category] = [];
        }

        acc[meal.category].push(meal);

        return acc;
    }, {});

    return (
        <div className="space-y-6">
            {Object.entries(byCategory).map(([category, categoryMeals]) => (
                <section key={category} className="rounded-[12px] border border-gray-200 bg-white px-4 py-4 sm:px-5">
                    <h2 className="font-montserrat text-sm font-bold uppercase tracking-[0.12em] text-[#5A6B44]">
                        {category}
                    </h2>
                    <ul className="mt-4 space-y-4">
                        {categoryMeals.map((meal) => {
                            const hasHighlights =
                                meal.hasG6pdTrigger ||
                                meal.safetyAlerts.length > 0 ||
                                meal.sickleCellHighlights.length > 0;

                            return (
                                <li key={meal.id} className="border-t border-gray-100 pt-4 first:border-t-0 first:pt-0">
                                    {onOpenMeal ? (
                                        <button
                                            type="button"
                                            onClick={() => onOpenMeal(meal)}
                                            className="text-left font-montserrat text-sm font-bold text-[#262A22] hover:text-[#5A6B44] hover:underline"
                                        >
                                            {meal.title}
                                        </button>
                                    ) : (
                                        <p className="font-montserrat text-sm font-bold text-[#262A22]">{meal.title}</p>
                                    )}

                                    {hasHighlights ? (
                                        <div className="mt-3 space-y-2">
                                            <p className="font-montserrat text-[10px] font-bold uppercase tracking-[0.14em] text-[#5A6B44]">
                                                Highlights
                                            </p>
                                            {meal.hasG6pdTrigger ? (
                                                <div>
                                                    <NutrientBadge type="G6PD Safety Alert" />
                                                </div>
                                            ) : null}
                                            {meal.safetyAlerts.length > 0 ? (
                                                <SafetyAlerts alerts={meal.safetyAlerts} />
                                            ) : null}
                                            {meal.sickleCellHighlights.length > 0 ? (
                                                <div className="flex flex-wrap gap-2">
                                                    {meal.sickleCellHighlights.map((badge) => (
                                                        <NutrientBadge key={`${meal.id}-${badge}`} type={badge} />
                                                    ))}
                                                </div>
                                            ) : null}
                                        </div>
                                    ) : (
                                        <p className="mt-2 font-body text-xs text-[#6B7280]">No sickle-cell or safety highlights.</p>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                </section>
            ))}
        </div>
    );
}

/**
 * @param {object} props
 * @param {Partial<Record<string, unknown[]>>} props.categories
 * @param {string} [props.dayLabel]
 * @param {string} [props.planCategoryLabel]
 */
export function DayMacronutrientsTabPanel({ categories, dayLabel = 'Day', planCategoryLabel = '' }) {
    const activeDayTotals = useMemo(() => sumActiveDayMacros(categories), [categories]);

    return (
        <PlanMacroSummaryPanel
            activeDayTotals={activeDayTotals}
            categories={categories}
            dayLabel={dayLabel}
            planCategoryLabel={planCategoryLabel}
        />
    );
}

function MicronutrientRdiTable({ rows }) {
    if (rows.length === 0) {
        return <p className="font-body text-sm text-[#555555]">No micronutrient data available for this day.</p>;
    }

    /** @type {Array<{ title: string; order: number; rows: typeof rows }>} */
    const sections = [];
    /** @type {Map<string, { title: string; order: number; rows: typeof rows }>} */
    const sectionByTitle = new Map();

    for (const row of rows) {
        const section = sectionByTitle.get(row.sectionTitle) ?? {
            title: row.sectionTitle,
            order: row.sectionOrder,
            rows: [],
        };

        if (!sectionByTitle.has(row.sectionTitle)) {
            sectionByTitle.set(row.sectionTitle, section);
            sections.push(section);
        }

        section.rows.push(row);
    }

    sections.sort((a, b) => a.order - b.order);

    return (
        <div
            role="region"
            aria-label="Micronutrients with percent RDI"
            className="overflow-x-auto rounded-[12px] border border-gray-200 bg-white"
        >
            <table className="w-full min-w-[320px] border-collapse text-left text-sm">
                <thead>
                    <tr className="border-b border-gray-200 bg-[#F8F9F6]">
                        <th className="px-3 py-2.5 font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                            Nutrient
                        </th>
                        <th className="px-3 py-2.5 text-right font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                            Full day
                        </th>
                        <th className="px-3 py-2.5 text-right font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                            % RDI
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {sections.map((section) => (
                        <Fragment key={section.title}>
                            <tr className="bg-[#F8F9F6]">
                                <td
                                    colSpan={3}
                                    className="px-3 py-2 font-montserrat text-xs font-bold uppercase tracking-[0.12em] text-[#5A6B44]"
                                >
                                    {section.title}
                                </td>
                            </tr>
                            {section.rows.map((row) => (
                                <tr key={`${section.title}-${row.label}`} className="border-b border-gray-100 last:border-b-0">
                                    <td className="px-3 py-2.5 font-montserrat text-sm font-medium text-[#374151]">
                                        {row.label}
                                    </td>
                                    <td className="px-3 py-2.5 text-right font-montserrat text-sm font-bold tabular-nums text-[#1F2937]">
                                        {row.formattedTotal}
                                    </td>
                                    <td
                                        className={[
                                            'px-3 py-2.5 text-right font-montserrat text-sm font-bold tabular-nums',
                                            row.rdiPercent != null && row.rdiPercent >= 20
                                                ? 'text-[#5A6B44]'
                                                : 'text-[#1F2937]',
                                        ].join(' ')}
                                    >
                                        {row.formattedRdiPercent ?? '—'}
                                    </td>
                                </tr>
                            ))}
                        </Fragment>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/**
 * @param {object} props
 * @param {Partial<Record<string, unknown[]>>} props.categories
 * @param {string} [props.dayLabel]
 */
export function DayMicronutrientsTabPanel({ categories, dayLabel = 'Day' }) {
    const micronutrientRows = useMemo(() => aggregateDayMicronutrientRows(categories), [categories]);

    return (
        <SummarySection
            title="Micronutrients"
            description={`Full-day totals and % of daily reference intake for ${dayLabel.toLowerCase()}.`}
        >
            <MicronutrientRdiTable rows={micronutrientRows} />
        </SummarySection>
    );
}

/**
 * @param {object} props
 * @param {Partial<Record<string, unknown[]>>} props.categories
 */
export function DayAllergySafetyTabPanel({ categories }) {
    const insights = useMemo(() => aggregateDayNutritionInsights(categories), [categories]);

    return (
        <div className="space-y-6">
            {insights.hasG6pdTrigger ? (
                <div className="rounded-[12px] border-2 border-[#B91C1C] bg-[#FEF2F2] p-4 shadow-sm">
                    <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#991B1B]">
                        G6PD safety
                    </p>
                    <div className="mt-3">
                        <NutrientBadge type="G6PD Safety Alert" />
                    </div>
                    <p className="mt-2 font-body text-sm text-[#7F1D1D]">
                        One or more meals today contain ingredients flagged as unsafe for G6PD deficiency.
                    </p>
                </div>
            ) : null}

            <SummarySection title="Allergy &amp; safety alerts">
                {insights.safetyAlerts.length > 0 ? (
                    <SafetyAlerts alerts={insights.safetyAlerts} />
                ) : (
                    <p className="font-body text-sm text-[#555555]">No allergy or safety alerts for this day.</p>
                )}
            </SummarySection>
        </div>
    );
}

/**
 * @param {object} props
 * @param {Partial<Record<string, unknown[]>>} props.categories
 */
export function DaySickleCellTabPanel({ categories }) {
    const insights = useMemo(() => aggregateDayNutritionInsights(categories), [categories]);

    const mealsWithSickle = useMemo(
        () =>
            listDayMealsWithHighlights(categories).filter(
                (meal) => meal.sickleCellHighlights.length > 0 || meal.hasG6pdTrigger,
            ),
        [categories],
    );

    return (
        <div className="space-y-6">
            <SummarySection title="Sickle cell highlights" description={SICKLE_RDI_FOOTNOTE} tone="muted">
                <div className="flex flex-wrap gap-2">
                    {insights.sickleCellHighlights.length > 0 ? (
                        insights.sickleCellHighlights.map((badge) => <NutrientBadge key={badge} type={badge} />)
                    ) : (
                        <p className="font-body text-sm text-[#555555]">—</p>
                    )}
                </div>
            </SummarySection>

            {mealsWithSickle.length > 0 ? (
                <SummarySection title="By meal">
                    <ul className="space-y-4">
                        {mealsWithSickle.map((meal) => (
                            <li key={meal.id} className="border-t border-gray-100 pt-4 first:border-t-0 first:pt-0">
                                <p className="font-montserrat text-sm font-bold text-[#262A22]">{meal.title}</p>
                                <p className="font-body text-xs text-[#555555]">{meal.category}</p>
                                {meal.sickleCellHighlights.length > 0 ? (
                                    <div className="mt-2 flex flex-wrap gap-2">
                                        {meal.sickleCellHighlights.map((badge) => (
                                            <NutrientBadge key={`${meal.id}-${badge}`} type={badge} />
                                        ))}
                                    </div>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                </SummarySection>
            ) : null}
        </div>
    );
}

/**
 * @param {object} props
 * @param {DaySummaryTabId} props.tab
 * @param {Partial<Record<string, unknown[]>>} props.categories
 * @param {string} [props.dayLabel]
 * @param {string} [props.planCategoryLabel]
 * @param {(meal: object) => void} [props.onOpenMeal]
 */
export default function DayNutritionalSummaryPanel({
    tab,
    categories,
    dayLabel = 'Day',
    planCategoryLabel = '',
    onOpenMeal,
}) {
    const meals = useMemo(() => listDayMealsWithHighlights(categories), [categories]);

    switch (tab) {
        case 'meals':
            return <DayMealsTabPanel meals={meals} onOpenMeal={onOpenMeal} />;
        case 'macronutrients':
            return (
                <DayMacronutrientsTabPanel
                    categories={categories}
                    dayLabel={dayLabel}
                    planCategoryLabel={planCategoryLabel}
                />
            );
        case 'micronutrients':
            return <DayMicronutrientsTabPanel categories={categories} dayLabel={dayLabel} />;
        case 'allergies':
            return <DayAllergySafetyTabPanel categories={categories} />;
        case 'sickle':
            return <DaySickleCellTabPanel categories={categories} />;
        default:
            return null;
    }
}
