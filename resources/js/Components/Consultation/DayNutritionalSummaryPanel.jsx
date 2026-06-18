import { useMemo } from 'react';
import NutrientBadge from '../Atoms/MealSystem/NutrientBadge.jsx';
import SafetyAlerts from '../MealSystem/SafetyAlerts.jsx';
import { MealNutritionSummaryTable } from '../Molecules/MealDetailView/MealDetailView';
import { G6PD_HIGHLIGHT_BADGE } from '../../meal-library/mealSafetyAndSickle.ts';
import { aggregateDayNutritionalData } from '../../meal-library/aggregateDayNutritionalData.ts';
import {
    PLAN_MACRO_CATEGORY_ROWS,
    PlanMacroSummaryPanel,
    sumActiveDayMacros,
} from './ChooseYourMeals.jsx';

/**
 * @param {Partial<Record<string, Array<{ title?: string; detailView?: Record<string, unknown> }>>>} categories
 */
export function aggregateDayNutritionInsights(categories) {
    /** @type {Map<string, { label: string; variant?: string }>} */
    const safetyByKey = new Map();
    /** @type {Set<string>} */
    const sickleBadges = new Set();
    let hasG6pdTrigger = false;

    /** @type {Array<{ title: string; category: string; hasG6pdTrigger: boolean; safetyAlerts: object[]; sickleCellHighlights: string[] }>} */
    const mealInsights = [];

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

            if (mealAlerts.length > 0 || mealSickle.length > 0 || detailView.hasG6pdTrigger) {
                mealInsights.push({
                    title: meal.title ?? 'Meal',
                    category: row.label,
                    hasG6pdTrigger: Boolean(detailView.hasG6pdTrigger),
                    safetyAlerts: mealAlerts,
                    sickleCellHighlights: mealSickle,
                });
            }
        }
    }

    return {
        safetyAlerts: [...safetyByKey.values()],
        sickleCellHighlights: [...sickleBadges],
        hasG6pdTrigger,
        mealInsights,
    };
}

const SICKLE_RDI_FOOTNOTE = 'High Source: ≥20% of daily RDI per serving';

/**
 * Full-day nutritional summary: macros, safety alerts, G6PD warnings, sickle-cell highlights.
 *
 * @param {object} props
 * @param {Partial<Record<string, unknown[]>>} props.categories
 * @param {string} [props.dayLabel]
 * @param {string} [props.planCategoryLabel]
 */
export default function DayNutritionalSummaryPanel({ categories, dayLabel = 'Day', planCategoryLabel = '' }) {
    const activeDayTotals = useMemo(() => sumActiveDayMacros(categories), [categories]);

    const insights = useMemo(() => aggregateDayNutritionInsights(categories), [categories]);

    const dayNutritionalData = useMemo(() => aggregateDayNutritionalData(categories), [categories]);

    return (
        <div className="space-y-6">
            <PlanMacroSummaryPanel
                activeDayTotals={activeDayTotals}
                categories={categories}
                dayLabel={dayLabel}
                planCategoryLabel={planCategoryLabel}
            />

            <div className="rounded-[12px] border border-gray-200 bg-white px-4 py-4 sm:px-5">
                <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                    Micronutrients
                </p>
                <p className="mt-1 font-body text-xs text-[#6B7280]">
                    Full-day totals across all meals for {dayLabel.toLowerCase()}.
                </p>
                <div className="mt-4">
                    {dayNutritionalData ? (
                        <MealNutritionSummaryTable data={dayNutritionalData} />
                    ) : (
                        <p className="font-body text-sm text-[#555555]">No micronutrient data available for this day.</p>
                    )}
                </div>
            </div>

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

            <div className="rounded-[12px] border border-gray-200 bg-white px-4 py-4 sm:px-5">
                <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                    Allergy &amp; safety alerts
                </p>
                {insights.safetyAlerts.length > 0 ? (
                    <div className="mt-3">
                        <SafetyAlerts alerts={insights.safetyAlerts} />
                    </div>
                ) : (
                    <p className="mt-2 font-body text-sm text-[#555555]">No allergy or safety alerts for this day.</p>
                )}
            </div>

            <div className="rounded-[12px] border border-gray-200 bg-[#F8F9F6] px-4 py-4 sm:px-5">
                <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                    Sickle cell highlights
                </p>
                <p className="mt-1 font-body text-xs text-[#6B7280]">{SICKLE_RDI_FOOTNOTE}</p>
                <div className="mt-3 flex flex-wrap gap-2">
                    {insights.sickleCellHighlights.length > 0 ? (
                        insights.sickleCellHighlights.map((badge) => <NutrientBadge key={badge} type={badge} />)
                    ) : (
                        <p className="font-body text-sm text-[#555555]">—</p>
                    )}
                </div>
            </div>

            {insights.mealInsights.length > 0 ? (
                <div className="rounded-[12px] border border-gray-200 bg-white px-4 py-4 sm:px-5">
                    <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#5A6B44]">
                        By meal
                    </p>
                    <ul className="mt-4 space-y-4">
                        {insights.mealInsights.map((meal) => (
                            <li key={`${meal.category}-${meal.title}`} className="border-t border-gray-100 pt-4 first:border-t-0 first:pt-0">
                                <p className="font-montserrat text-sm font-bold text-[#262A22]">{meal.title}</p>
                                <p className="font-body text-xs text-[#555555]">{meal.category}</p>
                                {meal.hasG6pdTrigger ? (
                                    <div className="mt-2">
                                        <NutrientBadge type="G6PD Safety Alert" />
                                    </div>
                                ) : null}
                                {meal.safetyAlerts.length > 0 ? (
                                    <div className="mt-2">
                                        <SafetyAlerts alerts={meal.safetyAlerts} />
                                    </div>
                                ) : null}
                                {meal.sickleCellHighlights.length > 0 ? (
                                    <div className="mt-2 flex flex-wrap gap-2">
                                        {meal.sickleCellHighlights.map((badge) => (
                                            <NutrientBadge key={`${meal.title}-${badge}`} type={badge} />
                                        ))}
                                    </div>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                </div>
            ) : null}
        </div>
    );
}
