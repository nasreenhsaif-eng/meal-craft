import { craftLibrarySlotRow } from './craftCalorieTargets.js';

/**
 * @param {Array<{ categories?: Record<string, Array<{ calorieTiers?: unknown[] }>> }>} [days]
 */
export function daysUseLibraryPortions(days) {
    for (const day of days ?? []) {
        for (const categoryKey of ['breakfasts', 'meals']) {
            for (const meal of day?.categories?.[categoryKey] ?? []) {
                if (Array.isArray(meal?.calorieTiers) && meal.calorieTiers.length > 0) {
                    return true;
                }
            }
        }
    }

    return false;
}

/**
 * Swap breakfast/main cards onto the authored library tab for a daily total.
 * Sides/desserts/soup stay one-size; omitted slots are hidden.
 *
 * @param {Array<{ dayNumber?: number; label?: string; categories?: Record<string, object[]> }>} [days]
 * @param {number} planTier
 * @param {string} [craftKey]
 */
export function applyLibraryTierPortions(days, planTier, craftKey = 'full') {
    const row = craftLibrarySlotRow(planTier, craftKey);

    return (days ?? []).map((day) => {
        const categories = { ...(day.categories ?? {}) };

        categories.breakfasts = overlayCategoryMeals(categories.breakfasts, row.breakfast);
        categories.meals = overlayCategoryMeals(categories.meals, row.mainEach);

        if (!row.includeSideSalad) {
            categories.sideSalads = [];
        }

        if (!row.includeDessert) {
            categories.desserts = [];
        }

        if (!row.includeSoup) {
            categories.soup = [];
        }

        if (row.breakfast <= 0) {
            categories.breakfasts = [];
        }

        return {
            ...day,
            categories,
            reconciliationWarnings: [],
        };
    });
}

/**
 * @param {object[] | undefined} meals
 * @param {number} tab
 */
function overlayCategoryMeals(meals, tab) {
    if (!Array.isArray(meals)) {
        return [];
    }

    if (tab <= 0) {
        return meals;
    }

    return meals.map((meal) => overlayMealFromTabs(meal, tab));
}

/**
 * @param {object} meal
 * @param {number} tab
 */
function overlayMealFromTabs(meal, tab) {
    const tier = (meal?.calorieTiers ?? []).find(
        (row) => Number(row?.calorie_tier) === Number(tab),
    );

    if (!tier) {
        return { ...meal, isScaled: false };
    }

    const macros = tier.macros ?? meal.macros;
    const detailView =
        meal.detailView && typeof meal.detailView === 'object'
            ? {
                  ...meal.detailView,
                  macros,
                  nutrition: tier.nutrition ?? meal.detailView.nutrition,
                  nutritionalData: tier.nutritionalData ?? meal.detailView.nutritionalData,
              }
            : meal.detailView;

    return {
        ...meal,
        macros,
        caloriesNumber: macros?.calories,
        isScaled: false,
        libraryCalorieTier: tab,
        kitchenIngredientRows: tier.kitchenIngredientRows ?? meal.kitchenIngredientRows,
        detailView,
    };
}
