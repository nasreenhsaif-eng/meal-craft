/** Mirrors {@link CraftCaloriePlanner} / config/customer_nutrition.php planning midpoints. */
const FIXED_SIDE_SALAD = 175;
const FIXED_DESSERT = 170;
const BREAKFAST_WEIGHT = 0.2;
const MAIN_EACH_WEIGHT = 0.4;
const BUSINESS_CALORIES = 500;

/**
 * @param {number} planTier
 * @returns {{ breakfast: number; mainEach: number }}
 */
export function scalableSlotTargetsForTier(planTier) {
    const fixedTotal = FIXED_SIDE_SALAD + FIXED_DESSERT;
    const scalableBudget = Math.max(0, planTier - fixedTotal);

    return {
        breakfast: Math.round(scalableBudget * BREAKFAST_WEIGHT),
        mainEach: Math.round(scalableBudget * MAIN_EACH_WEIGHT),
    };
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
