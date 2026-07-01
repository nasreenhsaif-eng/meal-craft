/** @typedef {{ id: string; isVegan?: boolean; macros?: { calories?: number | string; protein?: number | string; carbs?: number | string; fat?: number | string }; caloriesNumber?: number; proteinBalanced?: boolean; scalingMultiplier?: number; slot?: string }} ConsultationMealCard */

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
 * @param {ConsultationMealCard} meal
 * @param {number} extraCarbs
 */
function addCarbsToConsultationMeal(meal, extraCarbs) {
    if (extraCarbs <= 0) {
        return meal;
    }

    const protein = parseMacro(meal.macros?.protein);
    const fat = parseMacro(meal.macros?.fat);
    const carbs = parseMacro(meal.macros?.carbs) + extraCarbs;
    const calories = (meal.caloriesNumber ?? parseMacro(meal.macros?.calories)) + extraCarbs * 4;

    return {
        ...meal,
        caloriesNumber: Math.round(calories),
        macros: {
            calories: Math.round(calories),
            protein: `${Math.round(protein * 10) / 10}g`,
            carbs: `${Math.round(carbs * 10) / 10}g`,
            fat: `${Math.round(fat * 10) / 10}g`,
        },
    };
}

/**
 * @param {ConsultationMealCard} meal
 * @param {number} carbReduction
 */
function removeCarbsFromConsultationMeal(meal, carbReduction) {
    if (carbReduction <= 0) {
        return meal;
    }

    const protein = parseMacro(meal.macros?.protein);
    const fat = parseMacro(meal.macros?.fat);
    const currentCarbs = parseMacro(meal.macros?.carbs);
    const removed = Math.min(carbReduction, Math.max(0, currentCarbs - 1));
    const carbs = currentCarbs - removed;
    const calories = (meal.caloriesNumber ?? parseMacro(meal.macros?.calories)) - removed * 4;

    return {
        ...meal,
        caloriesNumber: Math.max(0, Math.round(calories)),
        macros: {
            calories: Math.max(0, Math.round(calories)),
            protein: `${Math.round(protein * 10) / 10}g`,
            carbs: `${Math.round(carbs * 10) / 10}g`,
            fat: `${Math.round(fat * 10) / 10}g`,
        },
    };
}

/**
 * @param {ConsultationMealCard} meal
 * @param {number} fatReduction
 */
function removeFatFromConsultationMeal(meal, fatReduction) {
    if (fatReduction <= 0) {
        return meal;
    }

    const protein = parseMacro(meal.macros?.protein);
    const carbs = parseMacro(meal.macros?.carbs);
    const currentFat = parseMacro(meal.macros?.fat);
    const removed = Math.min(fatReduction, Math.max(0, currentFat - 1));
    const fat = currentFat - removed;
    const calories = (meal.caloriesNumber ?? parseMacro(meal.macros?.calories)) - removed * 9;

    return {
        ...meal,
        caloriesNumber: Math.max(0, Math.round(calories)),
        macros: {
            calories: Math.max(0, Math.round(calories)),
            protein: `${Math.round(protein * 10) / 10}g`,
            carbs: `${Math.round(carbs * 10) / 10}g`,
            fat: `${Math.round(fat * 10) / 10}g`,
        },
    };
}

/**
 * @param {Partial<Record<'breakfasts' | 'meals' | 'sideSalads' | 'desserts' | 'soup', ConsultationMealCard[]>>} categories
 */
function flattenConsultationCategories(categories) {
    return [
        ...(categories.breakfasts ?? []),
        ...(categories.meals ?? []),
        ...(categories.sideSalads ?? []),
        ...(categories.desserts ?? []),
        ...(categories.soup ?? []),
    ];
}

/**
 * @param {ConsultationMealCard[]} meals
 */
function indexOfHighestCarbMain(meals) {
    let bestIndex = -1;
    let bestCarbs = 0;

    meals.forEach((meal, index) => {
        const carbs = parseMacro(meal.macros?.carbs);

        if (carbs > bestCarbs) {
            bestCarbs = carbs;
            bestIndex = index;
        }
    });

    return bestIndex;
}

/**
 * @param {ConsultationMealCard[]} meals
 */
function indexOfHighestFatMain(meals) {
    let bestIndex = -1;
    let bestFat = 0;

    meals.forEach((meal, index) => {
        const fat = parseMacro(meal.macros?.fat);

        if (fat > bestFat) {
            bestFat = fat;
            bestIndex = index;
        }
    });

    return bestIndex;
}

/**
 * @param {ConsultationMealCard[]} meals
 * @param {number} carbSurplus
 * @param {number} fatSurplus
 * @param {number} calorieSurplus
 */
function trimMainMealsForDaySurplus(meals, carbSurplus, fatSurplus, calorieSurplus) {
    let balanced = [...meals];
    let remainingCalorieSurplus = calorieSurplus;

    if (carbSurplus > 0) {
        const carbMainIndex = indexOfHighestCarbMain(balanced);

        if (carbMainIndex >= 0) {
            const carbReduction = Math.min(carbSurplus, remainingCalorieSurplus / 4);
            balanced = balanced.map((meal, index) =>
                index === carbMainIndex ? removeCarbsFromConsultationMeal(meal, carbReduction) : meal,
            );
            remainingCalorieSurplus = Math.max(0, remainingCalorieSurplus - carbReduction * 4);
        }
    }

    if (fatSurplus > 0 && remainingCalorieSurplus > 0) {
        const fatMainIndex = indexOfHighestFatMain(balanced);

        if (fatMainIndex >= 0) {
            const fatReduction = Math.min(fatSurplus, remainingCalorieSurplus / 9);
            balanced = balanced.map((meal, index) =>
                index === fatMainIndex ? removeFatFromConsultationMeal(meal, fatReduction) : meal,
            );
        }
    }

    return balanced;
}

/**
 * @param {ConsultationMealCard[]} meals
 * @param {number} proteinTargetPerMain
 * @param {number} [slotTargetCaloriesPerMain]
 */
function boostCompensatorMainsTowardTarget(meals, proteinTargetPerMain, slotTargetCaloriesPerMain = 0) {
    const compensatorIndexes = meals
        .map((meal, index) => (!meal.isVegan ? index : -1))
        .filter((index) => index >= 0);

    return meals.map((meal, index) => {
        if (!compensatorIndexes.includes(index)) {
            return meal;
        }

        const currentProtein = parseMacro(meal.macros?.protein);
        const currentCalories = meal.caloriesNumber ?? parseMacro(meal.macros?.calories);

        if (currentProtein <= 0 || currentCalories <= 0 || currentProtein >= proteinTargetPerMain - 0.25) {
            return meal;
        }

        let boostMultiplier = proteinTargetPerMain / currentProtein;

        if (slotTargetCaloriesPerMain > 0) {
            boostMultiplier = Math.min(boostMultiplier, slotTargetCaloriesPerMain / currentCalories);
        }

        if (boostMultiplier <= 1.0001) {
            return meal;
        }

        return scaleConsultationMeal(meal, boostMultiplier);
    });
}

/**
 * Boost non-vegan mains when combined or per-meal protein is below target.
 *
 * @param {ConsultationMealCard[]} meals
 * @param {number} proteinTargetPerMain
 * @param {number} [slotTargetCaloriesPerMain]
 */
export function balanceSelectedMainMealProtein(meals, proteinTargetPerMain, slotTargetCaloriesPerMain = 0) {
    if (!Array.isArray(meals) || meals.length === 0 || proteinTargetPerMain <= 0) {
        return meals ?? [];
    }

    const hasVeganMain = meals.some((meal) => meal.isVegan);

    if (!hasVeganMain) {
        return meals;
    }

    const perMealBoosted = boostCompensatorMainsTowardTarget(
        meals,
        proteinTargetPerMain,
        slotTargetCaloriesPerMain,
    );

    const proteinTargetTotal = proteinTargetPerMain * perMealBoosted.length;
    const currentProteinTotal = perMealBoosted.reduce((sum, meal) => sum + parseMacro(meal.macros?.protein), 0);
    const shortfall = Math.round((proteinTargetTotal - currentProteinTotal) * 10) / 10;

    if (shortfall <= 0.25) {
        return perMealBoosted;
    }

    const compensatorIndexes = perMealBoosted
        .map((meal, index) => (!meal.isVegan ? index : -1))
        .filter((index) => index >= 0);

    if (compensatorIndexes.length === 0) {
        return perMealBoosted;
    }

    const compensatingProtein = compensatorIndexes.reduce(
        (sum, index) => sum + parseMacro(perMealBoosted[index]?.macros?.protein),
        0,
    );

    if (compensatingProtein <= 0) {
        return perMealBoosted;
    }

    return perMealBoosted.map((meal, index) => {
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

/**
 * @param {Partial<Record<'breakfasts' | 'meals' | 'sideSalads' | 'desserts' | 'soup', ConsultationMealCard[]>>} categories
 */
function sumCategoryMacros(categories) {
    const keys = /** @type {const} */ (['breakfasts', 'meals', 'sideSalads', 'desserts', 'soup']);

    return keys.reduce(
        (totals, key) => {
            for (const meal of categories[key] ?? []) {
                totals.calories += meal.caloriesNumber ?? parseMacro(meal.macros?.calories);
                totals.protein += parseMacro(meal.macros?.protein);
                totals.carbs += parseMacro(meal.macros?.carbs);
                totals.fat += parseMacro(meal.macros?.fat);
            }

            return totals;
        },
        { calories: 0, protein: 0, carbs: 0, fat: 0 },
    );
}

/**
 * Mirror backend day-level protein reconciliation for live consultation footer totals.
 *
 * @param {Partial<Record<'breakfasts' | 'meals' | 'sideSalads' | 'desserts' | 'soup', ConsultationMealCard[]>>} categories
 * @param {{ calories: number; protein: number; carbs: number; fat: number }} targets
 * @param {{ protein?: number; carbs?: number; fat?: number }} tolerance
 * @param {number} dayCalorieTarget
 * @param {number} dayCalorieTolerance
 * @param {number} mainSlotTargetCalories
 * @returns {ConsultationMealCard[]}
 */
export function reconcileConsultationDayMacros(
    categories,
    targets,
    tolerance,
    dayCalorieTarget,
    dayCalorieTolerance,
    mainSlotTargetCalories,
) {
    const meals = categories.meals ?? [];

    if (meals.length === 0) {
        return flattenConsultationCategories(categories);
    }

    const totals = sumCategoryMacros(categories);
    const proteinDeficit = Math.round((targets.protein - totals.protein) * 10) / 10;
    const proteinTolerance = tolerance.protein ?? 15;
    const carbDeficit = Math.round((targets.carbs - totals.carbs) * 10) / 10;
    const carbTolerance = tolerance.carbs ?? 20;
    const fatGap = Math.abs(Math.round((targets.fat - totals.fat) * 10) / 10);
    const fatTolerance = tolerance.fat ?? 15;
    const calorieDeficit = Math.round((dayCalorieTarget - totals.calories) * 10) / 10;
    const calorieSurplus = Math.round((totals.calories - dayCalorieTarget) * 10) / 10;
    const carbSurplus = Math.round((totals.carbs - targets.carbs) * 10) / 10;
    const fatSurplus = Math.round((totals.fat - targets.fat) * 10) / 10;

    if (proteinDeficit > proteinTolerance) {
        const fixedCalories =
            [...(categories.sideSalads ?? []), ...(categories.desserts ?? []), ...(categories.soup ?? [])].reduce(
                (sum, meal) => sum + (meal.caloriesNumber ?? parseMacro(meal.macros?.calories)),
                0,
            );
        const breakfastCalories = (categories.breakfasts ?? []).reduce(
            (sum, meal) => sum + (meal.caloriesNumber ?? parseMacro(meal.macros?.calories)),
            0,
        );
        const mainCalories = meals.reduce(
            (sum, meal) => sum + (meal.caloriesNumber ?? parseMacro(meal.macros?.calories)),
            0,
        );
        const maxDayCalories = dayCalorieTarget + dayCalorieTolerance;
        const maxMainCalories = Math.max(0, maxDayCalories - fixedCalories - breakfastCalories);
        const remainingCalorieBudget = Math.max(0, maxMainCalories - mainCalories);

        const compensatorIndexes = meals
            .map((meal, index) => (!meal.isVegan ? index : -1))
            .filter((index) => index >= 0);

        if (compensatorIndexes.length === 0 || remainingCalorieBudget <= 0) {
            return flattenConsultationCategories(categories);
        }

        const compensatingProtein = compensatorIndexes.reduce(
            (sum, index) => sum + parseMacro(meals[index]?.macros?.protein),
            0,
        );

        const extraCaloriesPerCompensator = remainingCalorieBudget / compensatorIndexes.length;

        const balancedMains = meals.map((meal, index) => {
            if (!compensatorIndexes.includes(index)) {
                return meal;
            }

            const currentProtein = parseMacro(meal.macros?.protein);
            const currentCalories = meal.caloriesNumber ?? parseMacro(meal.macros?.calories);

            if (currentProtein <= 0 || currentCalories <= 0) {
                return meal;
            }

            const proteinShare = currentProtein / compensatingProtein;
            const addedProtein = proteinDeficit * proteinShare;
            let boostMultiplier = (currentProtein + addedProtein) / currentProtein;
            const maxCalories = Math.max(currentCalories + extraCaloriesPerCompensator, mainSlotTargetCalories);
            boostMultiplier = Math.min(boostMultiplier, maxCalories / currentCalories);

            if (boostMultiplier <= 1.0001) {
                return meal;
            }

            return scaleConsultationMeal(meal, boostMultiplier);
        });

        return [
            ...(categories.breakfasts ?? []),
            ...balancedMains,
            ...(categories.sideSalads ?? []),
            ...(categories.desserts ?? []),
            ...(categories.soup ?? []),
        ];
    }

    if (
        calorieSurplus > dayCalorieTolerance &&
        proteinDeficit <= proteinTolerance &&
        (carbSurplus > carbTolerance || fatSurplus > fatTolerance)
    ) {
        const balancedMains = trimMainMealsForDaySurplus(
            meals,
            Math.max(0, carbSurplus),
            Math.max(0, fatSurplus),
            calorieSurplus,
        );

        return [
            ...(categories.breakfasts ?? []),
            ...balancedMains,
            ...(categories.sideSalads ?? []),
            ...(categories.desserts ?? []),
            ...(categories.soup ?? []),
        ];
    }

    if (
        calorieDeficit > dayCalorieTolerance &&
        carbDeficit > carbTolerance &&
        proteinDeficit <= proteinTolerance &&
        fatGap <= fatTolerance
    ) {
        const carbMainIndex = indexOfHighestCarbMain(meals);

        if (carbMainIndex >= 0) {
            const extraCarbs = Math.min(carbDeficit, calorieDeficit / 4);
            const balancedMains = meals.map((meal, index) =>
                index === carbMainIndex ? addCarbsToConsultationMeal(meal, extraCarbs) : meal,
            );

            return [
                ...(categories.breakfasts ?? []),
                ...balancedMains,
                ...(categories.sideSalads ?? []),
                ...(categories.desserts ?? []),
                ...(categories.soup ?? []),
            ];
        }
    }

    return flattenConsultationCategories(categories);
}
