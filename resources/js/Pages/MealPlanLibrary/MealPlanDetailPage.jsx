import { createPortal } from 'react-dom';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import adminInertiaLayout from '../../lib/adminInertiaLayout.jsx';
import { resolveUrl } from '../../meal-craft/mealCraftPageProps.js';
import PillButton from '../../Components/Atoms/Button/Button.jsx';
import Button from '../../Components/Atoms/Button.jsx';
import AdminPreviewTierPicker from '../../Components/Admin/AdminPreviewTierPicker.jsx';
import ChooseYourMeals, {
    applyDeckSelectionToggle,
    DEFAULT_FULL_CRAFT_MAX_SELECTIONS,
} from '../../Components/Consultation/ChooseYourMeals.jsx';
import {
    applyFixedChoiceToggle,
    FIXED_CHOICE_CATEGORY_KEYS,
} from '../../consultation/fixedChoiceSelection.js';
import { DayMacroMicroTabPanel } from '../../Components/Consultation/DayNutritionalSummaryPanel.jsx';
import MealDetailView from '../../Components/Molecules/MealDetailView/MealDetailView';
import MealPlanMealEditSheet from '../../Components/MealPlan/MealPlanMealEditSheet.jsx';
import { SCHEDULER_SLOT_SECTIONS } from '../../meal-library/mealSearch.ts';
import { updateMealInPlanDays } from './mealPlanMealEdit.js';
import { useMealDetailModal } from '../../meal-library/useMealDetailModal.js';

const PAGE_BG = 'bg-[#F8F9F6]';

const WEEKDAY_LONG = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

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

const CATEGORY_KEYS_FOR_SELECTION = ['breakfasts', 'meals', 'sideSalads', 'desserts', 'soup'];

/**
 * Keep chosen breakfast / side cards visible after tier preview recategorizes a meal.
 *
 * @param {Array<{ dayNumber: number; categories?: Record<string, object[]> }>} previewDays
 * @param {Array<{ dayNumber: number; categories?: Record<string, object[]> }>} previousDays
 * @param {Record<number, Record<string, Array<string|number>>>} daySelections
 */
function retainSelectedMealsInPreviewDays(previewDays, previousDays, daySelections) {
    /** @type {Map<number, { dayNumber: number; categories?: Record<string, object[]> }>} */
    const previousByDay = new Map((previousDays ?? []).map((day) => [day.dayNumber, day]));

    return (previewDays ?? []).map((day) => {
        const previous = previousByDay.get(day.dayNumber);
        const selections = daySelections?.[day.dayNumber] ?? daySelections?.[String(day.dayNumber)] ?? {};
        /** @type {Map<string, object>} */
        const catalog = new Map();

        for (const source of [previous?.categories, day.categories]) {
            for (const meals of Object.values(source ?? {})) {
                for (const meal of meals ?? []) {
                    const id = String(meal?.id ?? '');
                    if (id !== '' && !catalog.has(id)) {
                        catalog.set(id, meal);
                    }
                }
            }
        }

        /** @type {Record<string, object[]>} */
        const categories = { ...(day.categories ?? {}) };

        for (const categoryKey of CATEGORY_KEYS_FOR_SELECTION) {
            const existing = Array.isArray(categories[categoryKey]) ? [...categories[categoryKey]] : [];
            const previousMeals = previous?.categories?.[categoryKey] ?? [];
            const existingIds = new Set(existing.map((meal) => String(meal?.id ?? '')).filter((id) => id !== ''));

            if (existing.length === 0) {
                for (const meal of previousMeals) {
                    const id = String(meal?.id ?? '');
                    if (id === '' || existingIds.has(id)) {
                        continue;
                    }

                    existing.push(meal);
                    existingIds.add(id);
                }
            }

            for (const rawId of selections[categoryKey] ?? []) {
                const id = String(rawId);
                if (id === '' || existingIds.has(id)) {
                    continue;
                }

                const meal = catalog.get(id);
                if (!meal) {
                    continue;
                }

                existing.push(meal);
                existingIds.add(id);
            }

            categories[categoryKey] = existing;
        }

        return { ...day, categories };
    });
}

/**
 * Rebind checked side categories onto a card that is actually on the day.
 *
 * @param {Record<number, Record<string, string[]>>} daySelections
 * @param {Array<{ dayNumber: number; categories?: Record<string, object[]> }>} planDays
 */
function repairFixedChoiceSelections(daySelections, planDays) {
    let changed = false;
    const next = { ...daySelections };

    for (const day of planDays ?? []) {
        const current = next[day.dayNumber] ?? next[String(day.dayNumber)] ?? {};
        let dayChanged = false;
        const patched = { ...current };

        for (const key of FIXED_CHOICE_CATEGORY_KEYS) {
            const ids = (current[key] ?? []).map((id) => String(id)).filter((id) => id !== '');

            if (ids.length === 0) {
                continue;
            }

            const cards = day.categories?.[key] ?? [];
            const cardIds = new Set(cards.map((meal) => String(meal?.id ?? '')).filter((id) => id !== ''));
            const valid = ids.filter((id) => cardIds.has(id));

            if (valid.length === ids.length) {
                continue;
            }

            dayChanged = true;

            if (valid.length > 0) {
                patched[key] = valid;
                continue;
            }

            if (cards.length === 0) {
                continue;
            }

            const recommended =
                cards.find((meal) => meal?.isRecommended || meal?.is_recommended) ?? cards[0];
            patched[key] = [String(recommended.id)];
        }

        if (dayChanged) {
            next[day.dayNumber] = patched;
            changed = true;
        }
    }

    return changed ? next : daySelections;
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

/**
 * Prefer recommended / primary-slot meals when seeding admin preview selections.
 *
 * @param {Array<{ id?: string|number; isRecommended?: boolean; plan_slot_index?: number; planSlotIndex?: number }>} meals
 * @param {number} cap
 * @returns {string[]}
 */
function initialSelectedIdsForCategory(meals, cap) {
    if (!Array.isArray(meals) || meals.length === 0 || cap <= 0) {
        return [];
    }

    const recommended = meals.filter((meal) => meal?.isRecommended || meal?.is_recommended);
    const source = recommended.length > 0 ? recommended : meals;

    return source.slice(0, cap).map((meal) => String(meal.id));
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
            out[day.dayNumber][section.categoryKey] = initialSelectedIdsForCategory(meals, cap);
        }
    }

    return out;
}

/**
 * @param {Record<number|string, Record<string, Array<number|string>>>|null|undefined} stored
 * @param {Array<{ dayNumber: number; categories?: Record<string, { id: string }[]> }>} planDays
 */
function buildInitialDaySelectionsFromStored(stored, planDays) {
    if (!stored || typeof stored !== 'object' || Object.keys(stored).length === 0) {
        return null;
    }

    /** @type {Record<number, Record<string, string[]>>} */
    const out = {};

    for (const day of planDays) {
        const dayKey = day.dayNumber;
        const rawDay = stored[dayKey] ?? stored[String(dayKey)] ?? {};
        out[dayKey] = {};

        for (const section of DETAIL_SECTIONS) {
            const ids = Array.isArray(rawDay?.[section.categoryKey])
                ? rawDay[section.categoryKey].map((id) => String(id)).filter((id) => id !== '')
                : [];
            out[dayKey][section.categoryKey] = ids;
        }
    }

    return out;
}

/**
 * @param {Array<{ dayNumber: number; categories?: Record<string, { id: string }[]> }>} planDays
 * @param {Record<number|string, Record<string, Array<number|string>>>|null|undefined} defaultDaySelections
 */
function resolveInitialDaySelections(planDays, defaultDaySelections) {
    return buildInitialDaySelectionsFromStored(defaultDaySelections, planDays) ?? buildInitialDaySelections(planDays);
}

/**
 * @param {{ categories?: Record<string, Array<{ id?: string|number }>> } | null} dayData
 * @param {{ id?: string|number }} meal
 */
function categoryKeyForMeal(dayData, meal) {
    const mealId = String(meal?.id ?? '');

    if (!dayData?.categories || mealId === '') {
        return null;
    }

    for (const section of DETAIL_SECTIONS) {
        const meals = dayData.categories[section.categoryKey] ?? [];

        if (meals.some((item) => String(item?.id ?? '') === mealId)) {
            return section.categoryKey;
        }
    }

    return null;
}

/**
 * @param {object} props
 * @param {object} props.mealPlan
 * @param {Array<{ dayNumber: number; label: string; categories: Record<string, unknown[]> }>} props.days
 * @param {Record<number|string, Record<string, Array<number|string>>>} [props.defaultDaySelections]
 * @param {number[]} [props.planTiers]
 * @param {number} [props.defaultPlanTier]
 * @param {string} [props.tierPreviewUrl]
 * @param {string} [props.saveDefaultSelectionsUrl]
 * @param {string} [props.libraryUrl]
 * @param {object[]} [props.ingredientProfiles]
 * @param {string} [props.dietProtocol]
 */
export default function MealPlanDetailPage({
    mealPlan,
    days = [],
    defaultDaySelections = null,
    planTiers = DEFAULT_PLAN_TIERS,
    defaultPlanTier = 1500,
    tierPreviewUrl = '',
    saveDefaultSelectionsUrl = '',
    libraryUrl = '/admin/meal-plan-library',
    ingredientProfiles = [],
    dietProtocol = 'balanced',
}) {
    const page = usePage();
    const flashSuccess = page.props?.flash?.success ?? null;
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
    const [daySelections, setDaySelections] = useState(() =>
        resolveInitialDaySelections(days, defaultDaySelections),
    );
    const [savingDefaults, setSavingDefaults] = useState(false);
    const [saveDefaultsError, setSaveDefaultsError] = useState(/** @type {string | null} */ (null));
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
        setDaySelections(resolveInitialDaySelections(days, defaultDaySelections));
    }, [days, defaultDaySelections]);

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

                    setPlanDays(retainSelectedMealsInPreviewDays(tierDays, days, daySelections));
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

    useEffect(() => {
        setDaySelections((prev) => repairFixedChoiceSelections(prev, planDays));
    }, [planDays]);

    const catalogMeals = useMemo(() => {
        if (!activeDayData?.categories) {
            return [];
        }

        return Object.values(activeDayData.categories).flat().filter(Boolean);
    }, [activeDayData]);

    const activeDaySelections = daySelections[activeDay] ?? {};

    const activeDayReconciliationWarnings = useMemo(
        () => (Array.isArray(activeDayData?.reconciliationWarnings) ? activeDayData.reconciliationWarnings : []),
        [activeDayData],
    );

    const selectedCategoriesForDayNutrition = useMemo(() => {
        if (!activeDayData?.categories) {
            return {};
        }

        /** @type {Record<string, unknown[]>} */
        const out = {};

        for (const section of DETAIL_SECTIONS) {
            const meals = activeDayData.categories?.[section.categoryKey] ?? [];
            const selectedSet = new Set(
                (activeDaySelections[section.categoryKey] ?? []).map((id) => String(id)),
            );

            out[section.categoryKey] = meals.filter((meal) => selectedSet.has(String(meal?.id ?? '')));
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

    const clearFixedChoiceCategory = useCallback((categoryKey) => {
        setDaySelections((prev) => {
            const day = prev[activeDay] ?? {};

            return {
                ...prev,
                [activeDay]: {
                    ...day,
                    [categoryKey]: [],
                },
            };
        });
    }, [activeDay]);

    const saveDefaultSelections = useCallback(() => {
        if (!saveDefaultSelectionsUrl) {
            return;
        }

        setSavingDefaults(true);
        setSaveDefaultsError(null);

        router.put(
            saveDefaultSelectionsUrl,
            { selections: daySelections },
            {
                preserveScroll: true,
                onFinish: () => setSavingDefaults(false),
                onError: () => {
                    setSaveDefaultsError('Could not save default selections. Please try again.');
                },
            },
        );
    }, [daySelections, saveDefaultSelectionsUrl]);

    const planCategoryLabel = String(mealPlan?.category ?? '').trim();
    const goalText = String(mealPlan?.goal ?? '').trim();
    const showGoalDescription =
        goalText !== '' &&
        goalText.toLowerCase() !== planCategoryLabel.toLowerCase() &&
        goalText.toLowerCase() !== 'balanced';

    const categoryMaxSelections = useMemo(() => {
        const breakfastCount = activeDayData?.categories?.breakfasts?.length ?? 0;

        return {
            ...DEFAULT_FULL_CRAFT_MAX_SELECTIONS,
            breakfasts: breakfastCount > 0 ? DEFAULT_FULL_CRAFT_MAX_SELECTIONS.breakfasts : 0,
        };
    }, [activeDayData]);

    const handleToggleCategory = useCallback(
        (categoryKey, meal) => {
            if (FIXED_CHOICE_CATEGORY_KEYS.includes(categoryKey)) {
                toggleFixedChoiceSide(categoryKey, meal);
                return;
            }

            toggleMealSelection(categoryKey, meal, defaultSelectionCapForCategory(categoryKey));
        },
        [toggleFixedChoiceSide, toggleMealSelection],
    );

    const handleEditMeal = useCallback(
        (meal) => {
            const categoryKey = categoryKeyForMeal(activeDayData, meal);

            if (!categoryKey) {
                return;
            }

            openMealEdit(meal, categoryKey);
        },
        [activeDayData, openMealEdit],
    );

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
                    <p className="mt-3 max-w-3xl font-body text-sm text-[#555555]">
                        Select the default meals for each day, then save. Customers start with these picks and can still
                        change them via SEE OTHER OPTIONS.
                    </p>
                    <div className="mt-4 flex flex-wrap items-center gap-3">
                        <Button
                            type="button"
                            variant="primary"
                            size="sm"
                            label={savingDefaults ? 'Saving defaults…' : 'Save as customer defaults'}
                            disabled={!saveDefaultSelectionsUrl || savingDefaults}
                            onClick={saveDefaultSelections}
                        />
                        {flashSuccess ? (
                            <p className="font-body text-sm text-[#5A6B44]">{String(flashSuccess)}</p>
                        ) : null}
                        {saveDefaultsError ? (
                            <p className="font-body text-sm text-red-700">{saveDefaultsError}</p>
                        ) : null}
                    </div>
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

                {activeDayData ? (
                    <div className="mb-8">
                        <DayMacroMicroTabPanel
                            key={`day-nutrition-${activeDayData.dayNumber}-${selectedTier}`}
                            categories={selectedCategoriesForDayNutrition}
                            dayLabel={activeDayData.label ?? 'Day'}
                            planCategoryLabel={planCategoryLabel}
                            craftKey="full"
                            planTierCalories={selectedTier}
                            nutritionPlan={null}
                            initialTab="micronutrients"
                        />
                    </div>
                ) : null}

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
                            <ChooseYourMeals
                                panelClassName="h-[min(78dvh,880px)] min-h-[560px]"
                                dayName={WEEKDAY_LONG[(activeDayData.dayNumber ?? 1) - 1] ?? activeDayData.label}
                                layout="categories"
                                dietProtocol={dietProtocol}
                                protocolSelectedLayout
                                meals={catalogMeals}
                                displayDecks={activeDayData.categories}
                                assignedMealsByCategory={activeDayData.categories}
                                categorySelections={activeDaySelections}
                                maxSelectionsByCategory={categoryMaxSelections}
                                onToggleCategory={handleToggleCategory}
                                onClearFixedChoiceCategory={clearFixedChoiceCategory}
                                deckScopePrefix={`plan-${mealPlan?.id ?? 'x'}-day-${activeDayData.dayNumber}`}
                                onViewDetails={openMealDetail}
                                onEditMeal={handleEditMeal}
                                targetCalories={selectedTier}
                                craftTitle={mealPlan?.name ?? ''}
                            />
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
                                  <div className="min-w-0 flex-1">
                                      <h2
                                          id="meal-plan-detail-modal-title"
                                          className="break-words font-montserrat text-lg font-bold text-[#262A22]"
                                      >
                                          {mealDetailModal.title}
                                      </h2>
                                      {mealDetailModal.detailView?.shortDescription ||
                                      mealDetailModal.detailView?.description ? (
                                          <p className="mt-1 font-montserrat text-sm font-medium leading-snug text-[#555555]">
                                              {mealDetailModal.detailView.shortDescription ||
                                                  mealDetailModal.detailView.description}
                                          </p>
                                      ) : null}
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
