/** Mirrors {@link CraftCaloriePlanner} / config/customer_nutrition.php tier targets. */
const TIER_SLOT_CALORIES = Object.freeze({
    1000: { breakfast: 200, mainEach: 250 },
    1200: { breakfast: 200, mainEach: 350 },
    1500: { breakfast: 300, mainEach: 450 },
    1800: { breakfast: 400, mainEach: 550 },
    2000: { breakfast: 450, mainEach: 625 },
});

const FIXED_CHOICE_CALORIES = 150;
const FIXED_CHOICE_COUNT = 2;
const BUSINESS_MAIN_TARGET = 375;
const BUSINESS_SIDE_CALORIES = 150;
const BALANCED_MACRO_SPLIT = Object.freeze({
    protein: 35,
    carbs: 35,
    fat: 30,
});

/** Mirrors config/customer_nutrition.php main_each_macro_split — protein-first main scaling. */
const MAIN_EACH_MACRO_SPLIT = Object.freeze({
    protein: 45,
    carbs: 25,
    fat: 30,
});

/**
 * @param {number} planTier
 */
export function tierSlotTargetsForPlanTier(planTier) {
    const tier = Math.round(planTier);
    const row = /** @type {{ breakfast: number; mainEach: number } | undefined} */ (
        TIER_SLOT_CALORIES[/** @type {keyof typeof TIER_SLOT_CALORIES} */ (tier)]
    );

    if (row) {
        return { breakfast: row.breakfast, mainEach: row.mainEach };
    }

    const fixedTotal = FIXED_CHOICE_COUNT * FIXED_CHOICE_CALORIES;
    const scalableBudget = Math.max(0, tier - fixedTotal);

    return {
        breakfast: Math.round(scalableBudget * 0.2),
        mainEach: Math.round(scalableBudget * 0.4),
    };
}

/**
 * @param {number} calories
 * @param {{ protein?: number; carbs?: number; fat?: number }} [split]
 */
export function macroGramsFromCalories(calories, split = BALANCED_MACRO_SPLIT) {
    const kcal = Math.max(0, calories);

    return {
        protein: Math.round(((kcal * (split.protein ?? BALANCED_MACRO_SPLIT.protein)) / 100 / 4) * 100) / 100,
        carbs: Math.round(((kcal * (split.carbs ?? BALANCED_MACRO_SPLIT.carbs)) / 100 / 4) * 100) / 100,
        fat: Math.round(((kcal * (split.fat ?? BALANCED_MACRO_SPLIT.fat)) / 100 / 9) * 100) / 100,
    };
}

/**
 * @param {number} planTier
 * @returns {{ breakfast: number; mainEach: number }}
 */
export function scalableSlotTargetsForTier(planTier) {
    return tierSlotTargetsForPlanTier(planTier);
}

/**
 * @param {{
 *   sideSalads?: Array<{ caloriesNumber?: number; baselineCalories?: number }>;
 *   desserts?: Array<{ caloriesNumber?: number; baselineCalories?: number }>;
 *   soup?: Array<{ caloriesNumber?: number; baselineCalories?: number }>;
 * } | null | undefined} grouped
 * @param {{ selectedFixedSlots?: string[] }} [options]
 */
export function fixedPortionCaloriesForAdapt(grouped, options = {}) {
    const selected = options.selectedFixedSlots;
    const activeSlots =
        selected === undefined || selected.length === 0
            ? ['side_salad', 'dessert']
            : selected;
    const sideMeal = grouped?.sideSalads?.[0];
    const dessertMeal = grouped?.desserts?.[0];
    const soupMeal = grouped?.soup?.[0];

    const valueFor = (slot, meal) => {
        if (!activeSlots.includes(slot)) {
            return 0;
        }

        if (!meal) {
            return FIXED_CHOICE_CALORIES;
        }

        return Math.max(
            0,
            Math.round(meal.baselineCalories ?? meal.caloriesNumber ?? FIXED_CHOICE_CALORIES),
        );
    };

    return {
        sideSaladCalories: valueFor('side_salad', sideMeal),
        dessertCalories: valueFor('dessert', dessertMeal),
        soupCalories: valueFor('soup', soupMeal),
    };
}

/**
 * @param {Record<string, unknown> | null | undefined} nutritionPlan
 * @param {number} planTier
 */
export function nutritionPlanMatchesTier(nutritionPlan, planTier) {
    if (!nutritionPlan || !Number.isFinite(planTier) || planTier <= 0) {
        return false;
    }

    const fromPlan = nutritionPlan.plan_tier ?? nutritionPlan.core_day_calories;

    return typeof fromPlan === 'number' && Math.round(fromPlan) === Math.round(planTier);
}

/**
 * @param {Record<string, unknown> | null | undefined} nutritionPlan
 * @param {number} [planTier]
 */
export function mainSlotTargetCaloriesFromPlan(nutritionPlan, planTier = 0) {
    const fromPlan = /** @type {{ calories?: number } | undefined} */ (
        /** @type {Record<string, unknown> | undefined} */ (nutritionPlan?.scalable_slot_targets)?.main_each
    )?.calories;

    if (
        typeof fromPlan === 'number'
        && fromPlan > 0
        && (planTier <= 0 || nutritionPlanMatchesTier(nutritionPlan, planTier))
    ) {
        return Math.round(fromPlan);
    }

    if (planTier > 0) {
        return tierSlotTargetsForPlanTier(planTier).mainEach;
    }

    return 0;
}

/**
 * @param {Record<string, unknown> | null | undefined} nutritionPlan
 * @param {number} [planTier]
 */
export function breakfastSlotTargetCaloriesFromPlan(nutritionPlan, planTier = 0) {
    const fromPlan = /** @type {{ calories?: number } | undefined} */ (
        /** @type {Record<string, unknown> | undefined} */ (nutritionPlan?.scalable_slot_targets)?.breakfast
    )?.calories;

    if (typeof fromPlan === 'number' && fromPlan > 0) {
        return Math.round(fromPlan);
    }

    if (planTier > 0) {
        return tierSlotTargetsForPlanTier(planTier).breakfast;
    }

    return 0;
}

/**
 * @param {string | null | undefined} craftKey
 * @param {number} planTier
 */
export function craftDayCaloriesForKey(craftKey, planTier) {
    const tier = Math.round(planTier);
    const { breakfast, mainEach } = tierSlotTargetsForPlanTier(tier);
    const fixedTotal = FIXED_CHOICE_COUNT * FIXED_CHOICE_CALORIES;

    if (!craftKey) {
        return tier;
    }

    switch (craftKey) {
        case 'full':
            return tier;
        case 'afternoon':
            return tier - breakfast;
        case 'day':
            return tier - mainEach;
        case 'intermittent':
            return tier - breakfast - mainEach;
        case 'business':
            return BUSINESS_MAIN_TARGET + BUSINESS_SIDE_CALORIES;
        default:
            return tier;
    }
}

/**
 * Protein gram target per main meal slot after craft-specific calorie budgeting.
 *
 * @param {string | null | undefined} craftKey
 * @param {number} planTier
 * @param {Record<string, unknown> | null | undefined} [nutritionPlan]
 */
export function mainProteinTargetPerMeal(craftKey, planTier, nutritionPlan = null) {
    const fromPlan = /** @type {{ protein_g?: number } | undefined} */ (
        /** @type {Record<string, unknown> | undefined} */ (nutritionPlan?.scalable_slot_targets)?.main_each
    )?.macros?.protein_g;

    if (typeof fromPlan === 'number' && fromPlan > 0 && nutritionPlanMatchesTier(nutritionPlan, planTier)) {
        return fromPlan;
    }

    const { mainEach } = tierSlotTargetsForPlanTier(Math.round(planTier));

    if (craftKey === 'business') {
        return macroGramsFromCalories(BUSINESS_MAIN_TARGET, MAIN_EACH_MACRO_SPLIT).protein;
    }

    if (craftKey === 'intermittent') {
        const intermittentMainEach = Math.max(
            0,
            craftDayCaloriesForKey('intermittent', planTier) - FIXED_CHOICE_COUNT * FIXED_CHOICE_CALORIES,
        );

        return macroGramsFromCalories(intermittentMainEach, MAIN_EACH_MACRO_SPLIT).protein;
    }

    return macroGramsFromCalories(mainEach, MAIN_EACH_MACRO_SPLIT).protein;
}

/**
 * @param {{
 *   sideSalads?: string[];
 *   desserts?: string[];
 *   soup?: string[];
 * } | null | undefined} selections
 * @returns {string[]}
 */
/** Default gram tolerances — mirrors config/customer_nutrition.php day_macro_tolerance. */
export const DEFAULT_DAY_MACRO_TOLERANCE = Object.freeze({
    protein: 15,
    carbs: 20,
    fat: 15,
});

/**
 * @param {Record<string, unknown> | null | undefined} nutritionPlan
 */
export function dayMacroToleranceFromPlan(nutritionPlan) {
    const tol = /** @type {{ protein_g?: number; carbs_g?: number; fat_g?: number } | undefined} */ (
        nutritionPlan?.day_macro_tolerance
    );

    return {
        protein: typeof tol?.protein_g === 'number' ? tol.protein_g : DEFAULT_DAY_MACRO_TOLERANCE.protein,
        carbs: typeof tol?.carbs_g === 'number' ? tol.carbs_g : DEFAULT_DAY_MACRO_TOLERANCE.carbs,
        fat: typeof tol?.fat_g === 'number' ? tol.fat_g : DEFAULT_DAY_MACRO_TOLERANCE.fat,
    };
}

/**
 * Daily macro gram targets for the active craft day (matches onboarding daily_macros at full craft).
 *
 * @param {Record<string, unknown> | null | undefined} nutritionPlan
 * @param {number} planTier
 * @param {string | null | undefined} [craftKey]
 * @returns {{ calories: number; protein: number; carbs: number; fat: number }}
 */
export function dailyMacroTargetsFromPlan(nutritionPlan, planTier, craftKey = 'full') {
    const tier = Math.round(planTier);
    const dayCalories = craftDayCaloriesForKey(craftKey ?? 'full', tier);

    if (nutritionPlan && nutritionPlanMatchesTier(nutritionPlan, tier)) {
        const daily = /** @type {{ protein_g?: number; carbs_g?: number; fat_g?: number } | undefined} */ (
            nutritionPlan.daily_macros
        );

        if (daily && craftKey === 'full') {
            return {
                calories: Math.round(dayCalories),
                protein: Math.round(daily.protein_g ?? 0),
                carbs: Math.round(daily.carbs_g ?? 0),
                fat: Math.round(daily.fat_g ?? 0),
            };
        }

        if (daily && tier > 0) {
            const ratio = dayCalories / tier;

            return {
                calories: Math.round(dayCalories),
                protein: Math.round((daily.protein_g ?? 0) * ratio),
                carbs: Math.round((daily.carbs_g ?? 0) * ratio),
                fat: Math.round((daily.fat_g ?? 0) * ratio),
            };
        }
    }

    const proteinPct = Number(nutritionPlan?.protein_percentage ?? BALANCED_MACRO_SPLIT.protein);
    const carbPct = Number(nutritionPlan?.carb_percentage ?? BALANCED_MACRO_SPLIT.carbs);
    const fatPct = Number(nutritionPlan?.fat_percentage ?? BALANCED_MACRO_SPLIT.fat);
    const grams = macroGramsFromCalories(dayCalories, {
        protein: proteinPct,
        carbs: carbPct,
        fat: fatPct,
    });

    return {
        calories: Math.round(dayCalories),
        protein: Math.round(grams.protein),
        carbs: Math.round(grams.carbs),
        fat: Math.round(grams.fat),
    };
}

/**
 * @param {number} selected
 * @param {number} target
 * @param {number} tolerance
 */
export function isMacroOutsideTolerance(selected, target, tolerance) {
    return Math.abs(Math.round(selected) - Math.round(target)) > tolerance;
}

export function selectedFixedSlotsFromSelections(selections) {
    if (!selections) {
        return [];
    }

    /** @type {string[]} */
    const slots = [];

    if ((selections.sideSalads?.length ?? 0) > 0) {
        slots.push('side_salad');
    }

    if ((selections.desserts?.length ?? 0) > 0) {
        slots.push('dessert');
    }

    if ((selections.soup?.length ?? 0) > 0) {
        slots.push('soup');
    }

    return slots;
}
