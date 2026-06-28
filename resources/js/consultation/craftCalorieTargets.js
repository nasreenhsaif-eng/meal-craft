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
    protein: 40,
    carbs: 30,
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
    const selected = options.selectedFixedSlots ?? ['side_salad', 'dessert', 'soup'];
    const sideMeal = grouped?.sideSalads?.[0];
    const dessertMeal = grouped?.desserts?.[0];
    const soupMeal = grouped?.soup?.[0];

    const valueFor = (slot, meal) => {
        if (!selected.includes(slot)) {
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
 */
export function mainSlotTargetCaloriesFromPlan(nutritionPlan) {
    const fromPlan = /** @type {{ calories?: number } | undefined} */ (
        /** @type {Record<string, unknown> | undefined} */ (nutritionPlan?.scalable_slot_targets)?.main_each
    )?.calories;

    if (typeof fromPlan === 'number' && fromPlan > 0) {
        return Math.round(fromPlan);
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

    if (typeof fromPlan === 'number' && fromPlan > 0) {
        return fromPlan;
    }

    const { mainEach } = tierSlotTargetsForPlanTier(Math.round(planTier));

    if (craftKey === 'business') {
        return macroGramsFromCalories(BUSINESS_MAIN_TARGET).protein;
    }

    if (craftKey === 'intermittent') {
        const intermittentMainEach = Math.max(
            0,
            craftDayCaloriesForKey('intermittent', planTier) - FIXED_CHOICE_COUNT * FIXED_CHOICE_CALORIES,
        );

        return macroGramsFromCalories(intermittentMainEach).protein;
    }

    return macroGramsFromCalories(mainEach).protein;
}

/**
 * @param {{
 *   sideSalads?: string[];
 *   desserts?: string[];
 *   soup?: string[];
 * } | null | undefined} selections
 * @returns {string[]}
 */
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
