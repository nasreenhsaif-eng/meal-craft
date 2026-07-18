import { createPortal } from 'react-dom';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import adminInertiaLayout from '../../lib/adminInertiaLayout.jsx';
import { resolveUrl } from '../../meal-craft/mealCraftPageProps.js';
import PillButton from '../../Components/Atoms/Button/Button.jsx';
import Button from '../../Components/Atoms/Button.jsx';
import AdminPreviewTierPicker from '../../Components/Admin/AdminPreviewTierPicker.jsx';
import {
    applyDeckSelectionToggle,
    DEFAULT_FULL_CRAFT_MAX_SELECTIONS,
    MealSlotCarousel,
} from '../../Components/Consultation/ChooseYourMeals.jsx';
import {
    applyFixedChoiceToggle,
    countFixedChoiceSelections,
    FIXED_CHOICE_CATEGORY_KEYS,
    FIXED_CHOICE_MAX_COUNT,
} from '../../consultation/fixedChoiceSelection.js';
import { DayMacroMicroTabPanel } from '../../Components/Consultation/DayNutritionalSummaryPanel.jsx';
import MealDetailView from '../../Components/Molecules/MealDetailView/MealDetailView';
import MealPlanMealEditSheet from '../../Components/MealPlan/MealPlanMealEditSheet.jsx';
import { SCHEDULER_SLOT_SECTIONS } from '../../meal-library/mealSearch.ts';
import { updateMealInPlanDays } from './mealPlanMealEdit.js';
import { useMealDetailModal } from '../../meal-library/useMealDetailModal.js';

const PAGE_BG = 'bg-[#F8F9F6]';

const DEFAULT_PLAN_TIERS = [1000, 1200, 1500, 1800, 2000];

/**
 * @param {number} mealPlanId
 * @param {number[]} planTiers
 * @param {number} fallback
 */
function readStoredMealPlanTier(mealPlanId, planTiers, fallback) {
    if (typeof window === 'undefined' || mealPlanId <= 0) {
        return fallback;
    }

    try {
        const raw = sessionStorage.getItem(`mc-admin-meal-plan-tier-${mealPlanId}`);
        const value = raw ? Number.parseInt(raw, 10) : Number.NaN;

        if (Number.isFinite(value) && planTiers.includes(value)) {
            return value;
        }
    } catch {
        // ignore storage errors
    }

    return fallback;
}

/**
 * @param {string} tierPreviewUrl
 * @param {number} planTier
 * @param {Record<number, Record<string, string[]>>} [daySelections]
 */
async function fetchTierPreviewDays(tierPreviewUrl, planTier, daySelections = {}) {
    const url = new URL(tierPreviewUrl, window.location.origin);
    url.searchParams.set('plan_tier', String(planTier));

    if (Object.keys(daySelections).length > 0) {
        url.searchParams.set('selections', JSON.stringify(daySelections));
    }

    const response = await fetch(url.toString(), {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error('Could not load tier preview.');
    }

    const payload = await response.json();

    return payload.days ?? [];
}

/** @type {Record<string, 'breakfasts' | 'meals' | 'sideSalads' | 'desserts' | 'soup'>} */
const SLOT_KEY_TO_CATEGORY = {
    breakfast: 'breakfasts',
    meal: 'meals',
    sidesalad: 'sideSalads',
    dessert: 'desserts',
    soup: 'soup',
};

const DETAIL_SECTIONS = SCHEDULER_SLOT_SECTIONS.map((section) => ({
    categoryKey: SLOT_KEY_TO_CATEGORY[section.key],
    header: section.label,
    deckSuffix: section.key,
    maxSelected: section.count,
}));

/** @param {string} categoryKey */
function defaultSelectionCapForCategory(categoryKey) {
    return DEFAULT_FULL_CRAFT_MAX_SELECTIONS[categoryKey] ?? 1;
}

/** @param {Array<{ dayNumber: number; categories?: Record<string, { id: string }[]> }>} planDays */
function buildInitialDaySelections(planDays) {
    /** @type {Record<number, Record<string, string[]>>} */
    const out = {};

    for (const day of planDays) {
        out[day.dayNumber] = {};
        for (const section of DETAIL_SECTIONS) {
            if (section.categoryKey === 'soup') {
                out[day.dayNumber].soup = [];
                continue;
            }

            const meals = day.categories?.[section.categoryKey] ?? [];
            const cap = defaultSelectionCapForCategory(section.categoryKey);
            out[day.dayNumber][section.categoryKey] = meals.slice(0, cap).map((meal) => String(meal.id));
        }
    }

    return out;
}

/**
 * @param {object} props
 * @param {object} props.mealPlan
 * @param {Array<{ dayNumber: number; label: string; categories: Record<string, unknown[]> }>} props.days
 * @param {number[]} [props.planTiers]
 * @param {number} [props.defaultPlanTier]
 * @param {string} [props.tierPreviewUrl]
 * @param {string} [props.libraryUrl]
 * @param {object[]} [props.ingredientProfiles]
 */
export default function MealPlanDetailPage({
    mealPlan,
    days = [],
    planTiers = DEFAULT_PLAN_TIERS,
    defaultPlanTier = 1500,
    tierPreviewUrl = '',
    libraryUrl = '/admin/meal-plan-library',
    ingredientProfiles = [],
}) {
    const availablePlanTiers = planTiers.length > 0 ? planTiers : DEFAULT_PLAN_TIERS;
    const mealPlanId = mealPlan?.id ?? 0;
    const initialTier = readStoredMealPlanTier(
        mealPlanId,
        availablePlanTiers,
        availablePlanTiers.includes(defaultPlanTier) ? defaultPlanTier : availablePlanTiers[0],
    );

    const [selectedTier, setSelectedTier] = useState(initialTier);
    const [planDays, setPlanDays] = useState(days);
    const [tierLoading, setTierLoading] = useState(Boolean(tierPreviewUrl));
    const [tierError, setTierError] = useState(/** @type {string | null} */ (null));
    const [activeDay, setActiveDay] = useState(() => days[0]?.dayNumber ?? 1);
    const [daySelections, setDaySelections] = useState(() => buildInitialDaySelections(days));
    const [mealEditModal, setMealEditModal] = useState(
        /** @type {{ dayNumber: number; categoryKey: string; meal: object } | null} */ (null),
    );
    const { mealDetailModal, detailLoading, openMealDetail, closeMealDetail } = useMealDetailModal(
        '/api/meals/{id}/detail-view',
        () => {
            const params = new URLSearchParams();
            params.set('plan_tier', String(selectedTier));
            params.set('craft_key', 'full');
            params.set('day_of_week', String(activeDay));

            return params.toString();
        },
    );

    useEffect(() => {
        setPlanDays(days);
        setDaySelections(buildInitialDaySelections(days));
    }, [days]);

    const daySelectionsJson = useMemo(() => JSON.stringify(daySelections), [daySelections]);

    useEffect(() => {
        if (!tierPreviewUrl) {
            setTierLoading(false);
            return undefined;
        }

        let cancelled = false;

        setTierLoading(true);
        setTierError(null);

        const timer = window.setTimeout(() => {
            fetchTierPreviewDays(tierPreviewUrl, selectedTier, daySelections)
                .then((tierDays) => {
                    if (cancelled) {
                        return;
                    }

                    setPlanDays(tierDays);
                })
                .catch(() => {
                    if (!cancelled) {
                        setTierError('Could not scale meals for this tier. Showing library portions.');
                        setPlanDays(days);
                    }
                })
                .finally(() => {
                    if (!cancelled) {
                        setTierLoading(false);
                    }
                });
        }, 300);

        return () => {
            cancelled = true;
            window.clearTimeout(timer);
        };
    }, [tierPreviewUrl, selectedTier, daySelectionsJson, days]);

    useEffect(() => {
        if (mealPlanId <= 0) {
            return;
        }

        try {
            sessionStorage.setItem(`mc-admin-meal-plan-tier-${mealPlanId}`, String(selectedTier));
        } catch {
            // ignore storage errors
        }
    }, [mealPlanId, selectedTier]);

    const activeDayData = useMemo(
        () => planDays.find((day) => day.dayNumber === activeDay) ?? planDays[0] ?? null,
        [activeDay, planDays],
    );

    const activeDaySelections = daySelections[activeDay] ?? {};

    const activeDayReconciliationWarnings = useMemo(
        () => (Array.isArray(activeDayData?.reconciliationWarnings) ? activeDayData.reconciliationWarnings : []),
        [activeDayData],
    );

    const selectedCategoriesForMacros = useMemo(() => {
        if (!activeDayData?.categories) {
            return {};
        }

        /** @type {Record<string, unknown[]>} */
        const out = {};

        for (const section of DETAIL_SECTIONS) {
            const meals = activeDayData.categories?.[section.categoryKey] ?? [];
            const selectedSet = new Set(activeDaySelections[section.categoryKey] ?? []);
            out[section.categoryKey] = meals.filter((meal) => selectedSet.has(String(meal.id)));
        }

        return out;
    }, [activeDayData, activeDaySelections]);

    const backUrl = resolveUrl(libraryUrl, '/admin/meal-plan-library');

    const openMealEdit = useCallback((meal, categoryKey) => {
        const hasKitchenRows = Array.isArray(meal?.kitchenIngredientRows) && meal.kitchenIngredientRows.length > 0;

        if (!meal?.editForm && !hasKitchenRows) {
            return;
        }
        setMealEditModal({
            dayNumber: activeDay,
            categoryKey,
            meal,
        });
    }, [activeDay]);

    const handleApplyMealEdit = useCallback((updatedMeal) => {
        if (!mealEditModal) {
            return;
        }
        setPlanDays((prev) =>
            updateMealInPlanDays(prev, {
                dayNumber: mealEditModal.dayNumber,
                categoryKey: mealEditModal.categoryKey,
                mealId: String(mealEditModal.meal.id),
            }, updatedMeal),
        );
    }, [mealEditModal]);

    const toggleMealSelection = useCallback((categoryKey, meal, maxSelected) => {
        const mealId = String(meal.id);

        setDaySelections((prev) => {
            const day = prev[activeDay] ?? {};
            const current = day[categoryKey] ?? [];
            const next = applyDeckSelectionToggle(current, mealId, maxSelected);

            return {
                ...prev,
                [activeDay]: {
                    ...day,
                    [categoryKey]: next,
                },
            };
        });
    }, [activeDay]);

    /** Side salad / dessert / soup behave as one "pick 1–2 of 3" group, matching the customer flow. */
    const toggleFixedChoiceSide = useCallback((categoryKey, meal) => {
        const mealId = String(meal.id);

        setDaySelections((prev) => {
            const day = prev[activeDay] ?? {};
            const { next, blocked } = applyFixedChoiceToggle(day, categoryKey, mealId);

            if (blocked) {
                return prev;
            }

            return {
                ...prev,
                [activeDay]: next,
            };
        });
    }, [activeDay]);

    const planCategoryLabel = String(mealPlan?.category ?? '').trim();
    const goalText = String(mealPlan?.goal ?? '').trim();
    const showGoalDescription =
        goalText !== '' &&
        goalText.toLowerCase() !== planCategoryLabel.toLowerCase() &&
        goalText.toLowerCase() !== 'balanced';

    const dietProtocol =
        planCategoryLabel.toLowerCase().includes('nutrient') ? 'nutrient_dense' : 'balanced';

    const nutritionPlanForMacros = useMemo(
        () =>
            dietProtocol === 'nutrient_dense'
                ? { protein_percentage: 32, carb_percentage: 28, fat_percentage: 40 }
                : { protein_percentage: 35, carb_percentage: 35, fat_percentage: 30 },
        [dietProtocol],
    );

    const coreSections = DETAIL_SECTIONS.filter(
        (section) => !FIXED_CHOICE_CATEGORY_KEYS.includes(section.categoryKey),
    );
    const sideSections = FIXED_CHOICE_CATEGORY_KEYS.map((key) =>
        DETAIL_SECTIONS.find((section) => section.categoryKey === key),
    ).filter(Boolean);
    const selectedSideCount = countFixedChoiceSelections(activeDaySelections);

    return (
        <div className={`min-h-full font-body ${PAGE_BG}`}>
            <div className="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <div className="mb-5 min-w-0">
                    <Link
                        href={backUrl}
                        className="inline-flex items-center gap-1 font-montserrat text-sm font-semibold text-[#5A6B44] hover:underline"
                    >
                        ← Back to Meal Plan Library
                    </Link>
                    <h1 className="mt-2 font-montserrat text-2xl font-bold tracking-tight text-[#262A22] sm:text-3xl">
                        {mealPlan?.name ?? 'Meal plan'}
                    </h1>
                    {showGoalDescription ? (
                        <p className="mt-2 max-w-3xl font-body text-sm leading-relaxed text-[#555555] sm:text-base">
                            {goalText}
                        </p>
                    ) : null}
                </div>

                <div className="mb-6 rounded-[12px] border border-gray-200 bg-white p-3 sm:p-4">
                    <div className="overflow-x-auto [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                        <div
                            className="flex w-max min-w-full items-center gap-2 px-1 py-2"
                            role="tablist"
                            aria-label="Meal plan days"
                        >
                            {planDays.map((day) => {
                                const selected = day.dayNumber === activeDay;
                                return (
                                    <PillButton
                                        key={day.dayNumber}
                                        type="button"
                                        role="tab"
                                        aria-selected={selected}
                                        label={day.label}
                                        variant={selected ? 'primary' : 'tab'}
                                        size="sm"
                                        onClick={() => setActiveDay(day.dayNumber)}
                                        className="shrink-0"
                                    />
                                );
                            })}
                        </div>
                    </div>
                </div>

                {tierPreviewUrl ? (
                    <div className="mb-6">
                        <AdminPreviewTierPicker
                            tiers={availablePlanTiers}
                            selectedTier={selectedTier}
                            onSelectTier={setSelectedTier}
                            loading={tierLoading}
                            description="Pick a calorie tier to reconcile kitchen portions and nutrition while you review each meal in this plan."
                            compactHint="Breakfast and mains scale to the tier you pick. Side salads, desserts, and soup stay at standard kitchen portions."
                        />
                        {activeDayReconciliationWarnings.length > 0 ? (
                            <div className="mt-2 rounded-[10px] border border-amber-200 bg-amber-50 px-3 py-2 font-body text-sm text-amber-900">
                                {activeDayReconciliationWarnings.map((warning) => (
                                    <p key={warning}>{warning}</p>
                                ))}
                            </div>
                        ) : null}
                        {tierError ? (
                            <p className="mt-2 rounded-[10px] border border-amber-200 bg-amber-50 px-3 py-2 font-body text-sm text-amber-900">
                                {tierError}
                            </p>
                        ) : null}
                    </div>
                ) : null}

                <div className="mb-6">
                    <DayMacroMicroTabPanel
                        categories={selectedCategoriesForMacros}
                        dayLabel={activeDayData?.label ?? 'Day'}
                        planCategoryLabel={`${planCategoryLabel} · ${selectedTier} kcal`.trim()}
                        craftKey="full"
                        planTierCalories={selectedTier}
                        nutritionPlan={nutritionPlanForMacros}
                        dietProtocol={dietProtocol}
                    />
                </div>

                <AnimatePresence mode="wait" initial={false}>
                    <motion.div
                        key={`${activeDayData?.dayNumber ?? 'empty'}-${selectedTier}`}
                        initial={{ x: 24, opacity: 0 }}
                        animate={{ x: 0, opacity: tierLoading ? 0.6 : 1 }}
                        exit={{ x: -24, opacity: 0 }}
                        transition={{ type: 'spring', stiffness: 260, damping: 30, mass: 0.85 }}
                        className={`mt-6 space-y-10 overflow-visible pb-12 ${tierLoading ? 'pointer-events-none' : ''}`}
                        aria-busy={tierLoading}
                    >
                        {activeDayData ? (
                            <>
                                {coreSections.map((section) => {
                                    const cards = activeDayData.categories?.[section.categoryKey] ?? [];
                                    const selectedIds = activeDaySelections[section.categoryKey] ?? [];

                                    return (
                                        <MealSlotCarousel
                                            key={`${activeDayData.dayNumber}-${section.categoryKey}`}
                                            title={section.header}
                                            deckScopeKey={`plan-${mealPlan?.id ?? 'x'}-day-${activeDayData.dayNumber}-${section.deckSuffix}`}
                                            sectionKey={section.categoryKey}
                                            sectionStackOrder={0}
                                            cards={cards}
                                            selectedIds={selectedIds}
                                            maxSelected={defaultSelectionCapForCategory(section.categoryKey)}
                                            onSelect={(meal) =>
                                                toggleMealSelection(
                                                    section.categoryKey,
                                                    meal,
                                                    defaultSelectionCapForCategory(section.categoryKey),
                                                )
                                            }
                                            onViewDetails={openMealDetail}
                                            onEditMeal={(meal) => openMealEdit(meal, section.categoryKey)}
                                        />
                                    );
                                })}

                                {sideSections.length > 0 ? (
                                    <div className="space-y-4">
                                        <div className="min-w-0">
                                            <h2 className="font-montserrat text-lg font-bold text-[#262A22]">
                                                Pick 1–2 of 3 sides
                                            </h2>
                                            <p className="mt-0.5 text-sm text-[#555555]">
                                                Side salad, soup, or dessert • {selectedSideCount}/{FIXED_CHOICE_MAX_COUNT} selected (min 1)
                                            </p>
                                        </div>
                                        {sideSections.map((section) => {
                                            const cards = activeDayData.categories?.[section.categoryKey] ?? [];
                                            const selectedIds = activeDaySelections[section.categoryKey] ?? [];

                                            return (
                                                <MealSlotCarousel
                                                    key={`${activeDayData.dayNumber}-${section.categoryKey}`}
                                                    title={section.header}
                                                    deckScopeKey={`plan-${mealPlan?.id ?? 'x'}-day-${activeDayData.dayNumber}-${section.deckSuffix}`}
                                                    sectionKey={section.categoryKey}
                                                    sectionStackOrder={0}
                                                    cards={cards}
                                                    selectedIds={selectedIds}
                                                    maxSelected={1}
                                                    onSelect={(meal) =>
                                                        toggleFixedChoiceSide(section.categoryKey, meal)
                                                    }
                                                    onViewDetails={openMealDetail}
                                                    onEditMeal={(meal) => openMealEdit(meal, section.categoryKey)}
                                                />
                                            );
                                        })}
                                    </div>
                                ) : null}
                            </>
                        ) : (
                            <p className="rounded-[12px] border border-dashed border-gray-200 bg-white p-8 text-center text-sm text-[#555555]">
                                No day data available for this plan.
                            </p>
                        )}
                    </motion.div>
                </AnimatePresence>
            </div>

            {mealDetailModal
                ? createPortal(
                      <div className="fixed inset-0 z-[120] flex items-end justify-center sm:items-center sm:p-4">
                          <button
                              type="button"
                              className="absolute inset-0 bg-black/40"
                              onClick={closeMealDetail}
                              aria-label="Close meal details"
                          />
                          <div
                              role="dialog"
                              aria-modal="true"
                              aria-labelledby="meal-plan-detail-modal-title"
                              className="relative z-10 flex max-h-[min(92dvh,900px)] w-full max-w-2xl flex-col overflow-hidden rounded-t-[16px] bg-white shadow-2xl sm:rounded-[16px]"
                          >
                              <div className="flex shrink-0 items-start justify-between gap-4 border-b border-gray-100 px-5 py-4">
                                  <div className="min-w-0">
                                      <h2
                                          id="meal-plan-detail-modal-title"
                                          className="break-words font-montserrat text-lg font-bold text-[#262A22]"
                                      >
                                          {mealDetailModal.title}
                                      </h2>
                                  </div>
                                  <Button
                                      label="Close"
                                      variant="ghost"
                                      type="button"
                                      onClick={closeMealDetail}
                                  />
                              </div>
                              <MealDetailView meal={mealDetailModal.detailView} embedded />
                              {detailLoading ? (
                                  <p className="px-5 pb-4 text-sm text-stone-500">Loading meal details…</p>
                              ) : null}
                          </div>
                      </div>,
                      document.body,
                  )
                : null}

            {mealEditModal
                ? createPortal(
                      <div className="fixed inset-0 z-[120] flex items-end justify-center sm:items-center sm:p-4">
                          <button
                              type="button"
                              className="absolute inset-0 bg-black/40"
                              onClick={() => setMealEditModal(null)}
                              aria-label="Close meal editor"
                          />
                          <div
                              role="dialog"
                              aria-modal="true"
                              aria-labelledby="meal-plan-edit-modal-title"
                              className="relative z-10 flex max-h-[min(92dvh,900px)] w-full max-w-2xl flex-col overflow-hidden rounded-t-[16px] bg-white shadow-2xl sm:rounded-[16px]"
                          >
                              <MealPlanMealEditSheet
                                  meal={mealEditModal.meal}
                                  ingredientProfiles={ingredientProfiles}
                                  planTier={selectedTier}
                                  tierPreviewActive={Boolean(tierPreviewUrl)}
                                  onClose={() => setMealEditModal(null)}
                                  onApply={handleApplyMealEdit}
                              />
                          </div>
                      </div>,
                      document.body,
                  )
                : null}
        </div>
    );
}

MealPlanDetailPage.layout = adminInertiaLayout;
