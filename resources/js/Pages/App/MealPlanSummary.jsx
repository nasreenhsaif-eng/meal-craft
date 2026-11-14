import { useCallback, useEffect, useLayoutEffect, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
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

const PAGE_BG = 'bg-white';

/** @typedef {'days' | 'day'} SummaryStep */
/** @typedef {'meals' | 'macronutrients' | 'micronutrients' | 'allergies' | 'sickle'} DaySummaryTabId */

const slideTransition = { type: 'spring', stiffness: 260, damping: 30, mass: 0.85 };

/**
 * @typedef {{
 *   craftKey?: string;
 *   craftTitle?: string;
 *   fulfillmentChoice?: 'diy' | 'partner_kitchen' | null;
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
 * Map legacy Storybook / deep-link step names onto the two-step flow.
 *
 * @param {string | undefined} raw
 * @param {boolean} hasMultipleDays
 * @returns {SummaryStep}
 */
function resolveStartingStep(raw, hasMultipleDays) {
    if (raw === 'days' && hasMultipleDays) {
        return 'days';
    }

    if (raw === 'day' || raw === 'content' || raw === 'topics') {
        return 'day';
    }

    return hasMultipleDays ? 'days' : 'day';
}

/**
 * @param {object} props
 * @param {string} [props.customerName]
 * @param {MealPlanSummaryCraftPlan} [props.craftPlan]
 * @param {string} [props.consultationUrl]
 * @param {string} [props.consultationEditUrl]
 * @param {string} [props.homeUrl]
 * @param {string | null} [props.sex]
 * @param {string | null} [props.activityLevel]
 * @param {number | null} [props.dailyCalorieTarget]
 * @param {boolean} [props.enableMealDetailModal]
 * @param {DaySummaryTabId} [props.initialContentTab] Storybook / deep-link: starting topic tab.
 * @param {string} [props.initialMobileStep] Storybook / deep-link: starting screen (legacy prop name).
 * @param {string} [props.initialStep] Storybook / deep-link: starting screen.
 * @param {boolean} [props.preview] Marketing embed — same UI, no navigation or page scroll.
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
    cartUrl = '/app/cart',
    sex = null,
    activityLevel = null,
    dailyCalorieTarget = null,
    enableMealDetailModal = true,
    initialContentTab = 'meals',
    initialMobileStep,
    initialStep,
    preview = false,
}) {
    const days = craftPlan.days ?? [];
    const hasMultipleDays = days.length > 1;
    const isDiyFulfillment = craftPlan.fulfillmentChoice === 'diy';
    const startingStep = resolveStartingStep(initialStep ?? initialMobileStep, hasMultipleDays);

    const [activeDay, setActiveDay] = useState(() => days[0]?.dayNumber ?? 1);
    /** @type {[DaySummaryTabId, import('react').Dispatch<import('react').SetStateAction<DaySummaryTabId>>]} */
    const [contentTab, setContentTab] = useState(initialContentTab);
    /** @type {[SummaryStep, import('react').Dispatch<import('react').SetStateAction<SummaryStep>>]} */
    const [step, setStep] = useState(startingStep);

    const mealModalEnabled = enableMealDetailModal && !preview;
    const { mealDetailModal, detailLoading, openMealDetail, closeMealDetail } = useMealDetailModal();

    useEffect(() => {
        if (!mealModalEnabled || typeof router.on !== 'function') {
            return undefined;
        }

        const removeListener = router.on('before', () => {
            closeMealDetail();
        });

        return () => {
            removeListener?.();
            closeMealDetail();
        };
    }, [closeMealDetail, mealModalEnabled]);

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

    const handleEditSelections = useCallback(() => {
        if (preview) {
            return;
        }

        const editUrl =
            typeof consultationEditUrl === 'string' && consultationEditUrl.trim() !== ''
                ? consultationEditUrl
                : null;

        if (editUrl) {
            window.location.assign(editUrl);
            return;
        }

        saveSummaryCraftPlanAndNavigateToEdit(consultationUrl, craftPlan);
    }, [consultationEditUrl, consultationUrl, craftPlan, preview]);

    const handleSelectDay = useCallback((dayNumber) => {
        setActiveDay(dayNumber);
        setContentTab('meals');
        setStep('day');
    }, []);

    useLayoutEffect(() => {
        if (preview) {
            return;
        }

        window.scrollTo(0, 0);
    }, [activeDay, contentTab, step, preview]);

    const pageIntro = (
        <div className="mb-3 min-w-0">
            <h1 className="text-center font-montserrat text-2xl font-semibold leading-tight text-[#262A22] sm:text-3xl">
                Your week, crafted.
            </h1>
            <p className="mt-1.5 text-center text-sm leading-snug text-[#555555] sm:text-base">
                Open a day. Every meal, every ingredient, every detail.
            </p>
            <p className="mt-1 text-center font-body text-xs text-[#6B7280] sm:text-sm">
                {planCategoryLabel}
                {craftPlan.planDateRange?.label ? ` · ${craftPlan.planDateRange.label}` : null}
                {days.length > 0 ? ` · ${days.length} delivery ${days.length === 1 ? 'day' : 'days'}` : null}
            </p>
        </div>
    );

    const summaryPanel = (
        <DayNutritionalSummaryPanel
            tab={contentTab}
            categories={activeDayCategories}
            dayLabel={activeDayData?.label ?? 'Day'}
            planCategoryLabel=""
            planTierCalories={craftPlan.planTierCalories ?? 0}
            craftKey={craftPlan.craftKey ?? 'full'}
            sex={sex}
            activityLevel={activityLevel}
            dailyCalories={dailyCalorieTarget ?? craftPlan.planTierCalories ?? 0}
            dietProtocol={craftPlan.dietProtocol ?? null}
            onOpenMeal={mealModalEnabled ? openMealDetail : undefined}
            onEditMeals={preview ? undefined : handleEditSelections}
            fullMealDetails
        />
    );

    const daysScreen = (
        <>
            {pageIntro}
            <p className="mb-3 text-center font-body text-sm text-[#555555]">Pick a day to open your meals.</p>
            <div className="mx-auto flex w-full max-w-xl flex-col gap-2" role="list" aria-label="Plan days">
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
        </>
    );

    const dayScreen = (
        <>
            <div className="mb-2 min-w-0">
                {hasMultipleDays ? (
                    <>
                        <h1 className="text-center font-montserrat text-2xl font-semibold leading-tight text-[#262A22] sm:text-3xl">
                            {activeDayLabel}
                        </h1>
                        <p className="mt-1.5 text-center text-sm leading-snug text-[#555555]">
                            Every meal, every ingredient, every detail.
                        </p>
                    </>
                ) : (
                    pageIntro
                )}
            </div>

            <div
                className="mb-3 flex gap-1.5 overflow-x-auto pb-0.5 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                role="tablist"
                aria-label="Day information"
            >
                {DAY_SUMMARY_TABS.map((tab) => {
                    const selected = contentTab === tab.id;

                    return (
                        <button
                            key={tab.id}
                            type="button"
                            role="tab"
                            aria-selected={selected}
                            onClick={() => setContentTab(/** @type {DaySummaryTabId} */ (tab.id))}
                            className={[
                                'shrink-0 rounded-full px-2.5 py-1 font-montserrat text-[11px] font-bold tracking-wide transition-colors',
                                selected
                                    ? 'bg-[#5A6B44] text-white'
                                    : 'bg-white text-[#555555] ring-1 ring-gray-200 hover:text-[#262A22]',
                            ].join(' ')}
                        >
                            {tab.label}
                        </button>
                    );
                })}
            </div>

            <div className="pb-6">{summaryPanel}</div>
        </>
    );

    const screen = step === 'days' && hasMultipleDays ? daysScreen : dayScreen;

    return (
        <CustomerInertiaShell
            customerName={customerName}
            cartUrl={cartUrl}
            embedded={preview}
            onBack={
                step === 'day' && hasMultipleDays
                    ? () => setStep('days')
                    : null
            }
            backAriaLabel="Back to days"
            footerNav={
                <CustomerAppBottomNav
                    activeTab="meals"
                    homeUrl={homeUrl}
                    profileUrl={profileUrl}
                    shopUrl={shopUrl}
                    mealsUrl={mealPlanSummaryUrl}
                    navigationDisabled={preview}
                />
            }
        >
            <div className={`min-h-full font-body ${PAGE_BG}`}>
                <div className="mx-auto w-full max-w-5xl py-3 sm:py-4">
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

            {mealModalEnabled ? (
                <MealDetailModalPortal
                    mealDetailModal={mealDetailModal}
                    loading={detailLoading}
                    onClose={closeMealDetail}
                    hideInstructions
                    enableGetRecipe={isDiyFulfillment}
                    inlineIngredients={!isDiyFulfillment}
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
 * @param {string} [props.cartUrl]
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
    cartUrl = '/app/cart',
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
            cartUrl={cartUrl}
            sex={sex}
            activityLevel={activityLevel}
            dailyCalorieTarget={dailyCalorieTarget}
        />
    );
}

MealPlanSummary.layout = (pageOrProps) => resolveInertiaLayoutChild(pageOrProps);
