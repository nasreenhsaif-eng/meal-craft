import { Fragment, useMemo, useState } from 'react';
import Button from '../Atoms/Button/Button.jsx';
import NutrientBadge from '../MealSystem/NutrientBadge.jsx';
import SafetyAlerts from '../MealSystem/SafetyAlerts.jsx';
import { G6PD_HIGHLIGHT_BADGE } from '../../meal-library/mealSafetyAndSickle.ts';
import { aggregateDayMicronutrientRows } from '../../meal-library/aggregateDayNutritionalData.ts';
import {
    BEST_EFFORT_NUTRIENT_LABELS,
    calculateMicronutrientTargets,
    FLOOR_RDI_TARGET_PERCENT,
} from '../../meal-library/nutrientDailyRdi.ts';
import CaloricThresholdNotice from './CaloricThresholdNotice.jsx';
import {
    PLAN_MACRO_CATEGORY_ROWS,
    PlanMacroSummaryPanel,
    sumActiveDayMacros,
} from './ChooseYourMeals.jsx';
import ProtocolMealSlotCard from './ProtocolMealSlotCard.jsx';

export const DAY_SUMMARY_TABS = /** @type {const} */ ([
    { id: 'meals', label: 'Meals' },
    { id: 'macronutrients', label: 'Macronutrients' },
    { id: 'micronutrients', label: 'Micronutrients' },
    { id: 'allergies', label: 'Allergy & safety' },
    { id: 'sickle', label: 'Sickle cell' },
]);

/** Macro + micro tabs for day nutrition review during consultation or admin preview. */
export const DAY_MACRO_MICRO_TABS = /** @type {const} */ ([
    { id: 'macronutrients', label: 'Macronutrients' },
    { id: 'micronutrients', label: 'Micronutrients' },
]);

/** @typedef {'meals' | 'macronutrients' | 'micronutrients' | 'allergies' | 'sickle'} DaySummaryTabId */

/** @typedef {'macronutrients' | 'micronutrients'} DayMacroMicroTabId */

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
                imageUrl: typeof meal.imageUrl === 'string' ? meal.imageUrl : undefined,
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
        <div className={`rounded-[12px] border px-2 py-4 sm:px-5 ${toneClass}`}>
            <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">{title}</p>
            {description ? <p className="mt-1 font-body text-xs text-[#6B7280]">{description}</p> : null}
            <div className="mt-4 min-w-0">{children}</div>
        </div>
    );
}

/**
 * Same Crafted-for-you slot cards on Meal Plan Summary and Cook It Yourself.
 *
 * @param {object} props
 * @param {Array<{ id: string; title: string; category: string; imageUrl?: string; macros?: object; safetyAlerts: object[]; sickleCellHighlights: string[]; hasG6pdTrigger: boolean; detailView?: object }>} props.meals
 * @param {(meal: object) => void} [props.onOpenMeal]
 * @param {boolean} [props.fullDetails] Kept for callers; both paths use ProtocolMealSlotCard.
 */
export function DayMealsTabPanel({ meals, onOpenMeal, fullDetails: _fullDetails = false }) {
    if (meals.length === 0) {
        return <p className="font-body text-sm text-[#555555]">No meals assigned for this day.</p>;
    }

    /** @type {Array<{ title: string; meals: typeof meals }>} */
    const slots = [];

    for (const row of PLAN_MACRO_CATEGORY_ROWS) {
        const categoryMeals = meals.filter((meal) => meal.category === row.label);
        if (categoryMeals.length === 0) {
            continue;
        }

        slots.push({ title: row.label, meals: categoryMeals });
    }

    if (slots.length === 0) {
        return <p className="font-body text-sm text-[#555555]">No meals assigned for this day.</p>;
    }

    return (
        <div className="flex flex-col gap-4">
            {slots.map((slot) => (
                <ProtocolMealSlotCard
                    key={slot.title}
                    title={slot.title}
                    selectedMeals={slot.meals}
                    multiSelect={slot.meals.length > 1}
                    onViewDetails={onOpenMeal}
                />
            ))}
        </div>
    );
}

/**
 * @param {object} props
 * @param {Partial<Record<string, unknown[]>>} props.categories
 * @param {string} [props.dayLabel]
 * @param {string} [props.planCategoryLabel]
 * @param {string | null | undefined} [props.craftKey] Reserved for callers; day targets are not shown.
 * @param {number} [props.planTierCalories] Reserved for callers; day targets are not shown.
 * @param {Record<string, unknown> | null | undefined} [props.nutritionPlan]
 */
export function DayMacronutrientsTabPanel({
    categories,
    dayLabel = 'Day',
    planCategoryLabel = '',
    craftKey: _craftKey = 'full',
    planTierCalories: _planTierCalories = 0,
    nutritionPlan = null,
}) {
    const activeDayTotals = useMemo(() => sumActiveDayMacros(categories), [categories]);

    return (
        <PlanMacroSummaryPanel
            activeDayTotals={activeDayTotals}
            categories={categories}
            dayLabel={dayLabel}
            planCategoryLabel={planCategoryLabel}
            nutritionPlan={nutritionPlan}
        />
    );
}

function MicronutrientRdiTable({ rows }) {
    if (rows.length === 0) {
        return (
            <p className="font-body text-sm text-[#555555]">No micronutrient data available for this day.</p>
        );
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
        <div className="space-y-3">
            <div
            role="region"
            aria-label="Micronutrients with percent RDI"
            className="overflow-x-auto rounded-[12px] border border-gray-200 bg-white"
        >
            <table className="w-full min-w-0 border-collapse text-left text-sm">
                <thead>
                    <tr className="border-b border-gray-200 bg-[#F8F9F6]">
                        <th className="px-2 py-2 font-montserrat text-[10px] font-bold uppercase tracking-[0.1em] text-[#374151] sm:px-3 sm:py-2.5 sm:text-xs sm:tracking-[0.14em]">
                            Nutrient
                        </th>
                        <th className="px-2 py-2 text-right font-montserrat text-[10px] font-bold uppercase tracking-[0.1em] text-[#374151] sm:px-3 sm:py-2.5 sm:text-xs sm:tracking-[0.14em]">
                            Full day
                        </th>
                        <th className="px-2 py-2 text-right font-montserrat text-[10px] font-bold uppercase tracking-[0.1em] text-[#374151] sm:px-3 sm:py-2.5 sm:text-xs sm:tracking-[0.14em]">
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
                                    className="px-2 py-2 font-montserrat text-[10px] font-bold uppercase tracking-[0.1em] text-[#5A6B44] sm:px-3 sm:text-xs sm:tracking-[0.12em]"
                                >
                                    {section.title}
                                </td>
                            </tr>
                            {section.rows.map((row) => (
                                <tr key={`${section.title}-${row.label}`} className="border-b border-gray-100 last:border-b-0">
                                    <td className="max-w-[10.5rem] px-2 py-2 font-montserrat text-xs font-medium leading-snug text-[#374151] sm:max-w-none sm:px-3 sm:py-2.5 sm:text-sm">
                                        {row.label}
                                        {BEST_EFFORT_NUTRIENT_LABELS.has(row.label) ? (
                                            <span className="ml-1 font-body text-[10px] font-normal text-[#555555] sm:ml-1.5 sm:text-xs">
                                                (best effort)
                                            </span>
                                        ) : null}
                                    </td>
                                    <td className="whitespace-nowrap px-2 py-2 text-right font-montserrat text-xs font-bold tabular-nums text-[#1F2937] sm:px-3 sm:py-2.5 sm:text-sm">
                                        {row.formattedTotal}
                                    </td>
                                    <td
                                        className={[
                                            'whitespace-nowrap px-2 py-2 text-right font-montserrat text-xs font-bold tabular-nums sm:px-3 sm:py-2.5 sm:text-sm',
                                            row.rdiPercent != null
                                              && row.rdiPercent < FLOOR_RDI_TARGET_PERCENT
                                              && !row.label.includes('Sugar')
                                              && !row.label.includes('Sodium')
                                                ? 'text-[#C44F5D]'
                                                : row.rdiPercent != null && row.rdiPercent >= 20
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
        </div>
    );
}

/**
 * @param {object} props
 * @param {Partial<Record<string, unknown[]>>} props.categories
 * @param {string} [props.dayLabel]
 * @param {number} [props.planTierCalories]
 * @param {string | null} [props.sex]
 * @param {string | null} [props.activityLevel]
 * @param {number | null} [props.dailyCalories]
 */
export function DayMicronutrientsTabPanel({
    categories,
    dayLabel = 'Day',
    planTierCalories = 0,
    sex = null,
    activityLevel = null,
    dailyCalories = null,
}) {
    const micronutrientRows = useMemo(() => {
        const calories = Number(dailyCalories ?? planTierCalories);
        const targets = calculateMicronutrientTargets({
            sex: sex ?? 'female',
            activityLevel: activityLevel ?? 'sedentary',
            dailyCalories: Number.isFinite(calories) && calories > 0 ? calories : null,
        });

        return aggregateDayMicronutrientRows(categories, targets);
    }, [activityLevel, categories, dailyCalories, planTierCalories, sex]);

    return (
        <SummarySection
            title="Micronutrients"
            description={`Full-day totals and % of daily reference intake for ${dayLabel.toLowerCase()}. Vitamin D is shown as best effort — it is difficult to reach 100% from food alone.`}
        >
            <CaloricThresholdNotice planTierCalories={planTierCalories} />
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
 * Tabbed macronutrients + micronutrients panel for a single day (consultation or admin preview).
 *
 * @param {object} props
 * @param {Partial<Record<string, unknown[]>>} props.categories
 * @param {string} [props.dayLabel]
 * @param {string} [props.planCategoryLabel]
 * @param {string | null | undefined} [props.craftKey]
 * @param {number} [props.planTierCalories]
 * @param {string | null} [props.sex]
 * @param {string | null} [props.activityLevel]
 * @param {number | null} [props.dailyCalories]
 * @param {Record<string, unknown> | null | undefined} [props.nutritionPlan]
 * @param {DayMacroMicroTabId} [props.initialTab]
 */
export function DayMacroMicroTabPanel({
    categories,
    dayLabel = 'Day',
    planCategoryLabel = '',
    craftKey = 'full',
    planTierCalories = 0,
    sex = null,
    activityLevel = null,
    dailyCalories = null,
    nutritionPlan = null,
    initialTab = 'macronutrients',
}) {
    const [tab, setTab] = useState(initialTab);

    return (
        <div className="space-y-4">
            <div
                className="flex w-full flex-wrap items-center gap-2"
                role="tablist"
                aria-label={`${dayLabel} nutrition`}
            >
                {DAY_MACRO_MICRO_TABS.map((item) => (
                    <Button
                        key={item.id}
                        type="button"
                        role="tab"
                        aria-selected={tab === item.id}
                        label={item.label}
                        variant="ghost"
                        size="sm"
                        onClick={() => setTab(item.id)}
                        className={['shrink-0', tab === item.id ? 'bg-[#5A6B44]/10' : ''].join(' ').trim()}
                    />
                ))}
            </div>

            {tab === 'macronutrients' ? (
                <DayMacronutrientsTabPanel
                    categories={categories}
                    dayLabel={dayLabel}
                    planCategoryLabel={planCategoryLabel}
                    craftKey={craftKey}
                    planTierCalories={planTierCalories}
                    nutritionPlan={nutritionPlan}
                />
            ) : (
                <DayMicronutrientsTabPanel
                    categories={categories}
                    dayLabel={dayLabel}
                    planTierCalories={planTierCalories}
                    sex={sex}
                    activityLevel={activityLevel}
                    dailyCalories={dailyCalories}
                />
            )}
        </div>
    );
}

/**
 * @param {object} props
 * @param {DaySummaryTabId} props.tab
 * @param {Partial<Record<string, unknown[]>>} props.categories
 * @param {string} [props.dayLabel]
 * @param {string} [props.planCategoryLabel]
 * @param {number} [props.planTierCalories]
 * @param {string | null | undefined} [props.craftKey]
 * @param {string | null} [props.sex]
 * @param {string | null} [props.activityLevel]
 * @param {number | null} [props.dailyCalories]
 * @param {Record<string, unknown> | null | undefined} [props.nutritionPlan]
 * @param {(meal: object) => void} [props.onOpenMeal]
 * @param {boolean} [props.fullMealDetails]
 */
export default function DayNutritionalSummaryPanel({
    tab,
    categories,
    dayLabel = 'Day',
    planCategoryLabel = '',
    planTierCalories = 0,
    craftKey = 'full',
    sex = null,
    activityLevel = null,
    dailyCalories = null,
    nutritionPlan = null,
    onOpenMeal,
    fullMealDetails = false,
}) {
    const meals = useMemo(() => listDayMealsWithHighlights(categories), [categories]);

    switch (tab) {
        case 'meals':
            return (
                <DayMealsTabPanel meals={meals} onOpenMeal={onOpenMeal} fullDetails={fullMealDetails} />
            );
        case 'macronutrients':
            return (
                <DayMacronutrientsTabPanel
                    categories={categories}
                    dayLabel={dayLabel}
                    planCategoryLabel={planCategoryLabel}
                    craftKey={craftKey}
                    planTierCalories={planTierCalories}
                    nutritionPlan={nutritionPlan}
                />
            );
        case 'micronutrients':
            return (
                <DayMicronutrientsTabPanel
                    categories={categories}
                    dayLabel={dayLabel}
                    planTierCalories={planTierCalories}
                    sex={sex}
                    activityLevel={activityLevel}
                    dailyCalories={dailyCalories}
                />
            );
        case 'allergies':
            return <DayAllergySafetyTabPanel categories={categories} />;
        case 'sickle':
            return <DaySickleCellTabPanel categories={categories} />;
        default:
            return null;
    }
}
