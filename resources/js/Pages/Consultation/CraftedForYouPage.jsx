import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import Button from '../../Components/Atoms/Button.jsx';
import PillButton from '../../Components/Atoms/Button/Button.jsx';
import ChooseYourMeals, {
    DEFAULT_FULL_CRAFT_MAX_SELECTIONS,
    MealSlotCarousel,
    buildConsultationDeckCatalog,
    consultationDeckOptionsForSlotKey,
    soupOfTheDayMeals,
} from '../../Components/Consultation/ChooseYourMeals.jsx';
import SquareCheckbox from '../../Components/Atoms/Icons/SquareCheckbox.jsx';
import { MealCraftLogoAnimatedIdentity } from '../../Components/Atoms/Logo/MealCraftLogoAnimated.jsx';
import { AnimatePresence, motion } from 'framer-motion';
import {
    fetchAdaptedMenu,
    mapAdaptedMenuPayloadToConsultationMeals,
    scheduledSoupConsultationMealsForDay,
} from '../../consultation/mapAdaptedMenuMeals.js';
import { buildCraftPlanSubmissionPayload, submitCraftPlan } from '../../consultation/submitCraftPlan.js';
import { craftDayCaloriesForKey } from '../../consultation/craftCalorieTargets.js';

const PAGE_BG = 'bg-[#F8F9F6]';
const WEEKDAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const WEEKDAY_LONG = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

const CRAFTS = [
    {
        key: 'full',
        title: 'Full Craft',
        description: '1 Breakfast, 2 Main Meals, 1 Side Salad, 1 Dessert, Soup of the Day (optional)',
        slots: [
            { id: 'breakfast', label: 'Breakfast', count: 1 },
            { id: 'meal', label: 'Meals', count: 2 },
            { id: 'sidesalad', label: 'Side salad', count: 1 },
            { id: 'dessert', label: 'Dessert', count: 1 },
            { id: 'soup', label: 'Soup of the Day', count: 1, optional: true },
        ],
    },
    {
        key: 'day',
        title: 'Day Craft',
        description: 'Breakfast, 1 Meal, Side Salad, Dessert & Soup (optional)',
        slots: [
            { id: 'breakfast', label: 'Breakfast', count: 1 },
            { id: 'meal', label: 'Meal', count: 1 },
            { id: 'sidesalad', label: 'Side salad', count: 1 },
            { id: 'dessert', label: 'Dessert', count: 1 },
            { id: 'soup', label: 'Soup for this day', count: 1, optional: true },
        ],
    },
    {
        key: 'afternoon',
        title: 'Afternoon Craft',
        description: '2 Meals, Side Salad, Dessert',
        slots: [
            { id: 'meal', label: 'Meals', count: 2 },
            { id: 'sidesalad', label: 'Side salad', count: 1 },
            { id: 'dessert', label: 'Dessert', count: 1 },
        ],
    },
    {
        key: 'intermittent',
        title: 'Intermittent Craft',
        description: '1 Soup, 1 Meal, Side Salad, Dessert',
        slots: [
            { id: 'soup', label: 'Soup', count: 1 },
            { id: 'meal', label: 'Meal', count: 1 },
            { id: 'sidesalad', label: 'Side salad', count: 1 },
            { id: 'dessert', label: 'Dessert', count: 1 },
        ],
    },
    {
        key: 'business',
        title: 'Business Craft',
        description: '1 Meal + (Soup OR Side Salad OR Dessert)',
        slots: [
            { id: 'meal', label: 'Meal', count: 1 },
            { id: 'choice', label: 'Business choice', count: 1, choice: ['soup', 'sidesalad', 'dessert'] },
        ],
    },
];

const MOCK_MEALS = [
    {
        id: 'meal-1',
        title: 'Egg white veggie scramble',
        imageUrl: 'https://images.unsplash.com/photo-1525351484163-7529414344d8?auto=format&fit=crop&w=900&q=80',
        mealType: 'Breakfast',
        category: 'Breakfast',
        prepMinutes: 18,
        macros: { calories: 312, protein: '28g', carbs: '12g', fat: '16g' },
        tags: [
            { label: 'Breakfast', type: 'category' },
            { label: 'Keto', type: 'dietary' },
            { label: 'High Protein', type: 'dietary' },
        ],
        nutrientHighlights: ['B12', 'Iron'],
        caloriesNumber: 312,
    },
    {
        id: 'meal-1b',
        title: 'Chia protein overnight oats',
        imageUrl: 'https://images.unsplash.com/photo-1511690743698-d9d85f2fbf38?auto=format&fit=crop&w=900&q=80',
        mealType: 'Breakfast',
        category: 'Breakfast',
        prepMinutes: 8,
        macros: { calories: 380, protein: '26g', carbs: '44g', fat: '11g' },
        tags: [
            { label: 'Breakfast', type: 'category' },
            { label: 'Balanced', type: 'dietary' },
            { label: 'High Protein', type: 'dietary' },
        ],
        nutrientHighlights: ['Magnesium'],
        caloriesNumber: 380,
    },
    {
        id: 'meal-2',
        title: 'Post-workout salmon bowl',
        imageUrl: 'https://images.unsplash.com/photo-1553621042-f6e147245754?auto=format&fit=crop&w=900&q=80',
        mealType: 'Meal',
        category: 'Meal',
        prepMinutes: 22,
        macros: { calories: 520, protein: '44g', carbs: '38g', fat: '22g' },
        tags: [
            { label: 'Meal', type: 'category' },
            { label: 'Balanced', type: 'dietary' },
            { label: 'High Protein', type: 'dietary' },
        ],
        nutrientHighlights: ['Magnesium', 'Zinc'],
        caloriesNumber: 520,
    },
    {
        id: 'meal-2b',
        title: 'Herb chicken quinoa plate',
        imageUrl: 'https://images.unsplash.com/photo-1604908177225-6b9dd98ec605?auto=format&fit=crop&w=900&q=80',
        mealType: 'Meal',
        category: 'Meal',
        prepMinutes: 25,
        macros: { calories: 610, protein: '48g', carbs: '58g', fat: '19g' },
        tags: [
            { label: 'Meal', type: 'category' },
            { label: 'Balanced', type: 'dietary' },
            { label: 'High Protein', type: 'dietary' },
        ],
        nutrientHighlights: ['Zinc'],
        caloriesNumber: 610,
    },
    {
        id: 'meal-2c',
        title: 'Ketogenic steak + greens',
        imageUrl: 'https://images.unsplash.com/photo-1551183053-bf91a1d81141?auto=format&fit=crop&w=900&q=80',
        mealType: 'Meal',
        category: 'Meal',
        prepMinutes: 28,
        macros: { calories: 720, protein: '55g', carbs: '14g', fat: '46g' },
        tags: [
            { label: 'Meal', type: 'category' },
            { label: 'Ketogenic', type: 'dietary' },
            { label: 'High Protein', type: 'dietary' },
        ],
        nutrientHighlights: ['Iron'],
        caloriesNumber: 720,
    },
    {
        id: 'meal-2d',
        title: 'Hormone Feast turkey + sweet potato',
        imageUrl: 'https://images.unsplash.com/photo-1544025162-d76694265947?auto=format&fit=crop&w=900&q=80',
        mealType: 'Meal',
        category: 'Meal',
        prepMinutes: 26,
        macros: { calories: 640, protein: '46g', carbs: '62g', fat: '20g' },
        tags: [
            { label: 'Meal', type: 'category' },
            { label: 'Hormone Feast', type: 'dietary' },
        ],
        nutrientHighlights: ['B12'],
        caloriesNumber: 640,
    },
    {
        id: 'meal-3',
        title: 'Side salad crunch bowl',
        imageUrl: 'https://images.unsplash.com/photo-1540189549336-e6e99c3679fe?auto=format&fit=crop&w=900&q=80',
        mealType: 'Side salad',
        category: 'Side salad',
        prepMinutes: 10,
        macros: { calories: 260, protein: '8g', carbs: '18g', fat: '17g' },
        tags: [
            { label: 'Side salad', type: 'category' },
            { label: 'Vegan', type: 'dietary' },
        ],
        nutrientHighlights: ['Folate'],
        caloriesNumber: 260,
    },
    {
        id: 'meal-3b',
        title: 'Side salad — citrus kale',
        imageUrl: 'https://images.unsplash.com/photo-1490645935967-10de6ba17061?auto=format&fit=crop&w=900&q=80',
        mealType: 'Side salad',
        category: 'Side salad',
        prepMinutes: 9,
        macros: { calories: 240, protein: '7g', carbs: '20g', fat: '15g' },
        tags: [
            { label: 'Side salad', type: 'category' },
            { label: 'Vegan', type: 'dietary' },
        ],
        nutrientHighlights: ['Folate'],
        caloriesNumber: 240,
    },
    {
        id: 'meal-4',
        title: 'Soup of the day — lentil',
        imageUrl: 'https://images.unsplash.com/photo-1604908554238-3f9b9b0c4b6c?auto=format&fit=crop&w=900&q=80',
        mealType: 'Soup',
        category: 'Soup',
        prepMinutes: 16,
        macros: { calories: 430, protein: '26g', carbs: '62g', fat: '10g' },
        tags: [
            { label: 'Soup', type: 'category' },
            { label: 'Sickle Cell Anemia', type: 'dietary' },
        ],
        nutrientHighlights: ['Iron', 'Magnesium'],
        caloriesNumber: 430,
    },
    {
        id: 'meal-5',
        title: 'Dessert — yogurt berries',
        imageUrl: 'https://images.unsplash.com/photo-1499636136210-6f4ee915583e?auto=format&fit=crop&w=900&q=80',
        mealType: 'Dessert',
        category: 'Dessert',
        prepMinutes: 6,
        macros: { calories: 220, protein: '12g', carbs: '24g', fat: '8g' },
        tags: [
            { label: 'Dessert', type: 'category' },
            { label: 'Balanced', type: 'dietary' },
        ],
        nutrientHighlights: ['B12'],
        caloriesNumber: 220,
    },
    {
        id: 'meal-5b',
        title: 'Dessert — cacao chia mousse',
        imageUrl: 'https://images.unsplash.com/photo-1495147466023-ac5c588e2e94?auto=format&fit=crop&w=900&q=80',
        mealType: 'Dessert',
        category: 'Dessert',
        prepMinutes: 7,
        macros: { calories: 260, protein: '10g', carbs: '28g', fat: '13g' },
        tags: [
            { label: 'Dessert', type: 'category' },
            { label: 'Balanced', type: 'dietary' },
        ],
        nutrientHighlights: ['Magnesium'],
        caloriesNumber: 260,
    },
];

/** Consultation mock library: shared with Storybook deck stories (`consultationMeals`). */
export const consultationMeals = MOCK_MEALS;

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
 *   pageEyebrow?: string;
 *   adaptedMenuUrl?: string;
 *   initialPlanTier?: number | null;
 *   disableAdaptedMenuFetch?: boolean;
 * }} [props]
 */
export default function CraftedForYouPage({
    closeHref,
    homeHref,
    pageEyebrow = 'Admin / Consultation',
    adaptedMenuUrl = '/api/menu/adapted',
    initialPlanTier = null,
    disableAdaptedMenuFetch = false,
} = {}) {
    const [screen, setScreen] = useState(1);
    const [craftKey, setCraftKey] = useState(/** @type {string|null} */ (null));
    const craft = useMemo(() => (craftKey ? craftByKey(craftKey) : null), [craftKey]);

    const [weekDuration, setWeekDuration] = useState(/** @type {number|null} */ (null));
    const [selectedDays, setSelectedDays] = useState(/** @type {number[]} */ ([]));
    const [activeDay, setActiveDay] = useState(/** @type {number|null} */ (null));
    const [toast, setToast] = useState(/** @type {{ message: string; visible: boolean }} */ ({ message: '', visible: false }));
    const [shakeOn, setShakeOn] = useState(false);

    const [environmentYes, setEnvironmentYes] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [submitError, setSubmitError] = useState(/** @type {string | null} */ (null));
    const [submitSuccess, setSubmitSuccess] = useState(false);

    // Craft-specific choice selection (per active curation day)
    const [businessCraftChoice, setBusinessCraftChoice] = useState(/** @type {'soup'|'sidesalad'|'dessert'} */ ('soup'));
    const [businessSideChoiceByDay, setBusinessSideChoiceByDay] = useState(
        /** @type {Record<number, 'soup'|'sidesalad'|'dessert'>} */ ({}),
    );

    // Slot selection state (chosen meal ids per category, per day)
    const [selectedByDay, setSelectedByDay] = useState(
        /** @type {Record<number, { breakfasts: string[]; meals: string[]; sideSalads: string[]; desserts: string[]; soup: string[] }>} */ ({}),
    );

    const [liveMeals, setLiveMeals] = useState(/** @type {typeof MOCK_MEALS | null} */ (null));
    const [nutritionPlan, setNutritionPlan] = useState(/** @type {Record<string, unknown> | null} */ (null));
    const [scheduledSoupsByWeekday, setScheduledSoupsByWeekday] = useState(
        /** @type {Record<string | number, unknown>} */ ({}),
    );
    const [menuLoading, setMenuLoading] = useState(!disableAdaptedMenuFetch);
    const [menuError, setMenuError] = useState(/** @type {string | null} */ (null));

    const catalogMeals = useMemo(() => liveMeals ?? MOCK_MEALS, [liveMeals]);

    /** Capped option sets only — mirrors pre–live-API MOCK_MEALS deck (2 / 4 / 2 / 2). */
    const consultationDeckMeals = useMemo(
        () => buildConsultationDeckCatalog(catalogMeals),
        [catalogMeals],
    );

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

        return 1200;
    }, [nutritionPlan, initialPlanTier]);

    const planTierCalories = useMemo(() => {
        const fromCraft = nutritionPlan?.craft_day_calories;
        if (typeof fromCraft === 'number' && fromCraft > 0) {
            return Math.round(fromCraft);
        }

        if (craftKey) {
            return craftDayCaloriesForKey(craftKey, basePlanTier);
        }

        return basePlanTier;
    }, [nutritionPlan, craftKey, basePlanTier]);

    useEffect(() => {
        if (disableAdaptedMenuFetch || craftKey === null) {
            return undefined;
        }

        let cancelled = false;

        (async () => {
            setMenuLoading(true);
            setMenuError(null);
            try {
                const payload = await fetchAdaptedMenu(adaptedMenuUrl, { craftKey });
                if (cancelled) {
                    return;
                }
                setLiveMeals(mapAdaptedMenuPayloadToConsultationMeals(payload));
                setNutritionPlan(
                    payload.plan && typeof payload.plan === 'object'
                        ? /** @type {Record<string, unknown>} */ (payload.plan)
                        : null,
                );
                setScheduledSoupsByWeekday(
                    payload.scheduled_soups_by_weekday && typeof payload.scheduled_soups_by_weekday === 'object'
                        ? /** @type {Record<string | number, unknown>} */ (payload.scheduled_soups_by_weekday)
                        : {},
                );
            } catch (error) {
                if (!cancelled) {
                    setMenuError(error instanceof Error ? error.message : 'Could not load meals.');
                }
            } finally {
                if (!cancelled) {
                    setMenuLoading(false);
                }
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [adaptedMenuUrl, disableAdaptedMenuFetch, craftKey]);

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
        // If the user is currently on the manual day selection screen, leap into Day 1 (Sunday).
        if (screen === 2) {
            const all = [1, 2, 3, 4, 5, 6, 7];
            const alreadyAllDays =
                selectedDays.length === 7 && all.every((d) => selectedDays.includes(d));

            if (!alreadyAllDays) {
                setSelectedDays(all);
            }
            if (activeDay !== 1) {
                setActiveDay(1);
            }
        }
    }, [weekDuration, screen, selectedDays, activeDay]);

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

    /** `lg` breakpoint: week duration lives on plan setup. Below `lg`, duration is on manual day selection — NEXT only needs a craft. */
    const [isLgViewport, setIsLgViewport] = useState(/** @type {boolean | null} */ (null));
    useEffect(() => {
        const mq = window.matchMedia('(min-width: 1024px)');
        const fn = () => setIsLgViewport(mq.matches);
        fn();
        mq.addEventListener('change', fn);
        return () => mq.removeEventListener('change', fn);
    }, []);

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

    async function submit() {
        if (!craftKey || weekDuration === null) {
            return;
        }

        setSubmitting(true);
        setSubmitError(null);
        setSubmitSuccess(false);

        try {
            const payload = buildCraftPlanSubmissionPayload({
                craftKey,
                weekDuration,
                selectedDays: sortedSelectedDays,
                selectedByDay,
            });

            await submitCraftPlan(payload);
            setSubmitSuccess(true);
        } catch (error) {
            setSubmitError(error instanceof Error ? error.message : 'Could not save craft plan.');
        } finally {
            setSubmitting(false);
        }
    }

    const usesManualDaySelection = weekDuration !== 7;

    const totalCurationDays = sortedSelectedDays.length;
    const curationStartScreen = usesManualDaySelection ? 3 : 2; // skip manual-day screen for 7-day plans
    const finalScreen = curationStartScreen + totalCurationDays;
    const totalScreens =
        weekDuration !== null ? curationStartScreen + weekDuration : Math.max(finalScreen, screen);

    const isCurationScreen = screen >= curationStartScreen && screen <= curationStartScreen + totalCurationDays - 1;
    const curationIndex = isCurationScreen ? screen - curationStartScreen : -1; // 0..N-1
    const curationDay = isCurationScreen ? sortedSelectedDays[curationIndex] ?? null : null;

    const progressHeaderLabel = useMemo(() => {
        if (isCurationScreen && curationDay !== null) {
            return WEEKDAY_LONG[curationDay - 1] ?? '';
        }
        if (screen === 1) {
            return 'Plan setup';
        }
        if (screen === 2 && usesManualDaySelection) {
            return 'Choose your days';
        }
        if (screen === finalScreen) {
            return 'Submit';
        }

        return `Step ${screen} of ${totalScreens}`;
    }, [
        isCurationScreen,
        curationDay,
        screen,
        usesManualDaySelection,
        finalScreen,
        totalScreens,
    ]);

    const progressSubLabel = useMemo(() => {
        if (!isCurationScreen || totalCurationDays <= 0) {
            return null;
        }

        return `${curationIndex + 1} of ${totalCurationDays}`;
    }, [isCurationScreen, totalCurationDays, curationIndex]);

    const calorieDay = curationDay ?? effectiveActiveDay;

    const selectedMealCardsForDay = useMemo(() => {
        if (!calorieDay) {
            return [];
        }
        const byId = new Map(catalogMeals.map((m) => [m.id, m]));
        const s = selectedByDay[calorieDay] ?? {
            breakfasts: [],
            meals: [],
            sideSalads: [],
            desserts: [],
            soup: [],
        };
        const ids = [...s.breakfasts, ...s.meals, ...s.sideSalads, ...s.desserts, ...s.soup];
        return ids.map((id) => byId.get(id)).filter(Boolean);
    }, [calorieDay, selectedByDay, catalogMeals]);

    const dayCaloriesTotal = useMemo(() => {
        const sum = selectedMealCardsForDay.reduce((acc, m) => acc + (m?.caloriesNumber ?? 0), 0);
        return sum;
    }, [selectedMealCardsForDay]);

    const progressPercent = totalScreens <= 1 ? 0 : ((screen - 1) / (totalScreens - 1)) * 100;

    const canGoNextFromCraftDuration =
        craftKey !== null && (isLgViewport !== true ? true : weekDuration !== null);
    const canGoNextFromManualDays = weekDuration !== null && sortedSelectedDays.length === weekDuration;

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
        return craft.slots.map((s) => ({
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
                            ? s.optional
                              ? 'Soup of the Day (optional)'
                              : 'Soup'
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
        return requiredSlotsByCraft
            .filter((slot) => !slot.optional)
            .every((slot) => (s[slot.selectionKey]?.length ?? 0) === slot.count);
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
            .filter((slot) => !slot.optional && (s[slot.selectionKey]?.length ?? 0) < slot.count)
            .map((slot) => slot.label.toLowerCase());

        if (missing.length === 0) {
            return 'Select all required meals before continuing.';
        }
        if (missing.length === 1) {
            return `Please select a ${missing[0]} before continuing.`;
        }

        return `Please select: ${missing.join(', ')}.`;
    }, [craft, curationDay, requiredSlotsByCraft, selectedByDay, businessSideChoiceByDay]);

    return (
        <div className={`min-h-screen ${PAGE_BG} px-4 py-4 font-sans md:px-4 pb-24 sm:pb-4`}>
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
            <div className="mx-auto max-w-[1100px] space-y-4">
                <div className="sticky top-0 z-50 -mx-4 border-b border-gray-200/70 bg-[#F8F9F6]/95 px-4 pb-3 pt-2 backdrop-blur md:-mx-8 md:px-8 sm:pb-4">
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
                                onClick={() => {
                                    if (closeHref) {
                                        window.location.assign(closeHref);

                                        return;
                                    }
                                    window.history.back();
                                }}
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

                {!disableAdaptedMenuFetch && menuLoading ? (
                    <p className="mb-4 rounded-[12px] border border-gray-200 bg-white px-4 py-3 font-body text-sm text-[#555555]">
                        Loading your meal library with adapted portions…
                    </p>
                ) : null}
                {!disableAdaptedMenuFetch && menuError ? (
                    <p className="mb-4 rounded-[12px] border border-amber-200 bg-amber-50 px-4 py-3 font-body text-sm text-amber-900">
                        {menuError} Showing sample meals until your plan is available.
                    </p>
                ) : null}

                {/* Screen 1 — Craft & Duration */}
                {screen === 1 ? (
                    <section className="rounded-[12px] border border-gray-200 bg-white p-6 shadow-sm">
                        <h2 className="font-montserrat text-[16px] font-bold tracking-tight text-[#262A22]">
                            The Craft &amp; Duration
                        </h2>
                        <p className="mt-1 font-body text-sm text-[#555555]">
                            Choose your craft and how many days you’re crafting for.
                        </p>

                        <div className="mt-6 grid gap-6 lg:grid-cols-2">
                            <div>
                                <h3 className="font-montserrat text-sm font-bold text-[#262A22]">What’s Your Craft</h3>
                                <div className="mt-4 grid gap-3">
                                    {CRAFTS.map((c) => {
                                        const active = c.key === craftKey;
                                        return (
                                            <button
                                                key={c.key}
                                                type="button"
                                                onClick={() => setCraftKey(c.key)}
                                                aria-pressed={active}
                                                className={[
                                                    'w-full rounded-[12px] border p-4 text-left transition-colors',
                                                    active ? 'border-[#5A6B44] bg-[#F8F9F6]' : 'border-gray-200 bg-white hover:bg-[#F8F9F6]',
                                                    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-offset-2',
                                                ].join(' ')}
                                            >
                                                <p className="font-montserrat text-sm font-bold text-[#262A22]">{c.title}</p>
                                                <p className="mt-1 font-body text-sm text-[#555555]">{c.description}</p>
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>

                            {/* Large screens: week duration on plan setup. Small screens: week duration is on manual day selection instead. */}
                            <div className="hidden rounded-[12px] border border-gray-200 bg-[#F8F9F6] p-5 lg:block">
                                <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#555555]">
                                    Week duration
                                </p>
                                <div className="mt-3 flex flex-wrap gap-2">
                                    {[5, 6, 7].map((n) => (
                                        <PillButton
                                            key={n}
                                            label={`${n} days`}
                                            variant={weekDuration === n ? 'primary' : 'outline'}
                                            size="sm"
                                            onClick={() => setWeekDuration(n)}
                                            className={weekDuration === n ? '' : 'ring-1 ring-[#E5E7EB]'}
                                        />
                                    ))}
                                </div>
                                <p className="mt-3 font-body text-xs text-[#555555]">
                                    You’ll pick exactly {weekDuration ?? '…'} active day(s) next.
                                </p>
                            </div>
                        </div>

                        <div className="mt-6 flex justify-end">
                            <Button
                                label="NEXT"
                                variant="primary"
                                disabled={!canGoNextFromCraftDuration}
                                onClick={() => {
                                    if (weekDuration === 7) {
                                        setSelectedDays([1, 2, 3, 4, 5, 6, 7]);
                                        setActiveDay(1);
                                        setScreen(2);
                                        return;
                                    }

                                    setScreen(2);
                                }}
                                className="px-10"
                            />
                        </div>
                    </section>
                ) : null}

                {/* Screen 2 — Manual Day Selection */}
                {screen === 2 && usesManualDaySelection ? (
                    <section className="rounded-[12px] border border-gray-200 bg-white p-6 shadow-sm">
                        <h2 className="font-montserrat text-[16px] font-bold tracking-tight text-[#262A22]">
                            Manual Day Selection
                        </h2>
                        <p className="mt-1 font-body text-sm text-[#555555]">
                            Choose which days of the week are active.
                        </p>

                        <div className="mt-5 rounded-[12px] border border-gray-200 bg-[#F8F9F6] p-4 lg:hidden">
                            <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#555555]">
                                Week duration
                            </p>
                            <div className="mt-3 flex flex-wrap gap-2">
                                {[5, 6, 7].map((n) => (
                                    <PillButton
                                        key={n}
                                        label={`${n} days`}
                                        variant={weekDuration === n ? 'primary' : 'outline'}
                                        size="sm"
                                        onClick={() => setWeekDuration(n)}
                                        className={weekDuration === n ? '' : 'ring-1 ring-[#E5E7EB]'}
                                    />
                                ))}
                            </div>
                            <p className="mt-2 font-body text-xs text-[#555555]">
                                Then choose exactly {weekDuration ?? '…'} active day(s) below.
                            </p>
                        </div>

                        <div className="mt-5 rounded-[12px] border border-gray-200 bg-[#F8F9F6] p-5">
                            <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#555555]">
                                CHOOSE YOUR DAYS ({sortedSelectedDays.length}/{weekDuration ?? '…'} SELECTED)
                            </p>
                            <div className={['mt-3 flex flex-wrap gap-2', shakeOn ? 'mc-shake' : ''].join(' ')}>
                                {WEEKDAY_LABELS.map((label, i) => {
                                    const dayIdx = i + 1;
                                    const isOn = sortedSelectedDays.includes(dayIdx);
                                    return (
                                        <PillButton
                                            key={label}
                                            label={label}
                                            variant={isOn ? 'primary' : 'outline'}
                                            size="sm"
                                            onClick={() => toggleDay(dayIdx)}
                                            className={isOn ? '' : 'ring-1 ring-[#E5E7EB]'}
                                        />
                                    );
                                })}
                            </div>
                            <p className="mt-3 font-body text-xs text-[#555555]">
                                Selection must not exceed your Week Duration.
                            </p>
                        </div>

                        <div className="mt-8 flex items-center justify-between gap-3 fixed bottom-0 left-0 right-0 z-40 border-t border-gray-200/70 bg-[#F8F9F6]/95 p-3 backdrop-blur sm:static sm:border-t-0 sm:bg-transparent sm:p-0">
                            <Button label="BACK" variant="outline" onClick={() => setScreen(1)} className="px-10" />
                            <Button
                                label="NEXT"
                                variant="primary"
                                disabled={!canGoNextFromManualDays}
                                onClick={() => {
                                    const first = sortedSelectedDays[0] ?? null;
                                    setActiveDay(first);
                                    setScreen(curationStartScreen);
                                }}
                                className="px-10"
                            />
                        </div>
                    </section>
                ) : null}

                {/* Screens 3..X — Daily Curation (one day per screen) */}
                {isCurationScreen ? (
                    <>
                        {curationDay ? (() => {
                            const day = curationDay;
                            const daySelections = selectedByDay[day] ?? {
                                breakfasts: [],
                                meals: [],
                                sideSalads: [],
                                desserts: [],
                                soup: [],
                            };

                            const toggle = (key, max) => (meal) => {
                                setSelectedByDay((prev) => {
                                    const current = prev[day] ?? {
                                        breakfasts: [],
                                        meals: [],
                                        sideSalads: [],
                                        desserts: [],
                                        soup: [],
                                    };
                                    const existing = current[key] ?? [];
                                    const isOn = existing.includes(meal.id);
                                    let next = existing;
                                    if (isOn) {
                                        next = existing.filter((id) => id !== meal.id);
                                    } else if (existing.length < max) {
                                        next = [...existing, meal.id];
                                    } else {
                                        next = existing;
                                    }
                                    return { ...prev, [day]: { ...current, [key]: next } };
                                });
                            };

                            return (
                            <div className="w-full max-md:-mx-4 h-[calc(100dvh-8.5rem)] max-md:h-[calc(100dvh-7rem)] overflow-hidden">
                            <ChooseYourMeals
                                panelClassName="h-full min-h-0"
                                soupCatalogMeals={catalogMeals}
                                dayName={WEEKDAY_LONG[curationDay - 1] ?? ''}
                                totalKcal={dayCaloriesTotal}
                                summaryLabel={`${WEEKDAY_LABELS[curationDay - 1]} selections`}
                                craftTitle={craft ? craft.title : ''}
                                targetCalories={planTierCalories}
                                layout={craft?.key === 'full' ? 'categories' : 'custom'}
                                meals={craft?.key === 'full' ? consultationDeckMeals : []}
                                categorySelections={craft?.key === 'full' ? daySelections : undefined}
                                onToggleCategory={
                                    craft?.key === 'full'
                                        ? (categoryKey, meal) =>
                                              toggle(
                                                  categoryKey,
                                                  DEFAULT_FULL_CRAFT_MAX_SELECTIONS[categoryKey],
                                              )(meal)
                                        : undefined
                                }
                                deckScopePrefix={craft?.key === 'full' ? String(curationDay) : ''}
                                onSoupOptInChange={(enabled) => {
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
                                }}
                                onFooterBack={() => {
                                    if (screen === curationStartScreen) {
                                        setScreen(usesManualDaySelection ? 2 : 1);
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
                            >
                                {craft?.key === 'full' ? null : (
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

                                            const pickCards = (slotKey) => {
                                                if (slotKey === 'soup') {
                                                    return scheduledSoupForDay(day).map((meal) => meal);
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
                                                                    { key: 'soup', label: 'Soup' },
                                                                    { key: 'sidesalad', label: 'Side Salad' },
                                                                    { key: 'dessert', label: 'Dessert' },
                                                                ].map((opt) => {
                                                                    const on = side === opt.key;
                                                                    return (
                                                                        <PillButton
                                                                            key={opt.key}
                                                                            label={opt.label}
                                                                            variant={on ? 'primary' : 'outline'}
                                                                            size="sm"
                                                                            onClick={() => setBusinessSideChoice(day, /** @type {any} */ (opt.key))}
                                                                            className={on ? '' : 'ring-1 ring-[#E5E7EB]'}
                                                                        />
                                                                    );
                                                                })}
                                                            </div>
                                                        </div>

                                                        {side === 'soup' ? (
                                                            <MealSlotCarousel
                                                                title="Soup"
                                                                deckScopeKey={`${day}-soup`}
                                                                cards={pickCards('soup')}
                                                                selectedIds={selections.soup}
                                                                maxSelected={1}
                                                                onSelect={toggle('soup', 1)}
                                                            />
                                                        ) : side === 'dessert' ? (
                                                            <MealSlotCarousel
                                                                title="Dessert"
                                                                deckScopeKey={`${day}-dessert`}
                                                                cards={pickCards('dessert')}
                                                                selectedIds={selections.desserts}
                                                                maxSelected={1}
                                                                onSelect={toggle('desserts', 1)}
                                                            />
                                                        ) : (
                                                            <MealSlotCarousel
                                                                title="Side salad"
                                                                deckScopeKey={`${day}-sidesalad`}
                                                                cards={pickCards('sidesalad')}
                                                                selectedIds={selections.sideSalads}
                                                                maxSelected={1}
                                                                onSelect={toggle('sideSalads', 1)}
                                                            />
                                                        )}
                                                    </>
                                                );
                                            }

                                            return requiredSlotsByCraft.map((slot) => (
                                                <MealSlotCarousel
                                                    key={slotId(day, slot.key, 1)}
                                                    title={slot.label}
                                                    deckScopeKey={`${day}-${slot.key}`}
                                                    cards={pickCards(slot.key)}
                                                    selectedIds={selections[slot.selectionKey]}
                                                    maxSelected={slot.count}
                                                    onSelect={toggle(slot.selectionKey, slot.count)}
                                                />
                                            ));
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

                {/* Final Screen — Environmental Check & Submit */}
                {screen === finalScreen ? (
                    <section className="rounded-[12px] border border-gray-200 bg-white p-6 shadow-sm">
                        <h2 className="font-montserrat text-[16px] font-bold tracking-tight text-[#262A22]">
                            Environmental Check &amp; Submit
                        </h2>
                        <p className="mt-1 font-body text-sm text-[#555555]">
                            One last meaningful choice before the big finish.
                        </p>

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
                                    <span className="font-montserrat text-sm font-bold text-[#C44F5D]">NO CUTLERY FOR ME</span>
                                ) : null}
                            </div>
                        </div>

                        <div className="mt-8 flex items-center justify-between gap-3 fixed bottom-0 left-0 right-0 z-40 border-t border-gray-200/70 bg-[#F8F9F6]/95 p-3 backdrop-blur sm:static sm:border-t-0 sm:bg-transparent sm:p-0">
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
                                className="px-12 py-3 text-base"
                            />
                        </div>
                    </section>
                ) : null}
            </div>

            {submitting ? (
                <div className="fixed inset-0 z-[100] flex items-center justify-center p-6">
                    <div className="absolute inset-0 bg-black/40" />
                    <div className="relative w-full max-w-[640px] rounded-[12px] bg-white p-8 shadow-2xl">
                        <div className="flex flex-col items-center text-center">
                            <div className="h-[140px] w-[140px] animate-pulse">
                                <MealCraftLogoAnimatedIdentity variant="minimal-animated" />
                            </div>
                            <p className="mt-4 font-montserrat text-sm font-bold uppercase tracking-[0.18em] text-[#5A6B44]">
                                Your meal plan is being crafted…
                            </p>
                            <p className="mt-2 font-body text-sm text-[#555555]">Please wait a moment.</p>
                        </div>
                    </div>
                </div>
            ) : null}

            {submitSuccess ? (
                <div className="fixed bottom-6 left-1/2 z-[110] w-full max-w-[640px] -translate-x-1/2 px-4">
                    <div className="rounded-[12px] border border-[#5A6B44]/30 bg-white px-4 py-3 shadow-lg">
                        <p className="font-body text-sm font-semibold text-[#262A22]">
                            Your craft plan was saved. The kitchen can now produce your selections.
                        </p>
                        {homeHref ? (
                            <button
                                type="button"
                                className="mt-3 font-montserrat text-sm font-bold text-[#5A6B44] underline-offset-2 hover:underline"
                                onClick={() => window.location.assign(homeHref)}
                            >
                                Back to your plan
                            </button>
                        ) : null}
                    </div>
                </div>
            ) : null}

            {submitError ? (
                <div className="fixed bottom-6 left-1/2 z-[110] w-full max-w-[640px] -translate-x-1/2 px-4">
                    <div className="rounded-[12px] border border-red-200 bg-red-50 px-4 py-3 shadow-lg">
                        <p className="font-body text-sm text-red-800">{submitError}</p>
                    </div>
                </div>
            ) : null}

            {toast.visible ? (
                <div className="fixed bottom-6 left-1/2 z-[110] w-full max-w-[640px] -translate-x-1/2 px-4">
                    <div className="rounded-[12px] border border-gray-200 bg-white px-4 py-3 shadow-lg">
                        <p className="font-body text-sm text-[#555555]">{toast.message}</p>
                    </div>
                </div>
            ) : null}
        </div>
    );
}

