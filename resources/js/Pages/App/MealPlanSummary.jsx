import { useCallback, useEffect, useLayoutEffect, useMemo, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import Button from '../../Components/Atoms/Button.jsx';
import OnboardingOptionButton from '../../Components/Atoms/Button/OnboardingOptionButton.jsx';
import DayNutritionalSummaryPanel, {
    DAY_SUMMARY_TABS,
} from '../../Components/Consultation/DayNutritionalSummaryPanel.jsx';
import { PLAN_MACRO_CATEGORY_ROWS } from '../../Components/Consultation/ChooseYourMeals.jsx';
import MealDetailModalPortal from '../../Components/Molecules/MealDetailModalPortal.jsx';
import CustomerAppBottomNav from '../../Components/Molecules/Customer/CustomerAppBottomNav.jsx';
import CustomerInertiaShell from '../../Layouts/CustomerInertiaShell.jsx';
import { saveSummaryCraftPlanAndNavigateToEdit } from '../../consultation/consultationDraft.js';
import { resolveInertiaLayoutChild } from '../../lib/resolveInertiaLayoutChild.js';
import { useMealDetailModal } from '../../meal-library/useMealDetailModal.js';

const PAGE_BG = 'bg-[#F8F9F6]';

/** @typedef {'days' | 'topics' | 'content'} SummaryStep */
/** @typedef {'meals' | 'macronutrients' | 'micronutrients' | 'allergies' | 'sickle'} DaySummaryTabId */

const slideTransition = { type: 'spring', stiffness: 260, damping: 30, mass: 0.85 };

/**
 * @typedef {{
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
 * }} MealPlanSummaryCraftPlan
 */

/**
 * @param {object} props
 * @param {string} [props.customerName]
 * @param {MealPlanSummaryCraftPlan} [props.craftPlan]
 * @param {string} [props.consultationUrl]
 * @param {string} [props.consultationEditUrl]
 * @param {string} [props.homeUrl]
 * @param {string} [props.fulfillmentUrl]
 * @param {string | null} [props.sex]
 * @param {string | null} [props.activityLevel]
 * @param {number | null} [props.dailyCalorieTarget]
 * @param {boolean} [props.enableMealDetailModal]
 * @param {DaySummaryTabId} [props.initialContentTab] Storybook / deep-link: starting topic tab.
 * @param {SummaryStep} [props.initialMobileStep] Storybook / deep-link: starting screen (legacy prop name).
 * @param {SummaryStep} [props.initialStep] Storybook / deep-link: starting screen.
 */
export function MealPlanSummaryInner({
    customerName = '',
    craftPlan = {},
    consultationUrl = '/consultation/crafted-for-you',
    consultationEditUrl = '',
    homeUrl = '/app',
    profileUrl = '/app/profile',
    shopUrl = '/app/shop',
    mealPlanSummaryUrl = '/app/meal-plan',
    fulfillmentUrl: _fulfillmentUrl = '/checkout',
    sex = null,
    activityLevel = null,
    dailyCalorieTarget = null,
    enableMealDetailModal = true,
    initialContentTab = 'meals',
    initialMobileStep,
    initialStep,
}) {
    const days = craftPlan.days ?? [];
    const hasMultipleDays = days.length > 1;
    const startingStep = initialStep ?? initialMobileStep ?? (hasMultipleDays ? 'days' : 'topics');

    const [activeDay, setActiveDay] = useState(() => days[0]?.dayNumber ?? 1);
    /** @type {[DaySummaryTabId, import('react').Dispatch<import('react').SetStateAction<DaySummaryTabId>>]} */
    const [contentTab, setContentTab] = useState(initialContentTab);
    /** @type {[SummaryStep, import('react').Dispatch<import('react').SetStateAction<SummaryStep>>]} */
    const [step, setStep] = useState(startingStep);

    const { mealDetailModal, detailLoading, openMealDetail, closeMealDetail } = useMealDetailModal();

    useEffect(() => {
        if (!enableMealDetailModal || typeof router.on !== 'function') {
            return undefined;
        }

        const removeListener = router.on('before', () => {
            closeMealDetail();
        });

        return () => {
            removeListener?.();
            closeMealDetail();
        };
    }, [closeMealDetail, enableMealDetailModal]);

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
    const activeDayLabel = activeDayData?.dateLabel
        ? `${activeDayData.label} ${activeDayData.dateLabel}`
        : (activeDayData?.label ?? 'Day');
    const activeTabLabel = DAY_SUMMARY_TABS.find((tab) => tab.id === contentTab)?.label ?? 'Meals';

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

    const handleNext = useCallback(() => {
        const base = String(homeUrl || '/app').split('?')[0] || '/app';
        if (typeof router.visit === 'function') {
            router.visit(`${base}?from=summary`);
        }
    }, [homeUrl]);

    const handleSelectDay = useCallback((dayNumber) => {
        setActiveDay(dayNumber);
        setStep('topics');
    }, []);

    const handleSelectTopic = useCallback((/** @type {DaySummaryTabId} */ tabId) => {
        setContentTab(tabId);
        setStep('content');
    }, []);

    useLayoutEffect(() => {
        window.scrollTo(0, 0);
    }, [activeDay, contentTab, step]);

    const pageIntro = (
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
                    <span className="font-semibold text-[#262A22]"> · {craftPlan.planDateRange.label}</span>
                ) : null}
                {days.length > 0 ? ` · ${days.length} delivery ${days.length === 1 ? 'day' : 'days'}` : null}
            </p>
        </div>
    );

    const summaryPanel = (
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
            onOpenMeal={enableMealDetailModal ? openMealDetail : undefined}
            onEditMeals={handleEditSelections}
        />
    );

    const nextFooter = (
        <div className="mt-4 flex flex-wrap justify-center gap-3 border-t border-gray-200 pt-6">
            <Button label="Choose a delivery option" variant="primary" onClick={handleNext} className="px-10" />
        </div>
    );

    const daysScreen = (
        <>
            {pageIntro}
            <p className="mb-4 font-body text-sm text-[#555555]">Choose a delivery day to review.</p>
            <div className="mx-auto flex w-full max-w-xl flex-col gap-3" role="list" aria-label="Plan days">
                {days.map((day) => {
                    const label = day.dateLabel ? `${day.label} ${day.dateLabel}` : day.label;

                    return (
                        <OnboardingOptionButton
                            key={day.dayNumber}
                            label={label}
                            align="center"
                            selected={day.dayNumber === activeDay}
                            onSelect={() => handleSelectDay(day.dayNumber)}
                        />
                    );
                })}
            </div>
            {nextFooter}
        </>
    );

    const topicsScreen = (
        <>
            {hasMultipleDays ? (
                <div className="mb-5 min-w-0">
                    <button
                        type="button"
                        onClick={() => setStep('days')}
                        className="inline-flex items-center gap-1 font-montserrat text-sm font-semibold text-[#5A6B44] hover:underline"
                    >
                        ← Back to days
                    </button>
                    <h1 className="mt-2 font-montserrat text-2xl font-bold tracking-tight text-[#262A22] sm:text-3xl">
                        {activeDayLabel}
                    </h1>
                    <p className="mt-2 font-body text-sm text-[#555555]">What would you like to review?</p>
                </div>
            ) : (
                <>
                    {pageIntro}
                    <p className="mb-4 font-body text-sm text-[#555555]">
                        What would you like to review for {activeDayLabel}?
                    </p>
                </>
            )}

            <div className="mx-auto flex w-full max-w-xl flex-col gap-3" role="list" aria-label="Day content topics">
                {DAY_SUMMARY_TABS.map((tab) => (
                    <OnboardingOptionButton
                        key={tab.id}
                        label={tab.label}
                        align="center"
                        onSelect={() => handleSelectTopic(/** @type {DaySummaryTabId} */ (tab.id))}
                    />
                ))}
            </div>

            {!hasMultipleDays ? nextFooter : null}
        </>
    );

    const contentScreen = (
        <>
            <div className="mb-5 min-w-0">
                <button
                    type="button"
                    onClick={() => setStep('topics')}
                    className="inline-flex items-center gap-1 font-montserrat text-sm font-semibold text-[#5A6B44] hover:underline"
                >
                    ← Back to topics
                </button>
                <p className="mt-2 font-montserrat text-xs font-bold uppercase tracking-wide text-[#555555]">
                    {activeDayLabel}
                </p>
                <h1 className="mt-1 font-montserrat text-2xl font-bold tracking-tight text-[#262A22] sm:text-3xl">
                    {activeTabLabel}
                </h1>
            </div>
            <div className="pb-12">{summaryPanel}</div>
        </>
    );

    let screen = topicsScreen;
    if (step === 'days' && hasMultipleDays) {
        screen = daysScreen;
    } else if (step === 'content') {
        screen = contentScreen;
    }

    return (
        <CustomerInertiaShell
            customerName={customerName}
            footerNav={
                <CustomerAppBottomNav
                    activeTab="meals"
                    homeUrl={homeUrl}
                    profileUrl={profileUrl}
                    shopUrl={shopUrl}
                    mealsUrl={mealPlanSummaryUrl}
                />
            }
        >
            <div className={`min-h-full font-body ${PAGE_BG}`}>
                <div className="mx-auto w-full max-w-5xl px-3 py-6 sm:px-6 lg:px-8">
                    <AnimatePresence mode="wait" initial={false}>
                        <motion.div
                            key={`${step}-${activeDay}-${contentTab}`}
                            initial={{ x: 24, opacity: 0 }}
                            animate={{ x: 0, opacity: 1 }}
                            exit={{ x: -24, opacity: 0 }}
                            transition={slideTransition}
                        >
                            {screen}
                        </motion.div>
                    </AnimatePresence>
                </div>
            </div>

            {enableMealDetailModal ? (
                <MealDetailModalPortal
                    mealDetailModal={mealDetailModal}
                    loading={detailLoading}
                    onClose={closeMealDetail}
                />
            ) : null}
        </CustomerInertiaShell>
    );
}

/**
 * @param {object} props
 * @param {string} [props.customerName]
 * @param {MealPlanSummaryCraftPlan} [props.craftPlan]
 * @param {string} [props.consultationUrl]
 * @param {string} [props.consultationEditUrl]
 * @param {string} [props.homeUrl]
 * @param {string} [props.profileUrl]
 * @param {string} [props.shopUrl]
 * @param {string} [props.mealPlanSummaryUrl]
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
    profileUrl = '/app/profile',
    shopUrl = '/app/shop',
    mealPlanSummaryUrl = '/app/meal-plan',
    fulfillmentUrl = '/checkout',
    sex = null,
    activityLevel = null,
    dailyCalorieTarget = null,
}) {
    return (
        <MealPlanSummaryInner
            customerName={customerName}
            craftPlan={craftPlan}
            consultationUrl={consultationUrl}
            consultationEditUrl={consultationEditUrl}
            homeUrl={homeUrl}
            profileUrl={profileUrl}
            shopUrl={shopUrl}
            mealPlanSummaryUrl={mealPlanSummaryUrl}
            fulfillmentUrl={fulfillmentUrl}
            sex={sex}
            activityLevel={activityLevel}
            dailyCalorieTarget={dailyCalorieTarget}
        />
    );
}

MealPlanSummary.layout = (pageOrProps) => resolveInertiaLayoutChild(pageOrProps);
