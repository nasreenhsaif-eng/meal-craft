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
 * @param {number} proteinReduction
 */
function removeProteinFromConsultationMeal(meal, proteinReduction) {
    if (proteinReduction <= 0) {
        return meal;
    }

    const carbs = parseMacro(meal.macros?.carbs);
    const fat = parseMacro(meal.macros?.fat);
    const currentProtein = parseMacro(meal.macros?.protein);
    const removed = Math.min(proteinReduction, Math.max(0, currentProtein - 1));
    const protein = currentProtein - removed;
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
function indexOfHighestProteinMain(meals) {
    let bestIndex = -1;
    let bestProtein = 0;

    meals.forEach((meal, index) => {
        const protein = parseMacro(meal.macros?.protein);

        if (protein > bestProtein) {
            bestProtein = protein;
            bestIndex = index;
        }
    });

    return bestIndex;
}

/**
 * Kitchen-real day surplus: trim starchy carbs, then protein. Never cooking fat.
 *
 * @param {ConsultationMealCard[]} meals
 * @param {number} carbSurplus
 * @param {number} proteinTrimRoom
 * @param {number} calorieSurplus
 */
function trimMainMealsForDaySurplus(meals, carbSurplus, proteinTrimRoom, calorieSurplus) {
    let balanced = [...meals];
    let remainingCalorieSurplus = calorieSurplus;
    let remainingProteinTrim = Math.max(0, proteinTrimRoom);

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

    if (remainingProteinTrim > 0 && remainingCalorieSurplus > 0) {
        const proteinMainIndex = indexOfHighestProteinMain(balanced);

        if (proteinMainIndex >= 0) {
            const proteinReduction = Math.min(remainingProteinTrim, remainingCalorieSurplus / 4);
            balanced = balanced.map((meal, index) =>
                index === proteinMainIndex
                    ? removeProteinFromConsultationMeal(meal, proteinReduction)
                    : meal,
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
 * Calories shown on a consultation meal card (MacroGrid uses `macros.calories`).
 *
 * @param {{ caloriesNumber?: number; macros?: { calories?: number | string } } | null | undefined} meal
 */
export function consultationMealCardCalories(meal) {
    const fromMacros = parseMacro(meal?.macros?.calories);
    if (fromMacros > 0) {
        return Math.round(fromMacros);
    }

    const fromNumber = meal?.caloriesNumber;

    return typeof fromNumber === 'number' && Number.isFinite(fromNumber) ? Math.round(fromNumber) : 0;
}

/**
 * Honest day calorie total for the consultation footer — sum adapted meal-card calories.
 * Must match the cards on screen (never invent a lower total via surplus math alone).
 *
 * @param {Array<{ caloriesNumber?: number; macros?: { calories?: number | string } } | null | undefined>} meals
 */
export function sumConsultationMealCardCalories(meals) {
    if (!Array.isArray(meals) || meals.length === 0) {
        return 0;
    }

    return meals.reduce((acc, meal) => acc + consultationMealCardCalories(meal), 0);
}

/**
 * Honest day macro totals for the consultation footer — flat sum of selected card macros.
 * Avoids dropping meals that lack `slot` when grouping by category.
 *
 * @param {Array<ConsultationMealCard | null | undefined>} meals
 * @returns {{ calories: number; protein: number; carbs: number; fat: number }}
 */
export function sumConsultationMealCardMacros(meals) {
    if (!Array.isArray(meals) || meals.length === 0) {
        return { calories: 0, protein: 0, carbs: 0, fat: 0 };
    }

    return meals.reduce(
        (totals, meal) => {
            if (!meal) {
                return totals;
            }

            return {
                calories: totals.calories + consultationMealCardCalories(meal),
                protein: totals.protein + parseMacro(meal.macros?.protein),
                carbs: totals.carbs + parseMacro(meal.macros?.carbs),
                fat: totals.fat + parseMacro(meal.macros?.fat),
            };
        },
        { calories: 0, protein: 0, carbs: 0, fat: 0 },
    );
}

/**
 * Close day macros for consultation — protein boost / carb refill / protein+starch trim.
 * Cooking fat is never trimmed (kitchen-real). Prefer {@link sumConsultationMealCardCalories}
 * for footer totals so the UI matches adapted meal cards.
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
    const calorieDeficit = Math.round((dayCalorieTarget - totals.calories) * 10) / 10;
    const calorieSurplus = Math.round((totals.calories - dayCalorieTarget) * 10) / 10;
    const carbSurplus = Math.round((totals.carbs - targets.carbs) * 10) / 10;

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
            // Day calorie headroom only — never expand past leftovers using the slot target.
            const maxCalories = currentCalories + extraCaloriesPerCompensator;
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

    if (calorieSurplus > dayCalorieTolerance) {
        // Prefer starch; protein may trim while staying at/above target − tol. Never trim fat.
        const proteinDayFloor = targets.protein - proteinTolerance;
        const proteinTrimRoom = Math.max(0, totals.protein - proteinDayFloor);
        const balancedMains = trimMainMealsForDaySurplus(
            meals,
            Math.max(0, carbSurplus),
            proteinTrimRoom,
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

    // Fat may sit outside tolerance when cooking oil is frozen — still refill starch for calories.
    if (calorieDeficit > dayCalorieTolerance && proteinDeficit <= proteinTolerance) {
        const carbMainIndex = indexOfHighestCarbMain(meals);
        const carbFillGrams = Math.max(carbDeficit, calorieDeficit / 4);

        if (carbMainIndex >= 0 && carbFillGrams > 0.25) {
            const balancedMains = meals.map((meal, index) =>
                index === carbMainIndex ? addCarbsToConsultationMeal(meal, carbFillGrams) : meal,
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
