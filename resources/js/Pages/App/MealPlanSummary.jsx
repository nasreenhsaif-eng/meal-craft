import { createPortal } from 'react-dom';
import { useCallback, useLayoutEffect, useMemo, useState } from 'react';
import { Link } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import PillButton from '../../Components/Atoms/Button/Button.jsx';
import Button from '../../Components/Atoms/Button.jsx';
import DayNutritionalSummaryPanel from '../../Components/Consultation/DayNutritionalSummaryPanel.jsx';
import CustomerAppHeaderActions from '../../Components/Molecules/Customer/CustomerAppHeaderActions.jsx';
import {
    PLAN_MACRO_CATEGORY_ROWS,
    sumActiveDayMacros,
} from '../../Components/Consultation/ChooseYourMeals.jsx';
import MealDetailView from '../../Components/Molecules/MealDetailView/MealDetailView';
import CustomerInertiaShell from '../../Layouts/CustomerInertiaShell.jsx';
import { resolveInertiaLayoutChild } from '../../lib/resolveInertiaLayoutChild.js';

const PAGE_BG = 'bg-[#F8F9F6]';

/**
 * @param {number} value
 */
function formatMacroValue(value) {
    if (!Number.isFinite(value)) {
        return '0';
    }

    return Number.isInteger(value) ? String(value) : value.toFixed(1);
}

/**
 * @param {object} props
 * @param {string} [props.customerName]
 * @param {{
 *   craftKey?: string;
 *   craftTitle?: string;
 *   weekDuration?: number;
 *   planTierCalories?: number;
 *   submittedAt?: string | null;
 *   days?: Array<{
 *     dayNumber: number;
 *     label: string;
 *     includeSoup?: boolean;
 *     categories?: Record<string, Array<{ id: string; title?: string; detailView?: object }>>;
 *   }>;
 * }} props.craftPlan
 * @param {string} [props.consultationUrl]
 * @param {string} [props.homeUrl]
 */
export default function MealPlanSummary({
    customerName = '',
    craftPlan = {},
    consultationUrl = '/consultation/crafted-for-you',
    homeUrl = '/app',
}) {
    const days = craftPlan.days ?? [];
    const [activeDay, setActiveDay] = useState(() => days[0]?.dayNumber ?? 1);
    const [contentTab, setContentTab] = useState(/** @type {'meals' | 'nutrition'} */ ('meals'));
    const [mealDetailModal, setMealDetailModal] = useState(
        /** @type {{ title: string; detailView: object } | null} */ (null),
    );

    const activeDayData = useMemo(
        () => days.find((day) => day.dayNumber === activeDay) ?? days[0] ?? null,
        [activeDay, days],
    );

    const activeDayCategories = useMemo(() => {
        if (!activeDayData?.categories) {
            return {};
        }

        /** @type {Record<string, unknown[]>} */
        const out = {};

        for (const row of PLAN_MACRO_CATEGORY_ROWS) {
            const items = activeDayData.categories?.[row.key] ?? [];
            if (row.optional && items.length === 0) {
                continue;
            }

            out[row.key] = items;
        }

        return out;
    }, [activeDayData]);

    const weekOverview = useMemo(
        () =>
            days.map((day) => {
                /** @type {Record<string, unknown[]>} */
                const categories = {};

                for (const row of PLAN_MACRO_CATEGORY_ROWS) {
                    const items = day.categories?.[row.key] ?? [];
                    if (row.optional && items.length === 0) {
                        continue;
                    }

                    categories[row.key] = items;
                }

                const totals = sumActiveDayMacros(categories);

                return {
                    dayNumber: day.dayNumber,
                    label: day.label,
                    totals,
                };
            }),
        [days],
    );

    const mealListSections = useMemo(() => {
        if (!activeDayData?.categories) {
            return [];
        }

        return PLAN_MACRO_CATEGORY_ROWS.map((row) => {
            const meals = activeDayData.categories?.[row.key] ?? [];
            if (meals.length === 0) {
                return null;
            }

            return {
                key: row.key,
                label: row.label,
                meals,
            };
        }).filter(Boolean);
    }, [activeDayData]);

    const planCategoryLabel = `${craftPlan.craftTitle ?? 'Craft'} · ${craftPlan.planTierCalories ?? ''} kcal`.trim();

    const openMealDetail = useCallback((meal) => {
        if (!meal?.detailView) {
            return;
        }

        setMealDetailModal({
            title: meal.title ?? 'Meal details',
            detailView: meal.detailView,
        });
    }, []);

    useLayoutEffect(() => {
        window.scrollTo(0, 0);
    }, [activeDay, contentTab]);

    return (
        <CustomerInertiaShell customerName={customerName} headerActions={<CustomerAppHeaderActions />}>
            <div className={`min-h-full font-body ${PAGE_BG}`}>
                <div className="mx-auto w-full max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
                    <div className="mb-5 min-w-0">
                        <Link
                            href={homeUrl}
                            className="inline-flex items-center gap-1 font-montserrat text-sm font-semibold text-[#5A6B44] hover:underline"
                        >
                            ← Back to home
                        </Link>
                        <h1 className="mt-2 font-montserrat text-2xl font-bold tracking-tight text-[#262A22] sm:text-3xl">
                            Your meal plan
                        </h1>
                        <p className="mt-2 font-body text-sm text-[#555555] sm:text-base">
                            {planCategoryLabel}
                            {days.length > 0 ? ` · ${days.length} delivery ${days.length === 1 ? 'day' : 'days'}` : null}
                        </p>
                    </div>

                    {weekOverview.length > 1 ? (
                        <div className="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            {weekOverview.map((day) => {
                                const selected = day.dayNumber === activeDay;

                                return (
                                    <button
                                        key={day.dayNumber}
                                        type="button"
                                        onClick={() => setActiveDay(day.dayNumber)}
                                        className={[
                                            'rounded-[12px] border px-4 py-3 text-left transition',
                                            selected
                                                ? 'border-[#5A6B44] bg-white shadow-sm'
                                                : 'border-gray-200 bg-white/80 hover:border-[#5A6B44]/40',
                                        ].join(' ')}
                                    >
                                        <p className="font-montserrat text-sm font-bold text-[#262A22]">{day.label}</p>
                                        <p className="mt-1 font-body text-xs text-[#555555]">
                                            {formatMacroValue(day.totals.calories)} kcal · P{' '}
                                            {formatMacroValue(day.totals.protein)}g · C{' '}
                                            {formatMacroValue(day.totals.carbs)}g · F{' '}
                                            {formatMacroValue(day.totals.fat)}g
                                        </p>
                                    </button>
                                );
                            })}
                        </div>
                    ) : null}

                    <div className="mb-6 overflow-visible rounded-[12px] border border-gray-200 bg-white p-3 sm:p-4">
                        <div
                            className="flex w-full flex-wrap items-center justify-center gap-2 sm:justify-start"
                            role="tablist"
                            aria-label="Day content"
                        >
                            <PillButton
                                type="button"
                                role="tab"
                                aria-selected={contentTab === 'meals'}
                                label="Meals"
                                variant={contentTab === 'meals' ? 'primary' : 'tab'}
                                size="sm"
                                onClick={() => setContentTab('meals')}
                                className="shrink-0"
                            />
                            <PillButton
                                type="button"
                                role="tab"
                                aria-selected={contentTab === 'nutrition'}
                                label="Nutritional summary"
                                variant={contentTab === 'nutrition' ? 'primary' : 'tab'}
                                size="sm"
                                onClick={() => setContentTab('nutrition')}
                                className="shrink-0"
                            />
                        </div>
                    </div>

                    <AnimatePresence mode="wait" initial={false}>
                        <motion.div
                            key={`${activeDayData?.dayNumber ?? 'empty'}-${contentTab}`}
                            initial={{ x: 24, opacity: 0 }}
                            animate={{ x: 0, opacity: 1 }}
                            exit={{ x: -24, opacity: 0 }}
                            transition={{ type: 'spring', stiffness: 260, damping: 30, mass: 0.85 }}
                            className="space-y-8 pb-12"
                        >
                            {contentTab === 'nutrition' ? (
                                <DayNutritionalSummaryPanel
                                    categories={activeDayCategories}
                                    dayLabel={activeDayData?.label ?? 'Day'}
                                    planCategoryLabel={planCategoryLabel}
                                />
                            ) : (
                                <>
                                    {mealListSections.map((section) => (
                                        <section
                                            key={section.key}
                                            className="rounded-[12px] border border-gray-200 bg-white px-4 py-4 sm:px-5"
                                        >
                                            <h2 className="font-montserrat text-sm font-bold uppercase tracking-[0.12em] text-[#5A6B44]">
                                                {section.label}
                                            </h2>
                                            <ul className="mt-3 space-y-2">
                                                {section.meals.map((meal) => (
                                                    <li key={meal.id}>
                                                        {meal.detailView ? (
                                                            <button
                                                                type="button"
                                                                onClick={() => openMealDetail(meal)}
                                                                className="w-full rounded-[8px] px-2 py-2 text-left font-body text-sm text-[#262A22] hover:bg-[#F8F9F6]"
                                                            >
                                                                {meal.title}
                                                            </button>
                                                        ) : (
                                                            <p className="px-2 py-2 font-body text-sm text-[#262A22]">
                                                                {meal.title}
                                                            </p>
                                                        )}
                                                    </li>
                                                ))}
                                            </ul>
                                        </section>
                                    ))}
                                </>
                            )}
                        </motion.div>
                    </AnimatePresence>

                    <div className="mt-4 flex flex-wrap gap-3 border-t border-gray-200 pt-6">
                        <Button
                            label="Edit selections"
                            variant="outline"
                            onClick={() => window.location.assign(consultationUrl)}
                            className="px-8"
                        />
                        <Button
                            label="Done"
                            variant="primary"
                            onClick={() => window.location.assign(homeUrl)}
                            className="px-10"
                        />
                    </div>
                </div>
            </div>

            {mealDetailModal
                ? createPortal(
                      <div className="fixed inset-0 z-[120] flex items-end justify-center p-0 sm:items-center sm:p-6">
                          <button
                              type="button"
                              className="absolute inset-0 bg-black/40"
                              aria-label="Close meal details"
                              onClick={() => setMealDetailModal(null)}
                          />
                          <div className="relative max-h-[92dvh] w-full max-w-3xl overflow-y-auto rounded-t-[16px] bg-white p-5 shadow-2xl sm:rounded-[16px] sm:p-6">
                              <div className="mb-4 flex items-start justify-between gap-3">
                                  <h2 className="font-montserrat text-lg font-bold text-[#262A22]">
                                      {mealDetailModal.title}
                                  </h2>
                                  <button
                                      type="button"
                                      className="font-montserrat text-sm font-bold text-[#5A6B44]"
                                      onClick={() => setMealDetailModal(null)}
                                  >
                                      Close
                                  </button>
                              </div>
                              <MealDetailView meal={mealDetailModal.detailView} hideImage={false} />
                          </div>
                      </div>,
                      document.body,
                  )
                : null}
        </CustomerInertiaShell>
    );
}

MealPlanSummary.layout = (pageOrProps) => resolveInertiaLayoutChild(pageOrProps);
