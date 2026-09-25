import { useCallback, useEffect, useLayoutEffect, useMemo, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import Button from '../../Components/Atoms/Button.jsx';
import DayNutritionalSummaryPanel, {
    DAY_SUMMARY_TABS,
} from '../../Components/Consultation/DayNutritionalSummaryPanel.jsx';
import CustomerAppHeaderActions from '../../Components/Molecules/Customer/CustomerAppHeaderActions.jsx';
import { PLAN_MACRO_CATEGORY_ROWS } from '../../Components/Consultation/ChooseYourMeals.jsx';
import MealDetailModalPortal from '../../Components/Molecules/MealDetailModalPortal.jsx';
import FoodFilterPill from '../../Components/MealSystem/FoodFilterPill.jsx';
import CustomerInertiaShell from '../../Layouts/CustomerInertiaShell.jsx';
import { saveSummaryCraftPlanAndNavigateToEdit } from '../../consultation/consultationDraft.js';
import { resolveInertiaLayoutChild } from '../../lib/resolveInertiaLayoutChild.js';
import { useMealDetailModal } from '../../meal-library/useMealDetailModal.js';

const PAGE_BG = 'bg-[#F8F9F6]';

/**
 * @param {object} props
 * @param {string} [props.customerName]
 * @param {{
 *   craftKey?: string;
 *   craftTitle?: string;
 *   weekDuration?: number;
 *   planTierCalories?: number;
 *   dietProtocol?: string | null;
 *   submittedAt?: string | null;
 *   planDateRange?: { startsOn?: string; endsOn?: string; label?: string };
 *   days?: Array<{
 *     dayNumber: number;
 *     label: string;
 *     dateLabel?: string;
 *     includeSoup?: boolean;
 *     categories?: Record<string, Array<{ id: string; title?: string; detailView?: object }>>;
 *   }>;
 * }} props.craftPlan
 * @param {string} [props.consultationUrl]
 * @param {string} [props.consultationEditUrl]
 * @param {string} [props.homeUrl]
 * @param {string} [props.fulfillmentUrl]
 * @param {string | null} [props.sex]
 * @param {string | null} [props.activityLevel]
 * @param {number | null} [props.dailyCalorieTarget]
 */
export default function MealPlanSummary({
    customerName = '',
    craftPlan = {},
    consultationUrl = '/consultation/crafted-for-you',
    consultationEditUrl = '',
    homeUrl = '/app',
    fulfillmentUrl = '/checkout',
    sex = null,
    activityLevel = null,
    dailyCalorieTarget = null,
}) {
    const days = craftPlan.days ?? [];
    const [activeDay, setActiveDay] = useState(() => days[0]?.dayNumber ?? 1);
    const [contentTab, setContentTab] = useState(/** @type {'meals' | 'macronutrients' | 'micronutrients' | 'allergies' | 'sickle'} */ ('meals'));
    const { mealDetailModal, detailLoading, openMealDetail, closeMealDetail } = useMealDetailModal();

    useEffect(() => {
        const removeListener = router.on('before', () => {
            closeMealDetail();
        });

        return () => {
            removeListener();
            closeMealDetail();
        };
    }, [closeMealDetail]);

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

    const planCategoryLabel = `${craftPlan.craftTitle ?? 'Craft'} · ${craftPlan.planTierCalories ?? ''} kcal`.trim();

    const handleEditSelections = useCallback(() => {
        const editUrl =
            typeof consultationEditUrl === 'string' && consultationEditUrl.trim() !== ''
                ? consultationEditUrl
                : null;

        if (editUrl) {
            window.location.assign(editUrl);
            return;
        }

        saveSummaryCraftPlanAndNavigateToEdit(consultationUrl, craftPlan);
    }, [consultationEditUrl, consultationUrl, craftPlan]);

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
                            {craftPlan.planDateRange?.label ? (
                                <span className="font-semibold text-[#262A22]">
                                    {' '}
                                    · {craftPlan.planDateRange.label}
                                </span>
                            ) : null}
                            {days.length > 0 ? ` · ${days.length} delivery ${days.length === 1 ? 'day' : 'days'}` : null}
                        </p>
                    </div>

                    {days.length > 1 ? (
                        <div
                            className="mb-6 flex flex-wrap gap-2"
                            role="tablist"
                            aria-label="Plan days"
                        >
                            {days.map((day) => (
                                <FoodFilterPill
                                    key={day.dayNumber}
                                    label={day.dateLabel ? `${day.label} ${day.dateLabel}` : day.label}
                                    isActive={day.dayNumber === activeDay}
                                    onClick={() => setActiveDay(day.dayNumber)}
                                />
                            ))}
                        </div>
                    ) : null}

                    <div className="mb-6 overflow-visible rounded-[12px] border border-gray-200 bg-white p-3 sm:p-4">
                        <div
                            className="flex w-full flex-wrap items-center justify-center gap-2 sm:justify-start"
                            role="tablist"
                            aria-label="Day content"
                        >
                            {DAY_SUMMARY_TABS.map((tab) => (
                                <Button
                                    key={tab.id}
                                    type="button"
                                    role="tab"
                                    aria-selected={contentTab === tab.id}
                                    label={tab.label}
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setContentTab(tab.id)}
                                    className={[
                                        'shrink-0',
                                        contentTab === tab.id ? 'bg-[#5A6B44]/10' : '',
                                    ]
                                        .join(' ')
                                        .trim()}
                                />
                            ))}
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
                            <DayNutritionalSummaryPanel
                                tab={contentTab}
                                categories={activeDayCategories}
                                dayLabel={activeDayData?.label ?? 'Day'}
                                planCategoryLabel={planCategoryLabel}
                                planTierCalories={craftPlan.planTierCalories ?? 0}
                                craftKey={craftPlan.craftKey ?? 'full'}
                                sex={sex}
                                activityLevel={activityLevel}
                                dailyCalories={dailyCalorieTarget ?? craftPlan.planTierCalories ?? 0}
                                dietProtocol={craftPlan.dietProtocol ?? null}
                                onOpenMeal={openMealDetail}
                                onEditMeals={handleEditSelections}
                            />
                        </motion.div>
                    </AnimatePresence>

                    <div className="mt-4 flex flex-wrap justify-end gap-3 border-t border-gray-200 pt-6">
                        <Button
                            label="Next"
                            variant="primary"
                            onClick={() => {
                                const base = String(homeUrl || '/app').split('?')[0] || '/app';
                                router.visit(`${base}?from=summary`);
                            }}
                            className="px-10"
                        />
                    </div>
                </div>
            </div>

            <MealDetailModalPortal
                mealDetailModal={mealDetailModal}
                loading={detailLoading}
                onClose={closeMealDetail}
            />
        </CustomerInertiaShell>
    );
}

MealPlanSummary.layout = (pageOrProps) => resolveInertiaLayoutChild(pageOrProps);
