/** Mirrors {@link CraftCaloriePlanner} / config/customer_nutrition.php planning midpoints. */
const FIXED_SIDE_SALAD = 175;
const FIXED_DESSERT = 170;
const BREAKFAST_WEIGHT = 0.2;
const MAIN_EACH_WEIGHT = 0.4;
const BUSINESS_CALORIES = 500;
const BALANCED_MACRO_SPLIT = Object.freeze({
    protein: 40,
    carbs: 30,
    fat: 30,
});

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
 * @param {number} [soupCalories] Fixed soup portion counted within tier when opted in.
 * @returns {{ breakfast: number; mainEach: number }}
 */
export function scalableSlotTargetsForTier(planTier, soupCalories = 0, sideSaladCalories = FIXED_SIDE_SALAD, dessertCalories = FIXED_DESSERT) {
    const fixedTotal = sideSaladCalories + dessertCalories + Math.max(0, soupCalories);
    const scalableBudget = Math.max(0, planTier - fixedTotal);

    return {
        breakfast: Math.round(scalableBudget * BREAKFAST_WEIGHT),
        mainEach: Math.round(scalableBudget * MAIN_EACH_WEIGHT),
    };
}

/**
 * @param {{
 *   sideSalads?: Array<{ caloriesNumber?: number; baselineCalories?: number }>;
 *   desserts?: Array<{ caloriesNumber?: number; baselineCalories?: number }>;
 *   soup?: Array<{ caloriesNumber?: number; baselineCalories?: number }>;
 * } | null | undefined} grouped
 * @param {{ includeSoup?: boolean }} [options]
 */
export function fixedPortionCaloriesForAdapt(grouped, options = {}) {
    const sideMeal = grouped?.sideSalads?.[0];
    const dessertMeal = grouped?.desserts?.[0];
    const soupMeal = grouped?.soup?.[0];

    const sideSalad =
        sideMeal?.baselineCalories ??
        sideMeal?.caloriesNumber ??
        FIXED_SIDE_SALAD;
    const dessert =
        dessertMeal?.baselineCalories ??
        dessertMeal?.caloriesNumber ??
        FIXED_DESSERT;
    const soup =
        options.includeSoup && soupMeal
            ? soupMeal.baselineCalories ?? soupMeal.caloriesNumber ?? 0
            : 0;

    return {
        sideSaladCalories: Math.max(0, Math.round(sideSalad)),
        dessertCalories: Math.max(0, Math.round(dessert)),
        soupCalories: Math.max(0, Math.round(soup)),
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
 * @param {string | null | undefined} craftKey
 * @param {number} planTier
 */
export function craftDayCaloriesForKey(craftKey, planTier) {
    const tier = Math.round(planTier);

    if (!craftKey) {
        return tier;
    }

    const { breakfast, mainEach } = scalableSlotTargetsForTier(tier);

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
            return BUSINESS_CALORIES;
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

    const tier = Math.round(planTier);
    const { mainEach } = scalableSlotTargetsForTier(tier);

    if (craftKey === 'business') {
        const businessMainEach = Math.round(BUSINESS_CALORIES * MAIN_EACH_WEIGHT);

        return macroGramsFromCalories(businessMainEach).protein;
    }

    if (craftKey === 'intermittent') {
        const intermittentMainEach = Math.round(
            scalableSlotTargetsForTier(tier).mainEach * (MAIN_EACH_WEIGHT / (BREAKFAST_WEIGHT + MAIN_EACH_WEIGHT)),
        );

        return macroGramsFromCalories(intermittentMainEach).protein;
    }

    return macroGramsFromCalories(mainEach).protein;
}
