/** @typedef {{ id: string; isVegan?: boolean; macros?: { calories?: number | string; protein?: number | string; carbs?: number | string; fat?: number | string }; caloriesNumber?: number; proteinBalanced?: boolean }} ConsultationMealCard */

function parseMacro(value: number | string | null | undefined): number {
    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : 0;
    }

    if (typeof value === 'string') {
        const parsed = Number.parseFloat(value.replace(/[^\d.-]/g, ''));

        return Number.isFinite(parsed) ? parsed : 0;
    }

    return 0;
}

/**
 * @param {ConsultationMealCard} meal
 * @param {number} multiplier
 */
function scaleConsultationMeal(meal, multiplier) {
    const protein = parseMacro(meal.macros?.protein) * multiplier;
    const calories = parseMacro(meal.macros?.calories) * multiplier;
    const carbs = parseMacro(meal.macros?.carbs) * multiplier;
    const fat = parseMacro(meal.macros?.fat) * multiplier;

    return {
        ...meal,
        proteinBalanced: multiplier !== 1,
        caloriesNumber: Math.round(calories),
        macros: {
            calories: Math.round(calories),
            protein: `${Math.round(protein * 10) / 10}g`,
            carbs: `${Math.round(carbs * 10) / 10}g`,
            fat: `${Math.round(fat * 10) / 10}g`,
        },
        scalingMultiplier:
            typeof meal.scalingMultiplier === 'number'
                ? Math.round(meal.scalingMultiplier * multiplier * 10000) / 10000
                : multiplier,
    };
}

/**
 * Boost non-vegan mains when a vegan choice leaves the combined main protein below target.
 *
 * @param {ConsultationMealCard[]} meals
 * @param {number} proteinTargetPerMain
 * @param {number} [slotTargetCaloriesPerMain]
 */
export function balanceSelectedMainMealProtein(meals, proteinTargetPerMain, slotTargetCaloriesPerMain = 0) {
    if (!Array.isArray(meals) || meals.length === 0 || proteinTargetPerMain <= 0) {
        return meals ?? [];
    }

    const proteinTargetTotal = proteinTargetPerMain * meals.length;
    const currentProteinTotal = meals.reduce((sum, meal) => sum + parseMacro(meal.macros?.protein), 0);
    const shortfall = Math.round((proteinTargetTotal - currentProteinTotal) * 10) / 10;

    if (shortfall <= 0.25) {
        return meals;
    }

    const compensatorIndexes = meals
        .map((meal, index) => (!meal.isVegan ? index : -1))
        .filter((index) => index >= 0);

    if (compensatorIndexes.length === 0) {
        return meals;
    }

    const compensatingProtein = compensatorIndexes.reduce(
        (sum, index) => sum + parseMacro(meals[index]?.macros?.protein),
        0,
    );

    if (compensatingProtein <= 0) {
        return meals;
    }

    return meals.map((meal, index) => {
        if (!compensatorIndexes.includes(index)) {
            return meal;
        }

        const currentProtein = parseMacro(meal.macros?.protein);

        if (currentProtein <= 0) {
            return meal;
        }

        const proteinShare = currentProtein / compensatingProtein;
        const addedProtein = shortfall * proteinShare;
        let boostMultiplier = (currentProtein + addedProtein) / currentProtein;
        const currentCalories = meal.caloriesNumber ?? parseMacro(meal.macros?.calories);

        if (currentCalories <= 0) {
            return meal;
        }

        if (slotTargetCaloriesPerMain > 0) {
            const maxBoostFromCalories = slotTargetCaloriesPerMain / currentCalories;
            boostMultiplier = Math.min(boostMultiplier, maxBoostFromCalories);
        }

        if (boostMultiplier <= 1.0001) {
            return meal;
        }

        return scaleConsultationMeal(meal, boostMultiplier);
    });
}

/**
 * @param {ConsultationMealCard[]} cards
 * @param {string[]} selectedIds
 * @param {number} proteinTargetPerMain
 * @param {number} [slotTargetCaloriesPerMain]
 */
export function balanceSelectedMainMealCards(cards, selectedIds, proteinTargetPerMain, slotTargetCaloriesPerMain = 0) {
    const selected = selectedIds
        .map((id) => cards.find((meal) => meal.id === id))
        .filter(Boolean);

    const balanced = balanceSelectedMainMealProtein(
        selected,
        proteinTargetPerMain,
        slotTargetCaloriesPerMain,
    );
    const balancedById = new Map(balanced.map((meal) => [meal.id, meal]));

    return cards.map((meal) => balancedById.get(meal.id) ?? meal);
}
