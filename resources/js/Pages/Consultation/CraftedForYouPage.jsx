import { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import Button from '../../Components/Atoms/Button.jsx';
import { resolveCustomerPhoneFrameHost } from '../../Components/Molecules/Customer/resolveCustomerPhoneFrameHost.js';
import OnboardingOptionButton from '../../Components/Atoms/Button/OnboardingOptionButton.jsx';
import FoodFilterPill from '../../Components/MealSystem/FoodFilterPill.jsx';
import OnboardingInlineDescription from '../../Components/Molecules/Onboarding/OnboardingInlineDescription.jsx';
import ChooseYourMeals, {
    applyDeckSelectionToggle,
    applyFixedChoiceToggle,
    buildWeeklyConsultationDisplayDecks,
    DEFAULT_FULL_CRAFT_MAX_SELECTIONS,
    FIXED_CHOICE_CATEGORY_KEYS,
    FIXED_CHOICE_REQUIRED_COUNT,
    FixedChoicePicker,
    MealSlotCarousel,
    consultationDeckOptionsForSlotKey,
    consultationDessertDeckForDay,
    consultationSideSaladDeckForDay,
    isFixedChoiceComplete,
    normalizeConsultationMealId,
    selectedMealsForSlotFromDecks,
    soupOfTheDayMeals,
    sumSelectedMacrosFromDisplayDecks,
} from '../../Components/Consultation/ChooseYourMeals.jsx';
import SquareCheckbox from '../../Components/Atoms/Icons/SquareCheckbox.jsx';
import { MealCraftLogoAnimatedIdentity } from '../../Components/Atoms/Logo/MealCraftLogoAnimated.jsx';
import { AnimatePresence, motion } from 'framer-motion';
import {
    fetchAdaptedMenu,
    buildAdaptedMenuQueryString,
    mapAdaptedMenuPayloadToConsultationMeals,
    scheduledFullCraftCategoryMealsForDay,
    scheduledSoupConsultationMealsForDay,
} from '../../consultation/mapAdaptedMenuMeals.js';
import { resolveProtocolBreakfastSeedId } from '../../consultation/seedProtocolBreakfast.js';
import { buildCraftPlanSubmissionPayload, submitCraftPlan } from '../../consultation/submitCraftPlan.js';
import { craftDayCaloriesForKey, craftDefaultsDessert, dailyMacroTargetsFromPlan, dayMacroToleranceFromPlan, fixedPortionCaloriesForAdapt, nutritionPlanMatchesTier, selectedFixedSlotsFromSelections } from '../../consultation/craftCalorieTargets.js';
import {
    resolveInitialConsultationRestoreDraft,
    saveConsultationDraft,
    stashConsultationResumeDraft,
} from '../../consultation/consultationDraft.js';
import { consultationMeals as MOCK_MEALS } from '../../consultation/consultationMockMeals.js';
import { isBalancedAntiInflammatoryProtocol } from '../../Components/Molecules/Onboarding/dietProtocolUtils.js';
import MealDetailModalPortal from '../../Components/Molecules/MealDetailModalPortal.jsx';
import FulfillmentOptionCards from '../../Components/Molecules/Customer/FulfillmentOptionCards.jsx';
import { useMealDetailModal } from '../../meal-library/useMealDetailModal.js';

const PAGE_BG = 'bg-[#F8F9F6]';
const WEEKDAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const WEEKDAY_LONG = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const CONSULTATION_BACK_HREF_STORAGE_KEY = 'mc-consultation-back-href';

const CRAFTS = [
    {
        key: 'full',
        title: 'Full Craft',
        description: '1 Breakfast, 2 Main Meals, pick 1–2 of side salad / dessert / soup (~150 kcal each)',
        slots: [
            { id: 'breakfast', label: 'Breakfast', count: 1 },
            { id: 'meal', label: 'Meals', count: 2 },
            { id: 'fixedChoice', label: 'Pick 1–2 of 3 sides', count: 2 },
        ],
    },
    {
        key: 'afternoon',
        title: 'Afternoon Craft',
        description: '2 Meals, pick 1–2 of side salad / dessert / soup',
        slots: [
            { id: 'meal', label: 'Meals', count: 2 },
            { id: 'fixedChoice', label: 'Pick 1–2 of 3 sides', count: 2 },
        ],
    },
    {
        key: 'day',
        title: 'Day Craft',
        description: 'Breakfast, 1 Meal, pick 1–2 of side salad / dessert / soup',
        slots: [
            { id: 'breakfast', label: 'Breakfast', count: 1 },
            { id: 'meal', label: 'Meal', count: 1 },
            { id: 'fixedChoice', label: 'Pick 1–2 of 3 sides', count: 2 },
        ],
    },
    {
        key: 'intermittent',
        title: 'Intermittent Craft',
        description: '1 Meal, pick 1–2 of side salad / dessert / soup',
        slots: [
            { id: 'meal', label: 'Meal', count: 1 },
            { id: 'fixedChoice', label: 'Pick 1–2 of 3 sides', count: 2 },
        ],
    },
    {
        key: 'business',
        title: 'Business Craft',
        description: '1 Main (350–400 kcal) + pick 1 of soup, side salad, or dessert (~150 kcal)',
        slots: [
            { id: 'meal', label: 'Meal', count: 1 },
            { id: 'choice', label: 'Business choice', count: 1, choice: ['soup', 'sidesalad', 'dessert'] },
        ],
    },
];

/** @typedef {import('../../consultation/consultationDraft.js').ConsultationDraft} ConsultationDraft */

/**
 * @param {{
 *   craftKey?: string;
 *   weekDuration?: number;
 *   selectedWeekdays?: number[];
 *   days?: Array<{
 *     dayNumber: number;
 *     includeSoup?: boolean;
 *     categories?: Record<string, unknown[]>;
 *   }>;
 * } | null | undefined} initialEditDraft
 * @returns {ConsultationDraft | null}
 */
function readInitialConsultationRestoreDraft(initialEditDraft, initialResumeDraft, forceResume = false) {
    try {
        return resolveInitialConsultationRestoreDraft(
            initialEditDraft,
            initialResumeDraft,
            forceResume,
        );
    } catch {
        return null;
    }
}

function mealTitleById(/** @type {string} */ id) {
    return MOCK_MEALS.find((m) => m.id === id)?.title ?? id;
}

function craftByKey(key) {
    return CRAFTS.find((c) => c.key === key) ?? CRAFTS[0];
}

function slotId(dayIdx, slotKey, index) {
    return `d${dayIdx}-${slotKey}-${index}`;
}

/**
 * @param {{
 *   closeHref?: string;
 *   homeHref?: string;
 *   backHref?: string;
 *   summaryHref?: string;
 *   deliveryUrl?: string;
 *   resumeChooseMeals?: boolean;
 *   forceReceive?: boolean;
 *   initialFulfillmentChoice?: 'diy' | 'partner_kitchen' | null;
 *   loginUrl?: string;
 *   signOutUrl?: string;
 *   isCustomerAccount?: boolean;
 *   isAdminPreview?: boolean;
 *   pageEyebrow?: string;
 *   adaptedMenuUrl?: string;
 *   mealDetailViewUrlTemplate?: string;
 *   mealLibraryRevision?: number;
 *   initialPlanTier?: number | null;
 *   disableAdaptedMenuFetch?: boolean;
 *   embedInScrollParent?: boolean;
 *   initialEditDraft?: {
 *     craftKey?: string;
 *     weekDuration?: number;
 *     selectedWeekdays?: number[];
 *     days?: Array<{
 *       dayNumber: number;
 *       includeSoup?: boolean;
 *       categories?: Record<string, unknown[]>;
 *     }>;
 *   } | null;
 *   initialResumeDraft?: import('../../consultation/consultationDraft.js').ConsultationDraft | null;
 *   dietProtocol?: string | null;
 *   sex?: string | null;
 *   activityLevel?: string | null;
 *   dailyCalorieTarget?: number | null;
 *   initialScreen?: number | null;
 *   initialCraftKey?: string | null;
 *   initialWeekDuration?: number | null;
 *   initialSelectedWeekdays?: number[] | null;
 * }} [props]
 */

/**
 * Render overlays inside the customer phone frame when present.
 *
 * @param {{ children: import('react').ReactNode | ((ctx: { nested: boolean }) => import('react').ReactNode) }} props
 */
function PhoneFramePortal({ children }) {
    if (typeof document === 'undefined') {
        return null;
    }

    const host = resolveCustomerPhoneFrameHost();
    if (!host) {
        return null;
    }

    const nested = host !== document.body;
    const content = typeof children === 'function' ? children({ nested }) : children;

    return createPortal(content, host);
}

export default function CraftedForYouPage({
    closeHref,
    homeHref,
    backHref,
    summaryHref,
    deliveryUrl = '/checkout/delivery',
    resumeChooseMeals = false,
    forceReceive = false,
    initialFulfillmentChoice = null,
    loginUrl,
    signOutUrl,
    csrfToken = '',
    isAdminPreview = false,
    pageEyebrow = 'Your plan',
    adaptedMenuUrl = '/api/menu/adapted',
    mealDetailViewUrlTemplate = '/api/meals/{id}/detail-view',
    mealLibraryRevision = 0,
    initialPlanTier = null,
    disableAdaptedMenuFetch = false,
    embedInScrollParent = false,
    initialEditDraft = null,
    initialResumeDraft = null,
    dietProtocol = null,
    sex = null,
    activityLevel = null,
    dailyCalorieTarget = null,
    initialScreen = null,
    initialCraftKey = null,
    initialWeekDuration = null,
    initialSelectedWeekdays = null,
    partnerKitchenPricing = null,
} = {}) {
    const initialRestoreDraft = useMemo(
        () =>
            forceReceive
                ? null
                : readInitialConsultationRestoreDraft(
                      initialEditDraft,
                      initialResumeDraft,
                      resumeChooseMeals,
                  ),
        [forceReceive, initialEditDraft, initialResumeDraft, resumeChooseMeals],
    );

    const [screen, setScreen] = useState(() => {
        if (typeof initialScreen === 'number' && initialScreen >= 1) {
            return initialScreen;
        }

        // Home Choose your meals → Receive: start at craft when fulfillment already picked.
        if (forceReceive) {
            return initialFulfillmentChoice === 'diy' || initialFulfillmentChoice === 'partner_kitchen'
                ? 2
                : 1;
        }

        // Partner payment resume: open on first Choose your meals day immediately.
        if (resumeChooseMeals && initialRestoreDraft?.weekDuration) {
            return initialRestoreDraft.weekDuration !== 7 ? 5 : 4;
        }

        // Returning from payment without a restorable draft: skip Receive (already paid).
        if (resumeChooseMeals) {
            return 2;
        }

        return 1;
    });
    const [craftKey, setCraftKey] = useState(
        () => initialCraftKey ?? initialRestoreDraft?.craftKey ?? null,
    );
    const craft = useMemo(() => (craftKey ? craftByKey(craftKey) : null), [craftKey]);

    const [weekDuration, setWeekDuration] = useState(
        () => initialWeekDuration ?? initialRestoreDraft?.weekDuration ?? null,
    );
    const [planWeeks, setPlanWeeks] = useState(
        () => {
            const restored = initialRestoreDraft?.planWeeks;

            if (restored === 1 || restored === 4) {
                return restored;
            }

            return null;
        },
    );
    const [selectedBilledDays, setSelectedBilledDays] = useState(
        () => {
            const restoredWeeks = initialRestoreDraft?.planWeeks;
            const restoredDuration = initialWeekDuration ?? initialRestoreDraft?.weekDuration ?? null;

            if ((restoredWeeks === 1 || restoredWeeks === 4) && typeof restoredDuration === 'number') {
                return restoredWeeks === 1 ? restoredDuration : restoredDuration * 4;
            }

            return null;
        },
    );
    const [partnerTrialOpen, setPartnerTrialOpen] = useState(
        () => initialRestoreDraft?.planWeeks === 1,
    );
    const [selectedDays, setSelectedDays] = useState(() => {
        if (Array.isArray(initialSelectedWeekdays) && initialSelectedWeekdays.length > 0) {
            return initialSelectedWeekdays;
        }

        return initialRestoreDraft?.selectedDays ?? [];
    });
    const [activeDay, setActiveDay] = useState(() => {
        const restoredDays = initialRestoreDraft?.selectedDays ?? [];
        return restoredDays.length > 0 ? restoredDays[0] : null;
    });
    const [toast, setToast] = useState(/** @type {{ message: string; visible: boolean }} */ ({ message: '', visible: false }));
    const [shakeOn, setShakeOn] = useState(false);

    const [environmentYes, setEnvironmentYes] = useState(
        () => initialRestoreDraft?.environmentYes ?? false,
    );
    /** @type {['diy'|'partner_kitchen'|null, import('react').Dispatch<import('react').SetStateAction<'diy'|'partner_kitchen'|null>>]} */
    const [fulfillmentChoice, setFulfillmentChoice] = useState(
        () =>
            initialFulfillmentChoice === 'diy' || initialFulfillmentChoice === 'partner_kitchen'
                ? initialFulfillmentChoice
                : initialRestoreDraft?.fulfillmentChoice === 'diy' ||
                    initialRestoreDraft?.fulfillmentChoice === 'partner_kitchen'
                  ? initialRestoreDraft.fulfillmentChoice
                  : resumeChooseMeals
                    ? /** @type {'partner_kitchen'} */ ('partner_kitchen')
                    : null,
    );
    const [submitting, setSubmitting] = useState(false);
    const [submitError, setSubmitError] = useState(/** @type {string | null} */ (null));
    const [submitSuccess, setSubmitSuccess] = useState(false);

    // Craft-specific choice selection (per active curation day)
    const [businessCraftChoice, setBusinessCraftChoice] = useState(/** @type {'soup'|'sidesalad'|'dessert'} */ ('soup'));
    const [businessSideChoiceByDay, setBusinessSideChoiceByDay] = useState(
        () => initialRestoreDraft?.businessSideChoiceByDay ?? {},
    );
    const [afternoonSoupOptInByDay, setAfternoonSoupOptInByDay] = useState(
        () => initialRestoreDraft?.afternoonSoupOptInByDay ?? {},
    );
    const [fullCraftSoupOptInByDay, setFullCraftSoupOptInByDay] = useState(
        () => initialRestoreDraft?.fullCraftSoupOptInByDay ?? {},
    );

    const planSetupBackHref = useMemo(() => {
        if (typeof backHref === 'string' && backHref.trim() !== '') {
            return backHref;
        }

        if (typeof homeHref === 'string' && homeHref.trim() !== '') {
            return homeHref;
        }

        if (typeof closeHref === 'string' && closeHref.trim() !== '') {
            return closeHref;
        }

        try {
            const stored = sessionStorage.getItem(CONSULTATION_BACK_HREF_STORAGE_KEY);

            if (typeof stored === 'string' && stored.trim() !== '') {
                return stored;
            }
        } catch {
            // sessionStorage may be unavailable
        }

        return null;
    }, [backHref, closeHref, homeHref]);

    useEffect(() => {
        const hrefToStore =
            (typeof backHref === 'string' && backHref.trim() !== '' ? backHref : null) ??
            (typeof homeHref === 'string' && homeHref.trim() !== '' ? homeHref : null);

        if (hrefToStore === null) {
            return;
        }

        try {
            sessionStorage.setItem(CONSULTATION_BACK_HREF_STORAGE_KEY, hrefToStore);
        } catch {
            // sessionStorage may be unavailable
        }
    }, [backHref, homeHref]);

    // Slot selection state (chosen meal ids per category, per day)
    const [selectedByDay, setSelectedByDay] = useState(
        () => initialRestoreDraft?.selectedByDay ?? {},
    );

    const [liveMeals, setLiveMeals] = useState(/** @type {typeof MOCK_MEALS | null} */ (null));
    const [nutritionPlan, setNutritionPlan] = useState(/** @type {Record<string, unknown> | null} */ (null));
    const [scheduledSoupsByWeekday, setScheduledSoupsByWeekday] = useState(
        /** @type {Record<string | number, unknown>} */ ({}),
    );
    const [scheduledFullCraftByWeekday, setScheduledFullCraftByWeekday] = useState(
        /** @type {Record<string | number, unknown>} */ ({}),
    );
    const [productionMealPlanId, setProductionMealPlanId] = useState(/** @type {number | null} */ (null));
    const [menuLoading, setMenuLoading] = useState(false);
    const [menuError, setMenuError] = useState(/** @type {string | null} */ (null));
    const hasLoadedAdaptedMenuRef = useRef(false);
    const adaptedMenuFetchGenerationRef = useRef(0);
    /** @type {React.MutableRefObject<string>} */
    const scheduleCacheIdentityRef = useRef('');
    /** When craft/tier changes, next successful payload replaces (does not merge) weekday caches. */
    const wipeScheduleCacheOnNextSuccessRef = useRef(false);
    /** Craft key that produced the schedule currently shown on cards (may lag while a craft switch refetches). */
    const displayedScheduleCraftKeyRef = useRef(/** @type {string | null} */ (null));
    /** @type {React.MutableRefObject<Record<string, string>>} */
    const lastAdaptedMenuFetchKeyByDayRef = useRef({});
    /**
     * Weekdays filled by the initial full-week schedule fetch that have not yet been
     * opened (or day-locally re-adapted). Lets Next read slots from cache without a request.
     * @type {React.MutableRefObject<Set<number>>}
     */
    const weekSeededDaysRef = useRef(new Set());
    /** Snapshot of weekday schedules for skip-fetch checks without stale closures. */
    const scheduledFullCraftByWeekdayRef = useRef(scheduledFullCraftByWeekday);
    scheduledFullCraftByWeekdayRef.current = scheduledFullCraftByWeekday;
    useLayoutEffect(() => {
        if (!initialRestoreDraft) {
            return;
        }

        try {
            saveConsultationDraft(initialRestoreDraft);
        } catch {
            // Ignore quota / storage errors — in-memory state is enough for this session.
        }

        const url = new URL(window.location.href);
        if (!url.searchParams.has('edit')) {
            return;
        }

        url.searchParams.delete('edit');
        const next = `${url.pathname}${url.search}${url.hash}`;
        window.history.replaceState({}, '', next);

        setToast({
            visible: true,
            message: 'Your previous selections are loaded. Walk through each step to review or change them.',
        });
    }, [initialRestoreDraft]);

    useEffect(() => {
        if (craftKey === null || weekDuration === null) {
            return;
        }

        saveConsultationDraft({
            craftKey,
            weekDuration,
            planWeeks: planWeeks === 1 || planWeeks === 4 ? planWeeks : 4,
            selectedDays,
            selectedByDay,
            businessSideChoiceByDay,
            afternoonSoupOptInByDay,
            fullCraftSoupOptInByDay,
            environmentYes,
            fulfillmentChoice,
        });
    }, [
        craftKey,
        weekDuration,
        planWeeks,
        selectedDays,
        selectedByDay,
        businessSideChoiceByDay,
        afternoonSoupOptInByDay,
        fullCraftSoupOptInByDay,
        environmentYes,
        fulfillmentChoice,
    ]);

    const catalogMeals = useMemo(() => liveMeals ?? MOCK_MEALS, [liveMeals]);

    const scheduledSoupForDay = useCallback(
        (dayOfWeek) => {
            const scheduled = scheduledSoupConsultationMealsForDay(scheduledSoupsByWeekday, dayOfWeek);
            if (scheduled.length > 0) {
                return scheduled;
            }

            return soupOfTheDayMeals(catalogMeals);
        },
        [scheduledSoupsByWeekday, catalogMeals],
    );

    const basePlanTier = useMemo(() => {
        const fromPlan = nutritionPlan?.plan_tier ?? nutritionPlan?.core_day_calories;
        if (typeof fromPlan === 'number' && fromPlan > 0) {
            return Math.round(fromPlan);
        }
        if (typeof initialPlanTier === 'number' && initialPlanTier > 0) {
            return initialPlanTier;
        }
        if (typeof dailyCalorieTarget === 'number' && dailyCalorieTarget > 0) {
            return dailyCalorieTarget;
        }

        return 1200;
    }, [nutritionPlan, initialPlanTier, dailyCalorieTarget]);

    const planTierCalories = useMemo(() => {
        const planSynced = nutritionPlan && nutritionPlanMatchesTier(nutritionPlan, basePlanTier);
        const fromCraft = planSynced ? nutritionPlan?.craft_day_calories : null;

        if (typeof fromCraft === 'number' && fromCraft > 0) {
            return Math.round(fromCraft);
        }

        if (craftKey) {
            return craftDayCaloriesForKey(craftKey, basePlanTier);
        }

        return basePlanTier;
    }, [nutritionPlan, craftKey, basePlanTier]);

    useEffect(() => {
        if (weekDuration === null) {
            return;
        }
        // If duration is reduced below current selections, trim chronologically (Sun → Sat).
        setSelectedDays((prev) => {
            const sorted = Array.from(new Set(prev)).filter((d) => d >= 1 && d <= 7).sort((a, b) => a - b);
            return sorted.length > weekDuration ? sorted.slice(0, weekDuration) : sorted;
        });
    }, [weekDuration]);

    useEffect(() => {
        if (weekDuration !== 7) {
            return;
        }
        // Poka‑Yoke: a 7-day plan always includes all days; manual selection is redundant.
        const all = [1, 2, 3, 4, 5, 6, 7];
        setSelectedDays(all);
        setActiveDay((prev) => prev ?? 1);
    }, [weekDuration]);

    useEffect(() => {
        // Keep active day valid.
        if (activeDay === null) {
            return;
        }
        if (!selectedDays.includes(activeDay)) {
            setActiveDay(selectedDays[0] ?? null);
        }
    }, [selectedDays, activeDay]);

    useEffect(() => {
        if (!toast.visible) {
            return undefined;
        }
        const t = window.setTimeout(() => setToast({ message: '', visible: false }), 1800);
        return () => window.clearTimeout(t);
    }, [toast.visible]);

    const sortedSelectedDays = useMemo(
        () => Array.from(new Set(selectedDays)).filter((d) => d >= 1 && d <= 7).sort((a, b) => a - b),
        [selectedDays],
    );

    const effectiveActiveDay = activeDay ?? (sortedSelectedDays.length ? sortedSelectedDays[0] : null);

    const slotTemplate = useMemo(() => {
        if (!craft) {
            return [];
        }
        if (craft.key === 'business') {
            return [
                { key: 'meal', label: 'Meal', count: 1 },
                { key: businessCraftChoice, label: 'Option', count: 1 },
            ];
        }
        return craft.slots;
    }, [craft, businessCraftChoice]);

    function businessSideChoiceForDay(day) {
        return businessSideChoiceByDay[day] ?? 'soup';
    }

    function setBusinessSideChoice(day, choice) {
        setBusinessSideChoiceByDay((prev) => ({ ...prev, [day]: choice }));
        // Enforce "one meal + one side" by clearing other side categories when pivoting.
        setSelectedByDay((prev) => {
            const current = prev[day] ?? {
                breakfasts: [],
                meals: [],
                sideSalads: [],
                desserts: [],
                soup: [],
            };
            const next = {
                ...current,
                sideSalads: choice === 'sidesalad' ? current.sideSalads : [],
                desserts: choice === 'dessert' ? current.desserts : [],
                soup: choice === 'soup' ? current.soup : [],
            };
            return { ...prev, [day]: next };
        });
    }

    function afternoonSoupOptInForDay(day) {
        if (afternoonSoupOptInByDay[day] === true) {
            return true;
        }

        return (selectedByDay[day]?.soup?.length ?? 0) > 0;
    }

    function setAfternoonSoupOptIn(day, enabled) {
        setAfternoonSoupOptInByDay((prev) => ({ ...prev, [day]: enabled }));

        if (!enabled) {
            setSelectedByDay((prev) => {
                const current = prev[day] ?? {
                    breakfasts: [],
                    meals: [],
                    sideSalads: [],
                    desserts: [],
                    soup: [],
                };

                return { ...prev, [day]: { ...current, soup: [] } };
            });
        }
    }

    function toggleDay(dayIdx) {
        setSelectedDays((prev) => {
            const set = new Set(prev);
            const isOn = set.has(dayIdx);

            if (isOn) {
                set.delete(dayIdx);
                const next = Array.from(set).sort((a, b) => a - b);
                if (activeDay === dayIdx) {
                    setActiveDay(next[0] ?? null);
                }
                return next;
            }

            if (set.size >= weekDuration) {
                setShakeOn(false);
                window.requestAnimationFrame(() => setShakeOn(true));
                setToast({ message: 'Maximum days for this plan reached.', visible: true });
                return prev;
            }

            set.add(dayIdx);
            const next = Array.from(set).sort((a, b) => a - b);
            setActiveDay(dayIdx);
            return next;
        });
    }

    function exitConsultation() {
        const href = homeHref ?? closeHref;

        if (href) {
            window.location.assign(href);

            return;
        }

        window.history.back();
    }

    function goBackFromPlanSetup() {
        if (planSetupBackHref) {
            window.location.assign(planSetupBackHref);

            return;
        }

        exitConsultation();
    }

    async function submit() {
        if (!craftKey || weekDuration === null) {
            return;
        }

        setSubmitting(true);
        setSubmitError(null);
        setSubmitSuccess(false);

        try {
            if (fulfillmentChoice !== 'diy' && fulfillmentChoice !== 'partner_kitchen') {
                setSubmitError('Choose how you want to receive your meal plan before submitting.');
                setSubmitting(false);
                return;
            }

            const payload = buildCraftPlanSubmissionPayload({
                craftKey,
                fulfillmentChoice,
                weekDuration,
                planWeeks: fulfillmentChoice === 'partner_kitchen' && (planWeeks === 1 || planWeeks === 4)
                    ? planWeeks
                    : 4,
                selectedDays: sortedSelectedDays,
                selectedByDay,
            });

            const result = await submitCraftPlan(payload, undefined, csrfToken);

            const redirectUrl =
                (typeof result.summary_url === 'string' && result.summary_url) ||
                summaryHref ||
                null;

            if (redirectUrl) {
                window.location.assign(redirectUrl);
                return;
            }

            setSubmitSuccess(true);
        } catch (error) {
            setSubmitError(error instanceof Error ? error.message : 'Could not save craft plan.');
        } finally {
            setSubmitting(false);
        }
    }

    const usesManualDaySelection = weekDuration !== 7;

    const totalCurationDays = sortedSelectedDays.length;
    // Screens: 1 receive → 2 craft → 3 duration → (4 choose days when not 7-day) → curation…
    const receiveScreen = 1;
    const craftScreen = 2;
    const durationScreen = 3;
    const manualDaysScreen = 4;
    const curationStartScreen = usesManualDaySelection ? 5 : 4;
    const finalScreen = curationStartScreen + totalCurationDays;
    const totalScreens =
        weekDuration !== null ? curationStartScreen + weekDuration : Math.max(finalScreen, screen);
    const isPartnerFulfillment = fulfillmentChoice === 'partner_kitchen';

    const isCurationScreen = screen >= curationStartScreen && screen <= curationStartScreen + totalCurationDays - 1;
    const curationIndex = isCurationScreen ? screen - curationStartScreen : -1; // 0..N-1
    const curationDay = isCurationScreen ? sortedSelectedDays[curationIndex] ?? null : null;

    // Partner Kitchen path: after payment, resume at Choose your meals (first curation day).
    useEffect(() => {
        if (!resumeChooseMeals) {
            return;
        }

        if (craftKey === null || weekDuration === null || sortedSelectedDays.length === 0) {
            setToast({
                message: 'Pick your craft and days again, then continue to Choose your meals.',
                visible: true,
            });
            return;
        }

        const first = sortedSelectedDays[0] ?? null;
        setActiveDay(first);
        setScreen(curationStartScreen);
    }, [resumeChooseMeals, craftKey, weekDuration, sortedSelectedDays, curationStartScreen]);

    const progressHeaderLabel = useMemo(() => {
        if (isCurationScreen && curationDay !== null) {
            return WEEKDAY_LONG[curationDay - 1] ?? '';
        }
        if (screen === receiveScreen) {
            return 'Receive meal plan';
        }
        if (screen === craftScreen) {
            return 'What’s Your Craft';
        }
        if (screen === durationScreen) {
            return 'Week duration';
        }
        if (screen === manualDaysScreen && usesManualDaySelection) {
            return 'Choose your days';
        }
        if (screen === finalScreen) {
            return isPartnerFulfillment ? 'Submit' : 'Submit';
        }

        return `Step ${screen} of ${totalScreens}`;
    }, [
        isCurationScreen,
        curationDay,
        screen,
        usesManualDaySelection,
        receiveScreen,
        craftScreen,
        durationScreen,
        manualDaysScreen,
        finalScreen,
        isPartnerFulfillment,
        totalScreens,
    ]);

    const progressSubLabel = useMemo(() => {
        if (!isCurationScreen || totalCurationDays <= 0) {
            return null;
        }

        return `${curationIndex + 1} of ${totalCurationDays}`;
    }, [isCurationScreen, totalCurationDays, curationIndex]);

    const calorieDay = curationDay ?? effectiveActiveDay;

    const adaptedMenuDay = useMemo(() => {
        if (calorieDay) {
            return calorieDay;
        }

        if (craftKey === 'full' && weekDuration !== null) {
            return sortedSelectedDays[0] ?? 1;
        }

        return null;
    }, [calorieDay, craftKey, weekDuration, sortedSelectedDays]);

    const isMenuPending = !disableAdaptedMenuFetch && liveMeals === null;

    const includeSoupForAdaptedMenu = useMemo(() => {
        if (!calorieDay) {
            return false;
        }

        return (selectedByDay[calorieDay]?.soup?.length ?? 0) > 0;
    }, [calorieDay, selectedByDay]);

    const soupCaloriesForAdaptedMenu = useMemo(() => {
        if (!includeSoupForAdaptedMenu || !calorieDay) {
            return undefined;
        }

        const soupId = selectedByDay[calorieDay]?.soup?.[0];

        if (soupId) {
            const fromLive = liveMeals?.find((meal) => meal.id === soupId);
            if (typeof fromLive?.baselineCalories === 'number' && fromLive.baselineCalories > 0) {
                return fromLive.baselineCalories;
            }
            if (typeof fromLive?.caloriesNumber === 'number' && fromLive.caloriesNumber > 0) {
                return fromLive.caloriesNumber;
            }

            const fromSchedule = scheduledSoupConsultationMealsForDay(scheduledSoupsByWeekday, calorieDay).find(
                (meal) => meal.id === soupId,
            );

            if (typeof fromSchedule?.baselineCalories === 'number' && fromSchedule.baselineCalories > 0) {
                return fromSchedule.baselineCalories;
            }

            if (typeof fromSchedule?.caloriesNumber === 'number' && fromSchedule.caloriesNumber > 0) {
                return fromSchedule.caloriesNumber;
            }
        }

        return 150;
    }, [includeSoupForAdaptedMenu, calorieDay, selectedByDay, liveMeals, scheduledSoupsByWeekday]);

    const dayFixedSelectionIds = useMemo(() => {
        if (!calorieDay) {
            return null;
        }

        const selections = selectedByDay[calorieDay];

        return {
            sideSaladId: selections?.sideSalads?.[0] ?? null,
            dessertId: selections?.desserts?.[0] ?? null,
            soupId: selections?.soup?.[0] ?? null,
        };
    }, [calorieDay, selectedByDay]);

    const selectedFixedBaselines = useMemo(() => {
        const lookup = (id) => {
            if (!id || !liveMeals) {
                return null;
            }

            const meal = liveMeals.find((entry) => entry.id === id);

            if (!meal) {
                return null;
            }

            return meal.baselineCalories ?? meal.caloriesNumber ?? null;
        };

        return {
            sideSalad: lookup(dayFixedSelectionIds?.sideSaladId),
            dessert: lookup(dayFixedSelectionIds?.dessertId),
            soup: lookup(dayFixedSelectionIds?.soupId),
        };
    }, [
        dayFixedSelectionIds?.sideSaladId,
        dayFixedSelectionIds?.dessertId,
        dayFixedSelectionIds?.soupId,
        liveMeals,
    ]);

    const adaptedMenuFetchKey = useMemo(() => {
        const scheduledDay = adaptedMenuDay
            ? scheduledFullCraftCategoryMealsForDay(scheduledFullCraftByWeekday, adaptedMenuDay)
            : null;
        const scheduledSideBaseline = scheduledDay?.sideSalads?.[0]?.baselineCalories
            ?? scheduledDay?.sideSalads?.[0]?.caloriesNumber
            ?? null;
        const scheduledDessertBaseline = scheduledDay?.desserts?.[0]?.baselineCalories
            ?? scheduledDay?.desserts?.[0]?.caloriesNumber
            ?? null;
        const scheduledSoupBaseline = scheduledDay?.soup?.[0]?.baselineCalories
            ?? scheduledDay?.soup?.[0]?.caloriesNumber
            ?? scheduledSoupConsultationMealsForDay(scheduledSoupsByWeekday, adaptedMenuDay ?? 0)[0]
                ?.baselineCalories
            ?? null;

        const selectedFixedSlots = selectedFixedSlotsFromSelections(selectedByDay[adaptedMenuDay]);

        const breakfastId = selectedByDay[adaptedMenuDay]?.breakfasts?.[0] ?? null;
        const selectedMainMealIds = selectedByDay[adaptedMenuDay]?.meals ?? [];

        return JSON.stringify({
            craftKey,
            mealLibraryRevision,
            includeSoup: includeSoupForAdaptedMenu,
            soupCalories: soupCaloriesForAdaptedMenu ?? null,
            selectedFixedSlots,
            sideSaladId: dayFixedSelectionIds?.sideSaladId ?? null,
            dessertId: dayFixedSelectionIds?.dessertId ?? null,
            soupId: dayFixedSelectionIds?.soupId ?? null,
            selectedSideBaseline: selectedFixedBaselines.sideSalad,
            selectedDessertBaseline: selectedFixedBaselines.dessert,
            selectedSoupBaseline: selectedFixedBaselines.soup,
            scheduledSideBaseline,
            scheduledDessertBaseline,
            scheduledSoupBaseline,
            dayOfWeek: adaptedMenuDay ?? null,
            breakfastId: breakfastId ?? null,
            selectedMainMealIds,
        });
    }, [
        craftKey,
        mealLibraryRevision,
        includeSoupForAdaptedMenu,
        soupCaloriesForAdaptedMenu,
        dayFixedSelectionIds,
        selectedFixedBaselines,
        adaptedMenuDay,
        selectedByDay,
        scheduledFullCraftByWeekday,
        scheduledSoupsByWeekday,
        catalogMeals,
    ]);

    const adaptedMenuFetchParams = useMemo(() => {
        if (!adaptedMenuDay || !craftKey) {
            return null;
        }

        const scheduledDay = scheduledFullCraftCategoryMealsForDay(scheduledFullCraftByWeekday, adaptedMenuDay);
        const scheduledSoups = scheduledSoupConsultationMealsForDay(scheduledSoupsByWeekday, adaptedMenuDay);
        const findScheduledMeal = (id, list) => list.find((meal) => meal.id === id);

        const mealFromBaseline = (id, baseline) => {
            if (!id || baseline === null) {
                return undefined;
            }

            return { id, baselineCalories: baseline, caloriesNumber: baseline };
        };

        const selectedFixedSlots = selectedFixedSlotsFromSelections(selectedByDay[adaptedMenuDay]);
        const planningFixedSlots =
            selectedFixedSlots.length > 0 ? selectedFixedSlots : undefined;

        const sideSaladMeal =
            planningFixedSlots?.includes('side_salad')
                ? mealFromBaseline(dayFixedSelectionIds?.sideSaladId, selectedFixedBaselines.sideSalad)
                    ?? (dayFixedSelectionIds?.sideSaladId
                        ? findScheduledMeal(dayFixedSelectionIds.sideSaladId, scheduledDay?.sideSalads ?? [])
                        : scheduledDay?.sideSalads?.[0])
                : undefined;
        const dessertMeal =
            planningFixedSlots?.includes('dessert')
                ? mealFromBaseline(dayFixedSelectionIds?.dessertId, selectedFixedBaselines.dessert)
                    ?? (dayFixedSelectionIds?.dessertId
                        ? findScheduledMeal(dayFixedSelectionIds.dessertId, scheduledDay?.desserts ?? [])
                        : scheduledDay?.desserts?.[0])
                : undefined;
        const soupMeal =
            planningFixedSlots?.includes('soup')
                ? mealFromBaseline(dayFixedSelectionIds?.soupId, selectedFixedBaselines.soup)
                    ?? (dayFixedSelectionIds?.soupId
                        ? findScheduledMeal(dayFixedSelectionIds.soupId, [
                              ...(scheduledDay?.soup ?? []),
                              ...scheduledSoups,
                          ])
                        : scheduledDay?.soup?.[0] ?? scheduledSoups[0])
                : undefined;

        const fixedPortion = fixedPortionCaloriesForAdapt(
            {
                sideSalads: sideSaladMeal ? [sideSaladMeal] : undefined,
                desserts: dessertMeal ? [dessertMeal] : undefined,
                soup: soupMeal ? [soupMeal] : undefined,
            },
            { selectedFixedSlots: planningFixedSlots },
        );

        return {
            craftKey,
            includeSoup: includeSoupForAdaptedMenu,
            selectedFixedSlots: planningFixedSlots,
            soupCalories: fixedPortion.soupCalories || undefined,
            sideSaladCalories: fixedPortion.sideSaladCalories || undefined,
            dessertCalories: fixedPortion.dessertCalories || undefined,
            dayOfWeek: adaptedMenuDay,
            selectedMainMealIds: selectedByDay[adaptedMenuDay]?.meals ?? [],
            selectedBreakfastMealIds: selectedByDay[adaptedMenuDay]?.breakfasts ?? [],
        };
    }, [
        adaptedMenuDay,
        craftKey,
        includeSoupForAdaptedMenu,
        soupCaloriesForAdaptedMenu,
        dayFixedSelectionIds,
        selectedFixedBaselines,
        scheduledFullCraftByWeekday,
        scheduledSoupsByWeekday,
        selectedByDay,
        catalogMeals,
    ]);

    const resolveMealDetailQueryString = useCallback(() => {
        const base = adaptedMenuFetchParams ?? {};
        // While a craft/tier switch is in flight, cards still show the previous schedule —
        // detail fetch must use that craft so API context matches on-screen cards.
        const detailCraftKey = wipeScheduleCacheOnNextSuccessRef.current
            ? (displayedScheduleCraftKeyRef.current ?? base.craftKey ?? craftKey ?? undefined)
            : (base.craftKey ?? craftKey ?? undefined);

        return buildAdaptedMenuQueryString({
            ...base,
            craftKey: detailCraftKey,
            dayOfWeek: base.dayOfWeek ?? calorieDay ?? undefined,
        });
    }, [adaptedMenuFetchParams, craftKey, calorieDay]);

    const { mealDetailModal, detailLoading, openMealDetail, closeMealDetail } = useMealDetailModal(
        mealDetailViewUrlTemplate,
        resolveMealDetailQueryString,
    );

    const openMealDetailWhenScheduleReady = useCallback(
        (meal) => {
            // Avoid opening details against a half-switched craft while the new schedule loads.
            if (wipeScheduleCacheOnNextSuccessRef.current && menuLoading) {
                return;
            }

            openMealDetail(meal);
        },
        [menuLoading, openMealDetail],
    );

    useEffect(() => {
        if (disableAdaptedMenuFetch || craftKey === null || adaptedMenuFetchParams === null) {
            return undefined;
        }

        const cacheIdentity = `${craftKey}|profile|${mealLibraryRevision}`;
        const dayKey = adaptedMenuDay != null ? String(adaptedMenuDay) : '';

        if (scheduleCacheIdentityRef.current !== cacheIdentity) {
            scheduleCacheIdentityRef.current = cacheIdentity;
            lastAdaptedMenuFetchKeyByDayRef.current = {};
            weekSeededDaysRef.current = new Set();
            hasLoadedAdaptedMenuRef.current = false;
            wipeScheduleCacheOnNextSuccessRef.current = true;
        }

        const dayHasSchedule =
            dayKey !== '' &&
            (scheduledFullCraftByWeekdayRef.current[adaptedMenuDay] != null ||
                scheduledFullCraftByWeekdayRef.current[dayKey] != null);

        const dayAlreadyCached =
            dayHasSchedule && lastAdaptedMenuFetchKeyByDayRef.current[dayKey] === adaptedMenuFetchKey;

        // Full-week seed already populated this weekday — open it from cache (no seal / no wait).
        const dayReadyFromWeekSeed =
            dayHasSchedule &&
            adaptedMenuDay != null &&
            weekSeededDaysRef.current.has(adaptedMenuDay);

        if (dayAlreadyCached) {
            return undefined;
        }

        if (dayReadyFromWeekSeed) {
            weekSeededDaysRef.current.delete(adaptedMenuDay);
            lastAdaptedMenuFetchKeyByDayRef.current[dayKey] = adaptedMenuFetchKey;
            return undefined;
        }

        let cancelled = false;
        const fetchGeneration = ++adaptedMenuFetchGenerationRef.current;
        // First load (and craft/tier wipe) builds the full week so Next days already have slots.
        const isFullWeekFetch = !hasLoadedAdaptedMenuRef.current;

        const timeoutId = window.setTimeout(() => {
            if (isFullWeekFetch) {
                setMenuLoading(true);
            }
            setMenuError(null);

            (async () => {
                try {
                    const fetchParams = isFullWeekFetch
                        ? { ...adaptedMenuFetchParams, dayOfWeek: undefined }
                        : adaptedMenuFetchParams;
                    const payload = await fetchAdaptedMenu(adaptedMenuUrl, fetchParams);
                    if (cancelled || fetchGeneration !== adaptedMenuFetchGenerationRef.current) {
                        return;
                    }
                    setLiveMeals(mapAdaptedMenuPayloadToConsultationMeals(payload));
                    setNutritionPlan(
                        payload.plan && typeof payload.plan === 'object'
                            ? /** @type {Record<string, unknown>} */ (payload.plan)
                            : null,
                    );

                    const incomingSoups =
                        payload.scheduled_soups_by_weekday && typeof payload.scheduled_soups_by_weekday === 'object'
                            ? /** @type {Record<string | number, unknown>} */ (payload.scheduled_soups_by_weekday)
                            : {};
                    const incomingFullCraft =
                        payload.scheduled_full_craft_by_weekday &&
                        typeof payload.scheduled_full_craft_by_weekday === 'object'
                            ? /** @type {Record<string | number, unknown>} */ (payload.scheduled_full_craft_by_weekday)
                            : {};

                    const replaceSchedule = wipeScheduleCacheOnNextSuccessRef.current;
                    wipeScheduleCacheOnNextSuccessRef.current = false;

                    setScheduledSoupsByWeekday((prev) =>
                        replaceSchedule || Object.keys(prev).length === 0
                            ? incomingSoups
                            : { ...prev, ...incomingSoups },
                    );
                    setScheduledFullCraftByWeekday((prev) =>
                        replaceSchedule || Object.keys(prev).length === 0
                            ? incomingFullCraft
                            : { ...prev, ...incomingFullCraft },
                    );

                    if (craftKey) {
                        displayedScheduleCraftKeyRef.current = craftKey;
                    }

                    setProductionMealPlanId(
                        typeof payload.production_meal_plan_id === 'number' ? payload.production_meal_plan_id : null,
                    );
                    hasLoadedAdaptedMenuRef.current = true;

                    if (isFullWeekFetch) {
                        // Mark every selected / returned weekday against this base schedule identity
                        // so day navigation can read slots from cache immediately.
                        const daysToCache = new Set(sortedSelectedDays);
                        for (const key of Object.keys(incomingFullCraft)) {
                            const dayNumber = Number(key);
                            if (dayNumber >= 1 && dayNumber <= 7) {
                                daysToCache.add(dayNumber);
                            }
                        }
                        const seededDays = new Set();
                        for (const day of daysToCache) {
                            lastAdaptedMenuFetchKeyByDayRef.current[String(day)] = adaptedMenuFetchKey;
                            // Keep the active first day on the normal adapt path (selection seeding).
                            if (day !== adaptedMenuDay) {
                                seededDays.add(day);
                            }
                        }
                        weekSeededDaysRef.current = seededDays;
                    } else if (dayKey !== '') {
                        lastAdaptedMenuFetchKeyByDayRef.current[dayKey] = adaptedMenuFetchKey;
                        if (adaptedMenuDay != null) {
                            weekSeededDaysRef.current.delete(adaptedMenuDay);
                        }
                    }
                } catch (error) {
                    if (!cancelled && fetchGeneration === adaptedMenuFetchGenerationRef.current) {
                        setMenuError(error instanceof Error ? error.message : 'Could not load meals.');
                    }
                } finally {
                    if (!cancelled && fetchGeneration === adaptedMenuFetchGenerationRef.current) {
                        setMenuLoading(false);
                    }
                }
            })();
        }, hasLoadedAdaptedMenuRef.current ? 200 : 0);

        return () => {
            cancelled = true;
            window.clearTimeout(timeoutId);
        };
    }, [
        adaptedMenuFetchKey,
        adaptedMenuUrl,
        disableAdaptedMenuFetch,
        craftKey,
        adaptedMenuFetchParams,
        adaptedMenuDay,
        mealLibraryRevision,
        sortedSelectedDays,
    ]);

    useEffect(() => {
        if (!craft) {
            return;
        }

        const craftIncludesBreakfast = craft.slots.some((slot) => slot.id === 'breakfast');

        if (!craftIncludesBreakfast) {
            setSelectedByDay((prev) => {
                let changed = false;
                /** @type {Record<number, import('../../consultation/consultationDraft.js').DaySelections>} */
                const next = { ...prev };

                for (const [dayKey, selections] of Object.entries(prev)) {
                    if ((selections?.breakfasts?.length ?? 0) === 0) {
                        continue;
                    }

                    next[Number(dayKey)] = { ...selections, breakfasts: [] };
                    changed = true;
                }

                return changed ? next : prev;
            });

            return;
        }

        if (sortedSelectedDays.length === 0) {
            return;
        }

        setSelectedByDay((prev) => {
            let changed = false;
            /** @type {Record<number, import('../../consultation/consultationDraft.js').DaySelections>} */
            const next = { ...prev };

            for (const day of sortedSelectedDays) {
                const current = next[day] ?? {
                    breakfasts: [],
                    meals: [],
                    sideSalads: [],
                    desserts: [],
                    soup: [],
                };

                const assigned = scheduledFullCraftCategoryMealsForDay(scheduledFullCraftByWeekday, day);
                const breakfastId = resolveProtocolBreakfastSeedId({
                    assignedBreakfasts: assigned?.breakfasts,
                    currentBreakfastId: current.breakfasts?.[0],
                    protectExisting: isBalancedAntiInflammatoryProtocol(dietProtocol),
                });

                if (!breakfastId) {
                    continue;
                }

                if (normalizeConsultationMealId(current.breakfasts?.[0]) === breakfastId) {
                    continue;
                }

                next[day] = { ...current, breakfasts: [breakfastId] };
                changed = true;
            }

            return changed ? next : prev;
        });
    }, [craft, sortedSelectedDays, scheduledFullCraftByWeekday, dietProtocol]);

    useEffect(() => {
        // Seed admin-recommended / primary-slot mains for every protocol (not only nutrient-dense).
        if (!craft || sortedSelectedDays.length === 0) {
            return;
        }

        const mealCap =
            craft.slots.find((slot) => slot.id === 'meal')?.count ??
            DEFAULT_FULL_CRAFT_MAX_SELECTIONS.meals ??
            2;

        if (mealCap <= 0) {
            return;
        }

        setSelectedByDay((prev) => {
            let changed = false;
            /** @type {Record<number, import('../../consultation/consultationDraft.js').DaySelections>} */
            const next = { ...prev };

            for (const day of sortedSelectedDays) {
                const current = next[day] ?? {
                    breakfasts: [],
                    meals: [],
                    sideSalads: [],
                    desserts: [],
                    soup: [],
                };

                if ((current.meals?.length ?? 0) >= mealCap) {
                    continue;
                }

                const assigned = scheduledFullCraftCategoryMealsForDay(scheduledFullCraftByWeekday, day);
                const mains = assigned?.meals ?? [];
                const recommended = mains.filter(
                    (meal) => meal?.isRecommended || meal?.is_recommended,
                );
                const byPrimarySlots = mains.filter((meal) => {
                    const slot = Number(meal?.plan_slot_index ?? meal?.planSlotIndex ?? 0);

                    return slot === 1 || slot === 5;
                });
                const seedSource =
                    recommended.length >= mealCap
                        ? recommended
                        : byPrimarySlots.length >= mealCap
                          ? byPrimarySlots
                          : recommended.length > 0
                            ? recommended
                            : mains.slice(0, mealCap);

                /** @type {string[]} */
                const preferredIds = seedSource
                    .map((meal) => normalizeConsultationMealId(meal?.id))
                    .filter((id) => id !== '');

                if (preferredIds.length === 0) {
                    continue;
                }

                const existingIds = (current.meals ?? [])
                    .map((id) => normalizeConsultationMealId(id))
                    .filter((id) => id !== '');
                const merged = [...existingIds];

                for (const id of preferredIds) {
                    if (merged.length >= mealCap) {
                        break;
                    }

                    if (!merged.includes(id)) {
                        merged.push(id);
                    }
                }

                if (merged.length < mealCap && preferredIds.length >= mealCap) {
                    // Prefer the recommended/primary ids over a stale partial pick.
                    merged.splice(0, merged.length, ...preferredIds.slice(0, mealCap));
                }

                if (merged.length === 0) {
                    continue;
                }

                const same =
                    merged.length === existingIds.length &&
                    merged.every((id, index) => id === existingIds[index]);

                if (same) {
                    continue;
                }

                next[day] = { ...current, meals: merged.slice(0, mealCap) };
                changed = true;
            }

            return changed ? next : prev;
        });
    }, [craft, sortedSelectedDays, scheduledFullCraftByWeekday]);

    const afternoonDessertKeptRef = useRef(/** @type {Set<number>} */ (new Set()));

    useEffect(() => {
        // Seed admin-recommended sides (1–2 of salad / dessert / soup) for every protocol.
        if (!craft || sortedSelectedDays.length === 0) {
            return;
        }

        if (!craft.slots.some((slot) => slot.id === 'fixedChoice')) {
            return;
        }

        const includeDefaultDessert = craftDefaultsDessert(basePlanTier, craft.key);

        setSelectedByDay((prev) => {
            let changed = false;
            /** @type {Record<number, import('../../consultation/consultationDraft.js').DaySelections>} */
            const next = { ...prev };

            for (const day of sortedSelectedDays) {
                let current = next[day] ?? {
                    breakfasts: [],
                    meals: [],
                    sideSalads: [],
                    desserts: [],
                    soup: [],
                };

                if (
                    !includeDefaultDessert &&
                    (current.desserts?.length ?? 0) > 0 &&
                    !afternoonDessertKeptRef.current.has(day)
                ) {
                    const assignedForClear = scheduledFullCraftCategoryMealsForDay(
                        scheduledFullCraftByWeekday,
                        day,
                    );
                    const adminDessert = (assignedForClear?.desserts ?? []).some(
                        (meal) => meal?.isRecommended || meal?.is_recommended,
                    );

                    if (!adminDessert) {
                        current = { ...current, desserts: [] };
                        next[day] = current;
                        changed = true;
                    }
                }

                const hasSides =
                    (current.sideSalads?.length ?? 0) > 0 ||
                    (current.desserts?.length ?? 0) > 0 ||
                    (current.soup?.length ?? 0) > 0;

                if (hasSides) {
                    continue;
                }

                const assigned = scheduledFullCraftCategoryMealsForDay(scheduledFullCraftByWeekday, day);
                if (!assigned) {
                    continue;
                }

                const adminRecommendedDessert = (assigned.desserts ?? []).some(
                    (meal) => meal?.isRecommended || meal?.is_recommended,
                );

                /** @type {import('../../consultation/consultationDraft.js').DaySelections} */
                const sideSeed = {
                    ...current,
                    sideSalads: [],
                    desserts: [],
                    soup: [],
                };

                let remaining = FIXED_CHOICE_REQUIRED_COUNT;

                const sideSeedKeys =
                    includeDefaultDessert || adminRecommendedDessert
                        ? /** @type {const} */ (['sideSalads', 'desserts', 'soup'])
                        : /** @type {const} */ (['sideSalads', 'soup']);

                for (const key of sideSeedKeys) {
                    if (remaining <= 0) {
                        break;
                    }

                    const recommended = (assigned[key] ?? [])
                        .filter((meal) => meal?.isRecommended || meal?.is_recommended)
                        .map((meal) => normalizeConsultationMealId(meal?.id))
                        .filter((id) => id !== '');

                    if (recommended.length > 0) {
                        sideSeed[key] = recommended.slice(0, 1);
                        remaining -= 1;
                    }
                }

                if (
                    sideSeed.sideSalads.length === 0 &&
                    sideSeed.desserts.length === 0 &&
                    sideSeed.soup.length === 0
                ) {
                    continue;
                }

                next[day] = sideSeed;
                changed = true;
            }

            return changed ? next : prev;
        });
    }, [craft, basePlanTier, sortedSelectedDays, scheduledFullCraftByWeekday]);

    const assignedMealsForCalorieDay = useMemo(() => {
        if (!calorieDay) {
            return null;
        }

        return scheduledFullCraftCategoryMealsForDay(scheduledFullCraftByWeekday, calorieDay);
    }, [calorieDay, scheduledFullCraftByWeekday]);

    const dayMacroTargets = useMemo(
        () =>
            dailyMacroTargetsFromPlan(
                nutritionPlan,
                planTierCalories,
                craft?.key ?? 'full',
            ),
        [nutritionPlan, planTierCalories, craft?.key],
    );

    const dayMacroTolerance = useMemo(
        () => dayMacroToleranceFromPlan(nutritionPlan),
        [nutritionPlan],
    );

    const progressPercent = totalScreens <= 1 ? 0 : ((screen - 1) / (totalScreens - 1)) * 100;

    const canGoNextFromCraft = craftKey !== null;
    const canGoNextFromDuration = isPartnerFulfillment
        ? weekDuration !== null && (planWeeks === 1 || planWeeks === 4) && selectedBilledDays !== null
        : weekDuration !== null;
    const canGoNextFromManualDays = weekDuration !== null && sortedSelectedDays.length === weekDuration;

    const partnerPlanLengthOptions = useMemo(() => {
        if (!craftKey || !partnerKitchenPricing || typeof partnerKitchenPricing !== 'object') {
            return [];
        }

        const options = partnerKitchenPricing.planLengthOptions?.[craftKey];

        return Array.isArray(options) ? options : [];
    }, [craftKey, partnerKitchenPricing]);

    function partnerDailyRateLabelFor(craftOptionKey) {
        if (!isPartnerFulfillment || !partnerKitchenPricing) {
            return null;
        }

        const label = partnerKitchenPricing.dailyRateLabels?.[craftOptionKey];

        return typeof label === 'string' ? label : null;
    }

    /**
     * @param {{ billedDays: number, planWeeks: number, weekDuration: number }} option
     */
    function selectPartnerPlanLength(option) {
        setSelectedBilledDays(option.billedDays);
        setPlanWeeks(option.planWeeks);
        setWeekDuration(option.weekDuration);
    }

    /**
     * @param {{ billedDays: number, amount: number }} option
     */
    function partnerPlanLengthPerDayLabel(option) {
        const billedDays = Number(option.billedDays);
        const amount = Number(option.amount);
        const perDay = billedDays > 0 && Number.isFinite(amount) ? amount / billedDays : 0;

        return `BHD ${perDay.toFixed(3)} / day`;
    }

    /**
     * @param {{ billedDays: number, amount: number, planWeeks: number }} option
     */
    function renderPartnerPlanLengthOption(option) {
        const active = selectedBilledDays === option.billedDays;
        const descriptionId = `plan-length-desc-${option.billedDays}`;

        return (
            <div key={`${option.planWeeks}-${option.billedDays}`} className="flex w-full flex-col">
                <OnboardingOptionButton
                    label={`${option.billedDays} days`}
                    detail={`BHD ${Number(option.amount).toFixed(0)}`}
                    selected={active}
                    onSelect={() => selectPartnerPlanLength(option)}
                    describedBy={descriptionId}
                />
                <div
                    className={[
                        'grid transition-[grid-template-rows] duration-300 ease-out motion-reduce:transition-none',
                        active ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]',
                    ].join(' ')}
                    aria-hidden={!active}
                >
                    <div className="min-h-0 overflow-hidden">
                        <OnboardingInlineDescription id={descriptionId}>
                            {partnerPlanLengthPerDayLabel(option)}
                            {option.planWeeks === 1
                                ? ' — trial pricing is higher per day than the 4 week plan.'
                                : ' for this plan length.'}
                        </OnboardingInlineDescription>
                    </div>
                </div>
            </div>
        );
    }

    /**
     * @param {number[]} [daysOverride] Pass explicit days when React state may still be stale (e.g. just set 7-day week).
     */
    async function continueAfterPlanSetup(daysOverride) {
        const daysForContinue =
            Array.isArray(daysOverride) && daysOverride.length > 0 ? daysOverride : selectedDays;

        // After payment, resume skips Partner checkout and opens Choose your meals.
        if (fulfillmentChoice === 'partner_kitchen' && !resumeChooseMeals) {
            if (!craftKey || weekDuration === null || daysForContinue.length === 0) {
                setToast({
                    message: 'Finish craft and week setup before continuing to checkout.',
                    visible: true,
                });
                return;
            }

            const draftPayload = {
                craftKey,
                weekDuration,
                planWeeks: planWeeks === 1 || planWeeks === 4 ? planWeeks : 4,
                selectedDays: daysForContinue,
                selectedByDay,
                businessSideChoiceByDay,
                afternoonSoupOptInByDay,
                fullCraftSoupOptInByDay,
                environmentYes,
                fulfillmentChoice: /** @type {'partner_kitchen'} */ ('partner_kitchen'),
            };

            saveConsultationDraft(draftPayload);

            try {
                await stashConsultationResumeDraft(draftPayload, csrfToken);
            } catch (error) {
                setToast({
                    message:
                        error instanceof Error
                            ? error.message
                            : 'Could not save your progress before checkout. Try again.',
                    visible: true,
                });
                return;
            }

            const separator = deliveryUrl.includes('?') ? '&' : '?';
            window.location.assign(`${deliveryUrl}${separator}resume=choose_meals`);
            return;
        }

        const first = daysForContinue[0] ?? null;
        setActiveDay(first);
        setScreen(curationStartScreen);
    }

    /** Weekly crafts share the default + SEE OTHER OPTIONS day view (no carousel). */
    const usesWeeklyCategoryLayout = useMemo(
        () => Boolean(craft && ['full', 'afternoon', 'day', 'intermittent', 'business'].includes(craft.key)),
        [craft],
    );

    const categoryMaxSelections = useMemo(() => {
        if (!craft) {
            return DEFAULT_FULL_CRAFT_MAX_SELECTIONS;
        }

        const mealSlot = craft.slots.find((slot) => slot.id === 'meal');
        const breakfastSlot = craft.slots.find((slot) => slot.id === 'breakfast');

        return {
            ...DEFAULT_FULL_CRAFT_MAX_SELECTIONS,
            breakfasts: breakfastSlot?.count ?? 0,
            meals: mealSlot?.count ?? DEFAULT_FULL_CRAFT_MAX_SELECTIONS.meals,
        };
    }, [craft]);

    /**
     * Single source of truth for weekly category carousels + footer totals.
     * Catalog and weekday schedule often share IDs with different adapted macros —
     * prefer schedule / assigned cards first (see buildWeeklyConsultationDisplayDecks).
     */
    const weeklyDisplayDecks = useMemo(() => {
        if (!usesWeeklyCategoryLayout || !calorieDay) {
            return null;
        }

        return buildWeeklyConsultationDisplayDecks({
            meals: catalogMeals,
            assignedMealsByCategory: assignedMealsForCalorieDay,
            scheduledSoupMeals: scheduledSoupForDay(calorieDay),
            soupCatalogMeals: catalogMeals,
            dietProtocol,
            includeBreakfast: (categoryMaxSelections.breakfasts ?? 0) > 0,
        });
    }, [
        usesWeeklyCategoryLayout,
        calorieDay,
        catalogMeals,
        assignedMealsForCalorieDay,
        scheduledSoupForDay,
        dietProtocol,
        categoryMaxSelections.breakfasts,
    ]);

    /**
     * First curation day keeps the looping seal until primary slots resolve on-screen —
     * schedule fetched, selections seeded, and deck cards available.
     */
    const firstCurationDayMealsReady = useMemo(() => {
        if (disableAdaptedMenuFetch || menuError) {
            return true;
        }

        if (liveMeals === null || menuLoading) {
            return false;
        }

        if (!curationDay) {
            return false;
        }

        if (!usesWeeklyCategoryLayout) {
            return true;
        }

        const assigned = scheduledFullCraftCategoryMealsForDay(scheduledFullCraftByWeekday, curationDay);

        if (assigned == null || !weeklyDisplayDecks) {
            return false;
        }

        const selections = selectedByDay[curationDay];
        const needsBreakfast = (categoryMaxSelections.breakfasts ?? 0) > 0;
        const scheduleHasBreakfast = (assigned.breakfasts?.length ?? 0) > 0;
        const needsMains = (categoryMaxSelections.meals ?? 0) > 0;
        const scheduleHasMains = (assigned.meals?.length ?? 0) > 0;

        if (needsBreakfast && scheduleHasBreakfast) {
            const resolvedBreakfasts = selectedMealsForSlotFromDecks(
                selections?.breakfasts ?? [],
                weeklyDisplayDecks.breakfasts,
                weeklyDisplayDecks,
            );
            if (resolvedBreakfasts.length === 0) {
                return false;
            }
        }

        if (needsMains && scheduleHasMains) {
            const resolvedMains = selectedMealsForSlotFromDecks(
                selections?.meals ?? [],
                weeklyDisplayDecks.meals,
                weeklyDisplayDecks,
            );
            if (resolvedMains.length === 0) {
                return false;
            }
        }

        return true;
    }, [
        disableAdaptedMenuFetch,
        menuError,
        liveMeals,
        menuLoading,
        curationDay,
        usesWeeklyCategoryLayout,
        scheduledFullCraftByWeekday,
        selectedByDay,
        weeklyDisplayDecks,
        categoryMaxSelections.breakfasts,
        categoryMaxSelections.meals,
    ]);

    const daySelectionsForCalorieDay = useMemo(() => {
        if (!calorieDay) {
            return {
                breakfasts: [],
                meals: [],
                sideSalads: [],
                desserts: [],
                soup: [],
            };
        }

        return (
            selectedByDay[calorieDay] ?? {
                breakfasts: [],
                meals: [],
                sideSalads: [],
                desserts: [],
                soup: [],
            }
        );
    }, [calorieDay, selectedByDay]);

    // Footer totals must match adapted meal cards (kitchen-real plate grams). Do not invent
    // a lower day total via client surplus math that never updates the cards.
    const dayMacroTotals = useMemo(() => {
        if (!weeklyDisplayDecks) {
            return { calories: 0, protein: 0, carbs: 0, fat: 0 };
        }

        return sumSelectedMacrosFromDisplayDecks(daySelectionsForCalorieDay, weeklyDisplayDecks);
    }, [weeklyDisplayDecks, daySelectionsForCalorieDay]);

    const dayCaloriesTotal = dayMacroTotals.calories;

    useEffect(() => {
        if (!usesWeeklyCategoryLayout) {
            return;
        }

        const maxMeals = categoryMaxSelections.meals ?? 2;

        setSelectedByDay((prev) => {
            let changed = false;
            /** @type {Record<number, import('../../consultation/consultationDraft.js').DaySelections>} */
            const next = { ...prev };

            for (const [dayKey, selections] of Object.entries(prev)) {
                const meals = selections?.meals ?? [];

                if (meals.length <= maxMeals) {
                    continue;
                }

                next[Number(dayKey)] = { ...selections, meals: meals.slice(0, maxMeals) };
                changed = true;
            }

            return changed ? next : prev;
        });
    }, [usesWeeklyCategoryLayout, categoryMaxSelections.meals]);

    const requiredSlotsByCraft = useMemo(() => {
        if (!craft) {
            return [];
        }
        if (craft.key === 'business') {
            return [
                { key: 'meal', label: 'Meal options', count: 1, selectionKey: 'meals' },
                { key: businessCraftChoice, label: 'Business choice', count: 1, selectionKey: businessCraftChoice === 'sidesalad' ? 'sideSalads' : businessCraftChoice === 'dessert' ? 'desserts' : 'soup' },
            ];
        }
        return craft.slots
            .filter((s) => s.id !== 'fixedChoice' && s.id !== 'breakfast')
            .map((s) => ({
            key: s.id,
            label:
                s.id === 'breakfast'
                    ? 'Breakfasts'
                    : s.id === 'meal'
                      ? 'Meal options'
                      : s.id === 'sidesalad'
                        ? 'Side salads'
                        : s.id === 'dessert'
                          ? 'Desserts'
                          : s.id === 'soup'
                            ? 'Soup'
                            : 'Slot',
            count: s.count,
            selectionKey: s.id === 'breakfast' ? 'breakfasts' : s.id === 'meal' ? 'meals' : s.id === 'sidesalad' ? 'sideSalads' : s.id === 'dessert' ? 'desserts' : 'soup',
            optional: Boolean(s.optional),
        }));
    }, [craft, businessCraftChoice]);

    const isCurationDayComplete = useMemo(() => {
        if (!craft || curationDay === null) {
            return false;
        }
        if (requiredSlotsByCraft.length === 0) {
            return false;
        }
        const s = selectedByDay[curationDay] ?? { breakfasts: [], meals: [], sideSalads: [], desserts: [], soup: [] };
        if (craft.key === 'business') {
            const side = businessSideChoiceByDay[curationDay] ?? 'soup';
            const hasMeal = (s.meals?.length ?? 0) === 1;
            const hasSide =
                side === 'soup'
                    ? (s.soup?.length ?? 0) === 1
                    : side === 'dessert'
                      ? (s.desserts?.length ?? 0) === 1
                      : (s.sideSalads?.length ?? 0) === 1;
            return hasMeal && hasSide;
        }

        const breakfastSlot = craft.slots.find((slot) => slot.id === 'breakfast');
        const hasBreakfast =
            !breakfastSlot || (s.breakfasts?.length ?? 0) === breakfastSlot.count;

        const scalableComplete = requiredSlotsByCraft.every(
            (slot) => (s[slot.selectionKey]?.length ?? 0) === slot.count,
        );
        const hasFixedChoiceSlot = craft.slots.some((slot) => slot.id === 'fixedChoice');

        return hasBreakfast && scalableComplete && (!hasFixedChoiceSlot || isFixedChoiceComplete(s));
    }, [craft, curationDay, requiredSlotsByCraft, selectedByDay, businessSideChoiceByDay]);

    const curationIncompleteMessage = useMemo(() => {
        if (!craft || curationDay === null) {
            return 'Select all required meals before continuing.';
        }

        const s = selectedByDay[curationDay] ?? { breakfasts: [], meals: [], sideSalads: [], desserts: [], soup: [] };

        if (craft.key === 'business') {
            const missing = [];
            if ((s.meals?.length ?? 0) < 1) {
                missing.push('main meal');
            }
            const side = businessSideChoiceByDay[curationDay] ?? 'soup';
            const sideLabel = side === 'soup' ? 'soup' : side === 'dessert' ? 'dessert' : 'side salad';
            const sideKey = side === 'soup' ? 'soup' : side === 'dessert' ? 'desserts' : 'sideSalads';
            if ((s[sideKey]?.length ?? 0) < 1) {
                missing.push(sideLabel);
            }
            if (missing.length === 0) {
                return 'Select all required meals before continuing.';
            }
            if (missing.length === 1) {
                return `Please select a ${missing[0]} before continuing.`;
            }
            return `Please select: ${missing.join(', ')}.`;
        }

        const missing = requiredSlotsByCraft
            .filter((slot) => (s[slot.selectionKey]?.length ?? 0) < slot.count)
            .map((slot) => slot.label.toLowerCase());

        if (craft.slots.some((slot) => slot.id === 'fixedChoice') && !isFixedChoiceComplete(s)) {
            missing.push('2 sides (side salad, dessert, or soup)');
        }

        if (missing.length === 0) {
            return 'Select all required meals before continuing.';
        }
        if (missing.length === 1) {
            return `Please select a ${missing[0]} before continuing.`;
        }

        return `Please select: ${missing.join(', ')}.`;
    }, [craft, curationDay, requiredSlotsByCraft, selectedByDay, businessSideChoiceByDay]);

    const mobileNavBarClass = embedInScrollParent
        ? 'mt-8 flex items-center justify-between gap-3 border-t border-gray-200/70 bg-[#F8F9F6] p-3 sm:mt-6 sm:border-t-0 sm:bg-transparent sm:p-0'
        : 'mt-8 flex items-center justify-between gap-3 fixed bottom-0 left-0 right-0 z-40 border-t border-gray-200/70 bg-[#F8F9F6]/95 p-3 backdrop-blur sm:static sm:mt-6 sm:border-t-0 sm:bg-transparent sm:p-0';

    const useDocumentScroll = embedInScrollParent || isCurationScreen;

    return (
        <div
            className={[
                PAGE_BG,
                'font-sans',
                useDocumentScroll
                    ? isCurationScreen && !embedInScrollParent
                        ? 'min-h-screen max-md:px-0 max-md:py-0 md:px-4 md:py-4 md:pb-8'
                        : 'w-full px-4 py-4 pb-8'
                    : 'min-h-screen px-4 py-4 pb-24 sm:pb-4 md:px-4',
            ].join(' ')}
        >
            <style>{`
              @keyframes mcShake {
                0%, 100% { transform: translateX(0); }
                20% { transform: translateX(-4px); }
                40% { transform: translateX(4px); }
                60% { transform: translateX(-3px); }
                80% { transform: translateX(3px); }
              }
              .mc-shake { animation: mcShake 260ms ease-in-out; }
            `}</style>
            <div className="mx-auto w-full max-w-[1100px] space-y-4">
                <div
                    className={[
                        'z-50 border-b border-gray-200/70 bg-[#F8F9F6]/95 px-4 pb-3 pt-2 backdrop-blur sm:pb-4',
                        embedInScrollParent
                            ? '-mx-4'
                            : isCurationScreen
                              ? 'sticky top-0'
                              : 'sticky top-0 -mx-4 md:-mx-8 md:px-8',
                    ].join(' ')}
                >
                    <div className="mx-auto max-w-[1100px]">
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <p className="mb-1 font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#555555]">
                                    {pageEyebrow}
                                </p>
                                <h1 className="font-montserrat text-[22px] font-bold tracking-tight text-[#262A22]">
                                    Crafted for YOU
                                </h1>
                            </div>
                            <Button
                                label="Close"
                                variant="ghost"
                                onClick={exitConsultation}
                            />
                        </div>

                        <div className="mt-3 sm:mt-4">
                            <div className="flex items-center justify-between gap-3 text-xs font-semibold tracking-tight text-[#555555]">
                                <span className={isCurationScreen ? 'font-montserrat text-sm font-bold text-[#262A22]' : ''}>
                                    {progressHeaderLabel}
                                </span>
                                {progressSubLabel ? <span>{progressSubLabel}</span> : null}
                            </div>
                            <div className="mt-2 h-1 w-full overflow-hidden rounded-full bg-gray-200 sm:h-1.5">
                                <div
                                    className="h-full rounded-full bg-[#5A6B44] transition-[width] duration-300 ease-out"
                                    style={{ width: `${progressPercent}%` }}
                                />
                            </div>
                        </div>

                    </div>
                </div>

                {!disableAdaptedMenuFetch && menuLoading && liveMeals === null ? (
                    <p className="mb-4 shrink-0 rounded-[12px] border border-gray-200 bg-white px-4 py-3 font-body text-sm text-[#555555]">
                        Loading your meal library with adapted portions…
                    </p>
                ) : null}
                {!disableAdaptedMenuFetch && menuError ? (
                    <p className="mb-4 shrink-0 rounded-[12px] border border-amber-200 bg-amber-50 px-4 py-3 font-body text-sm text-amber-900">
                        {menuError} Showing sample meals until your plan is available.
                        {loginUrl && /log in again/i.test(menuError) ? (
                            <>
                                {' '}
                                <a href={loginUrl} className="font-semibold underline underline-offset-2">
                                    Log in
                                </a>
                            </>
                        ) : null}
                    </p>
                ) : null}

                {/* Screen 1 — Receive meal plan (before craft) */}
                {screen === receiveScreen ? (
                    <section className="rounded-[12px] border border-gray-200 bg-white p-6 shadow-sm">
                        <h2 className="font-montserrat text-[16px] font-bold tracking-tight text-[#262A22]">
                            How would you like to receive your meal plan?
                        </h2>
                        <p className="mt-1 font-body text-sm text-[#555555]">
                            Choose between fresh daily preparation delivered to your door or self-prepared recipes at
                            home.
                        </p>

                        <FulfillmentOptionCards
                            className="mt-6"
                            deliveryUrl={deliveryUrl}
                            onContinue={(choice) => {
                                setFulfillmentChoice(choice);
                                setScreen(craftScreen);
                            }}
                        />

                        <div className={mobileNavBarClass}>
                            <Button
                                label="BACK"
                                variant="outline"
                                onClick={goBackFromPlanSetup}
                                className="px-10"
                            />
                        </div>
                    </section>
                ) : null}

                {/* Screen 2 — What’s Your Craft */}
                {screen === craftScreen ? (
                    <section className="rounded-[12px] border border-gray-200 bg-white p-6 shadow-sm">
                        <h2 className="font-montserrat text-[16px] font-bold tracking-tight text-[#262A22]">
                            What’s Your Craft
                        </h2>
                        <p className="mt-1 font-body text-sm text-[#555555]">
                            Choose the craft that matches how you want to eat this week.
                        </p>

                        <div className="mt-6 flex w-full flex-col gap-2.5">
                            {CRAFTS.map((c) => {
                                const active = c.key === craftKey;
                                const descriptionId = `craft-desc-${c.key}`;
                                const dailyPriceLabel = partnerDailyRateLabelFor(c.key);

                                return (
                                    <div key={c.key} className="flex w-full flex-col">
                                        <OnboardingOptionButton
                                            label={c.title}
                                            detail={dailyPriceLabel}
                                            selected={active}
                                            onSelect={() => setCraftKey(c.key)}
                                            describedBy={descriptionId}
                                        />
                                        <div
                                            className={[
                                                'grid transition-[grid-template-rows] duration-300 ease-out motion-reduce:transition-none',
                                                active ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]',
                                            ].join(' ')}
                                            aria-hidden={!active}
                                        >
                                            <div className="min-h-0 overflow-hidden">
                                                <OnboardingInlineDescription id={descriptionId}>
                                                    {c.description}
                                                </OnboardingInlineDescription>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>

                        <div className={mobileNavBarClass}>
                            <Button
                                label="BACK"
                                variant="outline"
                                onClick={() => setScreen(receiveScreen)}
                                className="px-10"
                            />
                            <Button
                                label="NEXT"
                                variant="primary"
                                disabled={!canGoNextFromCraft}
                                onClick={() => setScreen(durationScreen)}
                                className="px-10"
                            />
                        </div>
                    </section>
                ) : null}

                {/* Screen 3 — Week duration / Partner plan length */}
                {screen === durationScreen ? (
                    <section className="rounded-[12px] border border-gray-200 bg-white p-6 shadow-sm">
                        <h2 className="font-montserrat text-[16px] font-bold tracking-tight text-[#262A22]">
                            {isPartnerFulfillment ? 'Plan length' : 'Week duration'}
                        </h2>
                        <p className="mt-1 font-body text-sm text-[#555555]">
                            {isPartnerFulfillment
                                ? 'Choose how many days you want for this craft.'
                                : 'How many days are you crafting for this week?'}
                        </p>

                        {isPartnerFulfillment ? (
                            <div className="mt-6 flex w-full flex-col gap-5">
                                <div className="flex w-full flex-col gap-2.5">
                                    {partnerPlanLengthOptions
                                        .filter((option) => option.planWeeks === 4)
                                        .map((option) => renderPartnerPlanLengthOption(option))}
                                </div>
                                <div>
                                    <button
                                        type="button"
                                        className="font-montserrat text-sm font-semibold text-[#6E8C47] underline underline-offset-2 outline-none hover:text-[#556C37] focus-visible:ring-2 focus-visible:ring-[#6E8C47] focus-visible:ring-offset-2"
                                        aria-expanded={partnerTrialOpen}
                                        onClick={() => setPartnerTrialOpen((open) => !open)}
                                    >
                                        1 week trial
                                    </button>
                                    <div
                                        className={[
                                            'grid transition-[grid-template-rows] duration-300 ease-out motion-reduce:transition-none',
                                            partnerTrialOpen ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]',
                                        ].join(' ')}
                                        aria-hidden={!partnerTrialOpen}
                                    >
                                        <div className="min-h-0 overflow-hidden">
                                            <div className="mt-3 flex w-full flex-col gap-2.5">
                                                {partnerPlanLengthOptions
                                                    .filter((option) => option.planWeeks === 1)
                                                    .map((option) => renderPartnerPlanLengthOption(option))}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        ) : (
                            <div className="mt-6 rounded-[12px] border border-gray-200 bg-[#F8F9F6] p-5">
                                <div className="flex flex-wrap gap-2">
                                    {[5, 6, 7].map((n) => (
                                        <FoodFilterPill
                                            key={n}
                                            label={`${n} days`}
                                            isActive={weekDuration === n}
                                            onClick={() => {
                                                setWeekDuration(n);
                                                setPlanWeeks(null);
                                                setSelectedBilledDays(null);
                                            }}
                                        />
                                    ))}
                                </div>
                                <p className="mt-3 font-body text-xs text-[#555555]">
                                    {weekDuration === 7
                                        ? 'All seven days will be included automatically.'
                                        : `You’ll pick exactly ${weekDuration ?? '…'} active day(s) next.`}
                                </p>
                            </div>
                        )}

                        <div className={mobileNavBarClass}>
                            <Button
                                label="BACK"
                                variant="outline"
                                onClick={() => setScreen(craftScreen)}
                                className="px-10"
                            />
                            <Button
                                label="NEXT"
                                variant="primary"
                                disabled={!canGoNextFromDuration}
                                onClick={() => {
                                    if (weekDuration === 7) {
                                        const allDays = [1, 2, 3, 4, 5, 6, 7];
                                        setSelectedDays(allDays);
                                        setActiveDay(1);
                                        continueAfterPlanSetup(allDays);
                                        return;
                                    }

                                    setScreen(manualDaysScreen);
                                }}
                                className="px-10"
                            />
                        </div>
                    </section>
                ) : null}

                {/* Screen 4 — Manual Day Selection (5/6-day plans) */}
                {screen === manualDaysScreen && usesManualDaySelection ? (
                    <section className="rounded-[12px] border border-gray-200 bg-white p-6 shadow-sm">
                        <h2 className="font-montserrat text-[16px] font-bold tracking-tight text-[#262A22]">
                            Manual Day Selection
                        </h2>
                        <p className="mt-1 font-body text-sm text-[#555555]">
                            Choose which days of the week are active.
                        </p>

                        <div className="mt-5 rounded-[12px] border border-gray-200 bg-[#F8F9F6] p-5">
                            <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#555555]">
                                CHOOSE YOUR DAYS ({sortedSelectedDays.length}/{weekDuration ?? '…'} SELECTED)
                            </p>
                            <div className={['mt-3 flex flex-wrap gap-2', shakeOn ? 'mc-shake' : ''].join(' ')}>
                                {WEEKDAY_LABELS.map((label, i) => {
                                    const dayIdx = i + 1;
                                    const isOn = sortedSelectedDays.includes(dayIdx);
                                    return (
                                        <FoodFilterPill
                                            key={label}
                                            label={label}
                                            isActive={isOn}
                                            onClick={() => toggleDay(dayIdx)}
                                        />
                                    );
                                })}
                            </div>
                            <p className="mt-3 font-body text-xs text-[#555555]">
                                Selection must not exceed your Week Duration.
                            </p>
                        </div>

                        <div className={mobileNavBarClass}>
                            <Button
                                label="BACK"
                                variant="outline"
                                onClick={() => setScreen(durationScreen)}
                                className="px-10"
                            />
                            <Button
                                label="NEXT"
                                variant="primary"
                                disabled={!canGoNextFromManualDays}
                                onClick={() => {
                                    const first = sortedSelectedDays[0] ?? null;
                                    setActiveDay(first);
                                    continueAfterPlanSetup(sortedSelectedDays);
                                }}
                                className="px-10"
                            />
                        </div>
                    </section>
                ) : null}

                {/* Daily curation (one day per screen) */}
                {isCurationScreen ? (
                    <>
                        {curationDay ? (() => {
                            const day = curationDay;
                            const emptyDaySelections = {
                                breakfasts: [],
                                meals: [],
                                sideSalads: [],
                                desserts: [],
                                soup: [],
                            };

                            const daySelections = selectedByDay[day] ?? emptyDaySelections;

                            const toggle = (key, max) => (meal) => {
                                if (key === 'desserts') {
                                    afternoonDessertKeptRef.current.add(day);
                                }

                                setSelectedByDay((prev) => {
                                    const current = prev[day] ?? {
                                        breakfasts: [],
                                        meals: [],
                                        sideSalads: [],
                                        desserts: [],
                                        soup: [],
                                    };

                                    if (FIXED_CHOICE_CATEGORY_KEYS.includes(key)) {
                                        const { next, blocked } = applyFixedChoiceToggle(current, key, meal.id);

                                        if (blocked) {
                                            return prev;
                                        }

                                        return { ...prev, [day]: next };
                                    }

                                    const next = applyDeckSelectionToggle(current[key], meal.id, max);

                                    return { ...prev, [day]: { ...current, [key]: next } };
                                });
                            };

                            const toggleFixedChoice = (categoryKey, meal) => {
                                if (categoryKey === 'desserts') {
                                    afternoonDessertKeptRef.current.add(day);
                                }

                                setSelectedByDay((prev) => {
                                    const current = prev[day] ?? {
                                        breakfasts: [],
                                        meals: [],
                                        sideSalads: [],
                                        desserts: [],
                                        soup: [],
                                    };
                                    const { next, blocked } = applyFixedChoiceToggle(current, categoryKey, meal.id);

                                    if (blocked) {
                                        return prev;
                                    }

                                    return { ...prev, [day]: next };
                                });
                            };

                            const clearFixedChoiceCategory = (categoryKey) => {
                                setSelectedByDay((prev) => {
                                    const current = prev[day] ?? {
                                        breakfasts: [],
                                        meals: [],
                                        sideSalads: [],
                                        desserts: [],
                                        soup: [],
                                    };

                                    return {
                                        ...prev,
                                        [day]: { ...current, [categoryKey]: [] },
                                    };
                                });
                            };

                            return (
                            <div className="w-full">
                            <ChooseYourMeals
                                panelClassName="w-full"
                                documentScroll
                                soupCatalogMeals={catalogMeals}
                                dayName={WEEKDAY_LONG[curationDay - 1] ?? ''}
                                totalKcal={dayCaloriesTotal}
                                dayMacroTotals={dayMacroTotals}
                                dayMacroTargets={dayMacroTargets}
                                dayMacroTolerance={dayMacroTolerance}
                                nutritionPlan={nutritionPlan}
                                craftTitle={craft ? craft.title : ''}
                                targetCalories={planTierCalories}
                                dayCalorieTolerance={
                                    typeof nutritionPlan?.day_calorie_tolerance === 'number'
                                        ? nutritionPlan.day_calorie_tolerance
                                        : 50
                                }
                                layout={usesWeeklyCategoryLayout ? 'categories' : 'custom'}
                                meals={usesWeeklyCategoryLayout ? catalogMeals : []}
                                maxSelectionsByCategory={
                                    usesWeeklyCategoryLayout ? categoryMaxSelections : undefined
                                }
                                assignedMealsByCategory={
                                    usesWeeklyCategoryLayout
                                        ? assignedMealsForCalorieDay ?? undefined
                                        : undefined
                                }
                                displayDecks={
                                    usesWeeklyCategoryLayout ? weeklyDisplayDecks ?? undefined : undefined
                                }
                                categorySelections={usesWeeklyCategoryLayout ? daySelections : undefined}
                                onToggleCategory={
                                    usesWeeklyCategoryLayout
                                        ? (categoryKey, meal) => {
                                              if (
                                                  categoryKey === 'breakfasts' &&
                                                  !isBalancedAntiInflammatoryProtocol(dietProtocol)
                                              ) {
                                                  return;
                                              }

                                              if (FIXED_CHOICE_CATEGORY_KEYS.includes(categoryKey)) {
                                                  toggleFixedChoice(categoryKey, meal);
                                                  return;
                                              }

                                              const max =
                                                  categoryMaxSelections[categoryKey] ??
                                                  DEFAULT_FULL_CRAFT_MAX_SELECTIONS[categoryKey];

                                              toggle(categoryKey, max)(meal);
                                          }
                                        : undefined
                                }
                                onClearFixedChoiceCategory={
                                    usesWeeklyCategoryLayout ? clearFixedChoiceCategory : undefined
                                }
                                deckScopePrefix={usesWeeklyCategoryLayout ? String(curationDay) : ''}
                                onFooterBack={() => {
                                    if (screen === curationStartScreen) {
                                        setScreen(
                                            usesManualDaySelection ? manualDaysScreen : durationScreen,
                                        );
                                        return;
                                    }
                                    setScreen(screen - 1);
                                }}
                                onFooterNext={() => {
                                    setScreen(screen + 1);
                                }}
                                footerNextDisabled={!isCurationDayComplete}
                                footerIncompleteMessage={curationIncompleteMessage}
                                scheduledSoupMeals={scheduledSoupForDay(curationDay)}
                                onViewDetails={openMealDetailWhenScheduleReady}
                                dietProtocol={dietProtocol}
                                protocolSelectedLayout
                                isMenuPending={isMenuPending}
                                showSealLoading={curationIndex === 0 && !firstCurationDayMealsReady}
                            >
                                {usesWeeklyCategoryLayout ? null : (
                                <AnimatePresence mode="wait" initial={false}>
                                    <motion.div
                                        key={`curation-day-${curationDay}`}
                                        initial={{ x: 32, opacity: 0 }}
                                        animate={{ x: 0, opacity: 1 }}
                                        exit={{ x: -32, opacity: 0 }}
                                        transition={{ type: 'spring', stiffness: 240, damping: 30, mass: 0.8 }}
                                        className="space-y-8 overflow-visible"
                                    >
                                        {(() => {
                                            const selections = daySelections;
                                            const assignedForDay = scheduledFullCraftCategoryMealsForDay(
                                                scheduledFullCraftByWeekday,
                                                day,
                                            );

                                            const pickCards = (slotKey) => {
                                                if (slotKey === 'soup') {
                                                    return scheduledSoupForDay(day).map((meal) => meal);
                                                }

                                                if (slotKey === 'dessert') {
                                                    return consultationDessertDeckForDay(
                                                        catalogMeals,
                                                        assignedForDay?.desserts ?? [],
                                                        {
                                                            preferBakedDesserts:
                                                                isBalancedAntiInflammatoryProtocol(dietProtocol),
                                                        },
                                                    );
                                                }

                                                if (slotKey === 'sidesalad') {
                                                    return consultationSideSaladDeckForDay(
                                                        catalogMeals,
                                                        assignedForDay?.sideSalads ?? [],
                                                    );
                                                }

                                                // Same weekday mains as Full Craft — not the capped generic catalog deck.
                                                if (slotKey === 'meal' && (assignedForDay?.meals?.length ?? 0) > 0) {
                                                    return assignedForDay.meals;
                                                }

                                                return consultationDeckOptionsForSlotKey(catalogMeals, slotKey);
                                            };

                                            if (craft?.key === 'business') {
                                                const side = businessSideChoiceForDay(day);

                                                return (
                                                    <>
                                                        <MealSlotCarousel
                                                            title="Choose Your Meals of the Day"
                                                            deckScopeKey={`${day}-meal`}
                                                            cards={pickCards('meal')}
                                                            selectedIds={selections.meals}
                                                            maxSelected={1}
                                                            onSelect={toggle('meals', 1)}
                                                            onViewDetails={openMealDetailWhenScheduleReady}
                                                        />

                                                        <div className="rounded-[12px] border border-gray-200 bg-white p-4">
                                                            <p className="font-montserrat text-sm font-bold tracking-tight text-[#262A22]">
                                                                Choose your side
                                                            </p>
                                                            <p className="mt-1 font-body text-sm text-[#555555]">
                                                                Only one side carousel can be active at a time.
                                                            </p>
                                                            <div className="mt-3 flex flex-wrap gap-2">
                                                                {[
                                                                    { key: 'sidesalad', label: 'Side Salad' },
                                                                    { key: 'dessert', label: 'Dessert' },
                                                                    { key: 'soup', label: 'Soup' },
                                                                ].map((opt) => {
                                                                    const on = side === opt.key;
                                                                    return (
                                                                        <Button
                                                                            key={opt.key}
                                                                            label={opt.label}
                                                                            variant={on ? 'primary' : 'tab'}
                                                                            size="sm"
                                                                            onClick={() => setBusinessSideChoice(day, /** @type {any} */ (opt.key))}
                                                                        />
                                                                    );
                                                                })}
                                                            </div>
                                                        </div>

                                                        {side === 'soup' ? (
                                                            <MealSlotCarousel
                                                                title="Soup"
                                                                showSelectionSubheader
                                                                deckScopeKey={`${day}-soup`}
                                                                cards={pickCards('soup')}
                                                                selectedIds={selections.soup}
                                                                maxSelected={1}
                                                                onSelect={toggle('soup', 1)}
                                                                onViewDetails={openMealDetailWhenScheduleReady}
                                                            />
                                                        ) : side === 'dessert' ? (
                                                            <MealSlotCarousel
                                                                title="Dessert"
                                                                deckScopeKey={`${day}-dessert`}
                                                                cards={pickCards('dessert')}
                                                                selectedIds={selections.desserts}
                                                                maxSelected={1}
                                                                onSelect={toggle('desserts', 1)}
                                                                onViewDetails={openMealDetailWhenScheduleReady}
                                                            />
                                                        ) : (
                                                            <MealSlotCarousel
                                                                title="Side salad"
                                                                deckScopeKey={`${day}-sidesalad`}
                                                                cards={pickCards('sidesalad')}
                                                                selectedIds={selections.sideSalads}
                                                                maxSelected={1}
                                                                onSelect={toggle('sideSalads', 1)}
                                                                onViewDetails={openMealDetailWhenScheduleReady}
                                                            />
                                                        )}
                                                    </>
                                                );
                                            }

                                            const hasBreakfastSlot = craft?.slots.some((slot) => slot.id === 'breakfast');
                                            const breakfastCards =
                                                assignedForDay?.breakfasts?.length
                                                    ? assignedForDay.breakfasts
                                                    : consultationDeckOptionsForSlotKey(catalogMeals, 'breakfast');

                                            return (
                                                <>
                                                    {hasBreakfastSlot ? (
                                                        <MealSlotCarousel
                                                            title="Your Breakfast"
                                                            deckScopeKey={`${day}-breakfast`}
                                                            cards={breakfastCards}
                                                            selectedIds={selections.breakfasts}
                                                            maxSelected={1}
                                                            readOnly
                                                            showSelectedState
                                                            onSelect={() => {}}
                                                            onViewDetails={openMealDetailWhenScheduleReady}
                                                        />
                                                    ) : null}

                                                    {requiredSlotsByCraft.map((slot) => (
                                                        <MealSlotCarousel
                                                            key={slotId(day, slot.key, 1)}
                                                            title={slot.label}
                                                            deckScopeKey={`${day}-${slot.key}`}
                                                            cards={pickCards(slot.key)}
                                                            selectedIds={selections[slot.selectionKey]}
                                                            maxSelected={slot.count}
                                                            onSelect={toggle(slot.selectionKey, slot.count)}
                                                            onViewDetails={openMealDetailWhenScheduleReady}
                                                        />
                                                    ))}

                                                    {craft?.slots.some((slot) => slot.id === 'fixedChoice') ? (
                                                        <FixedChoicePicker
                                                            categorySelections={selections}
                                                            deckScopePrefix={String(day)}
                                                            meals={catalogMeals}
                                                            scheduledSoupMeals={scheduledSoupForDay(day)}
                                                            soupCatalogMeals={catalogMeals}
                                                            dietProtocol={dietProtocol}
                                                            onSelectMeal={toggleFixedChoice}
                                                            onViewDetails={openMealDetailWhenScheduleReady}
                                                            resolveCards={(_, selectionKey) => {
                                                                if (selectionKey === 'soup') {
                                                                    return pickCards('soup');
                                                                }

                                                                return pickCards(
                                                                    selectionKey === 'sideSalads'
                                                                        ? 'sidesalad'
                                                                        : 'dessert',
                                                                );
                                                            }}
                                                        />
                                                    ) : null}
                                                </>
                                            );
                                        })()}
                                    </motion.div>
                                </AnimatePresence>
                                )}
                            </ChooseYourMeals>
                            </div>
                            );
                        })() : (
                            <p className="rounded-[12px] border border-dashed border-gray-200 bg-[#F8F9F6] p-8 text-center font-body text-sm text-[#555555]">
                                Choose your days first.
                            </p>
                        )}
                    </>
                ) : null}

                {/* Final Screen — Partner: Environmental Check & Submit; DIY: Submit only */}
                {screen === finalScreen ? (
                    <section className="rounded-[12px] border border-gray-200 bg-white p-6 shadow-sm">
                        <h2 className="font-montserrat text-[16px] font-bold tracking-tight text-[#262A22]">
                            {isPartnerFulfillment ? 'Environmental Check & Submit' : 'Submit your meal plan'}
                        </h2>
                        <p className="mt-1 font-body text-sm text-[#555555]">
                            {isPartnerFulfillment
                                ? 'One last meaningful choice before the big finish.'
                                : 'Save your selections to open your cook-it-yourself plan.'}
                        </p>

                        {isPartnerFulfillment ? (
                            <div className="mt-6 rounded-[12px] border border-gray-200 bg-[#F8F9F6] p-5">
                                <p className="font-montserrat text-sm font-bold text-[#262A22]">
                                    Would You Like To Help Saving The Environment?
                                </p>
                                <div className="mt-3 flex flex-wrap items-center gap-3">
                                    <button
                                        type="button"
                                        className="inline-flex items-center gap-3"
                                        onClick={() => setEnvironmentYes((v) => !v)}
                                        aria-pressed={environmentYes}
                                    >
                                        <SquareCheckbox checked={environmentYes} presentational />
                                        <span className="font-montserrat text-sm font-bold text-[#262A22]">YES</span>
                                    </button>
                                    {environmentYes ? (
                                        <span className="font-montserrat text-sm font-bold text-[#C44F5D]">
                                            NO CUTLERY FOR ME
                                        </span>
                                    ) : null}
                                </div>
                            </div>
                        ) : null}

                        <div className={mobileNavBarClass}>
                            <Button
                                label="BACK"
                                variant="outline"
                                onClick={() => setScreen(screen - 1)}
                                className="px-10"
                            />
                            <Button
                                label="SUBMIT"
                                variant="primary"
                                onClick={submit}
                                disabled={submitting}
                                className="px-12 py-3 text-base"
                            />
                        </div>
                    </section>
                ) : null}
            </div>

            {submitting ? (
                <PhoneFramePortal>
                    {({ nested }) => (
                        <div
                            className={
                                nested
                                    ? 'absolute inset-0 z-[100] flex items-center justify-center p-4'
                                    : 'fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6'
                            }
                        >
                            <div className="absolute inset-0 bg-black/40" />
                            <div className="relative w-full max-w-full rounded-[12px] bg-white p-6 shadow-2xl">
                                <div className="flex flex-col items-center text-center">
                                    <div className="h-[120px] w-[120px] animate-pulse">
                                        <MealCraftLogoAnimatedIdentity variant="minimal-animated" />
                                    </div>
                                    <p className="mt-4 font-montserrat text-sm font-bold uppercase tracking-[0.18em] text-[#5A6B44]">
                                        Your meal plan is being crafted…
                                    </p>
                                    <p className="mt-2 font-body text-sm text-[#555555]">
                                        Please wait a moment.
                                    </p>
                                </div>
                            </div>
                        </div>
                    )}
                </PhoneFramePortal>
            ) : null}

            {submitSuccess ? (
                <PhoneFramePortal>
                    {({ nested }) => (
                        <div
                            className={
                                nested
                                    ? 'absolute inset-x-0 bottom-0 z-[110] px-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]'
                                    : 'fixed bottom-6 left-1/2 z-[110] w-full max-w-[430px] -translate-x-1/2 px-4'
                            }
                        >
                            <div className="rounded-[12px] border border-[#5A6B44]/30 bg-white px-4 py-3 shadow-lg">
                                <p className="font-body text-sm font-semibold text-[#262A22]">
                                    Your craft plan was saved. The kitchen can now produce your selections.
                                </p>
                                {homeHref ? (
                                    <button
                                        type="button"
                                        className="mt-3 font-montserrat text-sm font-bold text-[#5A6B44] underline-offset-2 hover:underline"
                                        onClick={() =>
                                            window.location.assign(summaryHref || homeHref)
                                        }
                                    >
                                        {summaryHref
                                            ? 'View your meal plan summary'
                                            : 'Back to your plan'}
                                    </button>
                                ) : null}
                            </div>
                        </div>
                    )}
                </PhoneFramePortal>
            ) : null}

            {submitError ? (
                <PhoneFramePortal>
                    {({ nested }) => (
                        <div
                            className={
                                nested
                                    ? 'absolute inset-x-0 bottom-0 z-[110] px-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]'
                                    : 'fixed bottom-6 left-1/2 z-[110] w-full max-w-[430px] -translate-x-1/2 px-4'
                            }
                        >
                            <div className="rounded-[12px] border border-red-200 bg-red-50 px-4 py-3 shadow-lg">
                                <p className="font-body text-sm text-red-800">
                                    {submitError}
                                    {loginUrl && /log in again/i.test(submitError) ? (
                                        <>
                                            {' '}
                                            <a
                                                href={loginUrl}
                                                className="font-semibold underline underline-offset-2"
                                            >
                                                Log in
                                            </a>
                                        </>
                                    ) : null}
                                </p>
                            </div>
                        </div>
                    )}
                </PhoneFramePortal>
            ) : null}

            {toast.visible ? (
                <PhoneFramePortal>
                    {({ nested }) => (
                        <div
                            className={
                                nested
                                    ? 'absolute inset-x-0 bottom-0 z-[110] px-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]'
                                    : 'fixed bottom-6 left-1/2 z-[110] w-full max-w-[430px] -translate-x-1/2 px-4'
                            }
                        >
                            <div className="rounded-[12px] border border-gray-200 bg-white px-4 py-3 shadow-lg">
                                <p className="font-body text-sm text-[#555555]">{toast.message}</p>
                            </div>
                        </div>
                    )}
                </PhoneFramePortal>
            ) : null}

            <MealDetailModalPortal
                mealDetailModal={mealDetailModal}
                loading={detailLoading}
                onClose={closeMealDetail}
                hideInstructions={isPartnerFulfillment}
                inlineIngredients={isPartnerFulfillment}
            />
        </div>
    );
}

