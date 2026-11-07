/**
 * @typedef {{
 *   id: number;
 *   name: string;
 *   adaptedAmountGrams: number;
 *   adaptedAmount: number | null;
 *   unit: string;
 *   isPreparedBase?: boolean;
 * }} AdaptedKitchenIngredient
 */

/**
 * @typedef {{
 *   mealId: number;
 *   mealName: string;
 *   slot: string;
 *   imageUrl: string | null;
 *   instructions: string | string[];
 *   customerName: string;
 *   ingredients: AdaptedKitchenIngredient[];
 * }} AdaptedKitchenMeal
 */

/**
 * @typedef {'breakfast' | 'meal' | 'side salad' | 'soup' | 'dessert' | 'base recipe'} KitchenRecipeTypeLabel
 */

/**
 * @typedef {{
 *   key: string;
 *   kind: 'meal' | 'base';
 *   mealId: number;
 *   mealName: string;
 *   slot: string;
 *   typeLabel: KitchenRecipeTypeLabel;
 *   portions: number;
 *   portionsLabel: string;
 *   imageUrl: string | null;
 *   instructions: string | string[];
 *   perPortionIngredients: AdaptedKitchenIngredient[];
 *   totalGramsNeeded?: number;
 *   finishedWeightGrams?: number | null;
 * }} KitchenRecipeBatch
 */

/** @type {KitchenRecipeTypeLabel[]} */
export const KITCHEN_RECIPE_TYPE_ORDER = [
    'breakfast',
    'meal',
    'side salad',
    'soup',
    'dessert',
    'base recipe',
];

/**
 * @param {{ kind?: string; slot?: string }} batch
 * @returns {KitchenRecipeTypeLabel}
 */
export function kitchenRecipeTypeLabel(batch) {
    if (batch?.kind === 'base' || String(batch?.slot ?? '') === 'base') {
        return 'base recipe';
    }

    switch (String(batch?.slot ?? '').trim().toLowerCase()) {
        case 'breakfast':
            return 'breakfast';
        case 'side_salad':
        case 'side salad':
        case 'salad':
            return 'side salad';
        case 'soup':
            return 'soup';
        case 'dessert':
            return 'dessert';
        case 'main':
        case 'meal':
        default:
            return 'meal';
    }
}

/**
 * @param {KitchenRecipeTypeLabel | string} typeLabel
 * @returns {number}
 */
export function kitchenRecipeTypeSortIndex(typeLabel) {
    const index = KITCHEN_RECIPE_TYPE_ORDER.indexOf(/** @type {KitchenRecipeTypeLabel} */ (typeLabel));

    return index === -1 ? KITCHEN_RECIPE_TYPE_ORDER.length : index;
}

/**
 * @typedef {{
 *   id: number;
 *   name: string;
 *   instructions?: string;
 *   image_path?: string | null;
 *   finished_weight_grams?: number | null;
 *   total_adapted_grams: number;
 *   usage_count: number;
 *   components?: { id: number; name: string; amount_grams: number }[];
 * }} KitchenBaseRecipe
 */

/**
 * @typedef {{
 *   id: number;
 *   name: string;
 *   unit: string;
 *   perPortionGrams: number;
 *   totalGrams: number;
 *   perPortionAmount: number | null;
 *   totalAmount: number | null;
 *   displayLine: string;
 * }} ScaledBatchIngredient
 */

/**
 * @param {number} value
 * @param {number} [digits]
 */
function round(value, digits = 2) {
    const factor = 10 ** digits;

    return Math.round(value * factor) / factor;
}

/**
 * Stable signature so identical plate sizes batch together.
 *
 * @param {AdaptedKitchenIngredient[]} ingredients
 */
export function kitchenMealIngredientSignature(ingredients) {
    return [...ingredients]
        .map((ingredient) => {
            const id = Number(ingredient.id ?? 0);
            const grams = round(Number(ingredient.adaptedAmountGrams ?? 0), 2);

            return `${id}:${grams}`;
        })
        .sort()
        .join('|');
}

/**
 * One portion = one customer slot meal. Groups by meal id + ingredient gram signature.
 *
 * @param {AdaptedKitchenMeal[]} adaptedMeals
 * @returns {KitchenRecipeBatch[]}
 */
export function aggregateKitchenRecipeBatches(adaptedMeals) {
    /** @type {Map<string, KitchenRecipeBatch>} */
    const batches = new Map();

    for (const meal of adaptedMeals ?? []) {
        const mealId = Number(meal.mealId ?? 0);
        const mealName = String(meal.mealName ?? '').trim() || 'Meal';
        const ingredients = Array.isArray(meal.ingredients) ? meal.ingredients : [];
        const signature = kitchenMealIngredientSignature(ingredients);
        const key = `${mealId}|${signature || 'empty'}`;

        const existing = batches.get(key);
        if (existing) {
            existing.portions += 1;
            if (!existing.imageUrl && meal.imageUrl) {
                existing.imageUrl = meal.imageUrl;
            }
            if (
                (!existing.instructions || String(existing.instructions).trim() === '') &&
                meal.instructions
            ) {
                existing.instructions = meal.instructions;
            }
            continue;
        }

        const slot = String(meal.slot ?? '');

        batches.set(key, {
            key,
            kind: 'meal',
            mealId,
            mealName,
            slot,
            typeLabel: kitchenRecipeTypeLabel({ kind: 'meal', slot }),
            portions: 1,
            portionsLabel: '1',
            imageUrl: meal.imageUrl ?? null,
            instructions: meal.instructions ?? '',
            perPortionIngredients: ingredients.map((ingredient) => ({
                id: Number(ingredient.id ?? 0),
                name: String(ingredient.name ?? ''),
                adaptedAmountGrams: Number(ingredient.adaptedAmountGrams ?? 0),
                adaptedAmount:
                    ingredient.adaptedAmount !== null && ingredient.adaptedAmount !== undefined
                        ? Number(ingredient.adaptedAmount)
                        : null,
                unit: String(ingredient.unit ?? 'g') || 'g',
                isPreparedBase: Boolean(ingredient.isPreparedBase),
            })),
        });
    }

    return [...batches.values()]
        .map((batch) => ({
            ...batch,
            typeLabel: batch.typeLabel ?? kitchenRecipeTypeLabel(batch),
            portionsLabel: String(batch.portions),
        }))
        .sort(
            (a, b) =>
                kitchenRecipeTypeSortIndex(a.typeLabel) - kitchenRecipeTypeSortIndex(b.typeLabel) ||
                a.mealName.localeCompare(b.mealName) ||
                a.key.localeCompare(b.key),
        );
}

/**
 * Turn API base recipes into kitchen recipe-batch rows (prep packs for the day).
 *
 * @param {KitchenBaseRecipe[]} baseRecipes
 * @returns {KitchenRecipeBatch[]}
 */
export function aggregateKitchenBaseRecipeBatches(baseRecipes) {
    /** @type {KitchenRecipeBatch[]} */
    const batches = [];

    for (const base of baseRecipes ?? []) {
        const id = Number(base.id ?? 0);
        const name = String(base.name ?? '').trim();
        const totalGramsNeeded = round(Number(base.total_adapted_grams ?? 0), 2);
        const usageCount = Math.max(0, Number(base.usage_count ?? 0));

        if (id <= 0 || name === '' || totalGramsNeeded <= 0) {
            continue;
        }

        const components = Array.isArray(base.components) ? base.components : [];
        const finishedWeightGrams =
            base.finished_weight_grams !== null &&
            base.finished_weight_grams !== undefined &&
            Number.isFinite(Number(base.finished_weight_grams)) &&
            Number(base.finished_weight_grams) > 0
                ? Number(base.finished_weight_grams)
                : null;

        const batchDivisor =
            finishedWeightGrams ??
            components.reduce((sum, component) => {
                const grams = Number(component.amount_grams ?? 0);

                return sum + (Number.isFinite(grams) && grams > 0 ? grams : 0);
            }, 0);

        const scale = batchDivisor > 0 ? totalGramsNeeded / batchDivisor : 0;

        batches.push({
            key: `base:${id}`,
            kind: 'base',
            mealId: id,
            mealName: name,
            slot: 'base',
            typeLabel: 'base recipe',
            portions: usageCount > 0 ? usageCount : 1,
            portionsLabel: `${Math.round(totalGramsNeeded)}g`,
            imageUrl: base.image_path ? String(base.image_path) : null,
            instructions: base.instructions ?? '',
            totalGramsNeeded,
            finishedWeightGrams,
            perPortionIngredients: components
                .map((component) => {
                    const amountGrams = Number(component.amount_grams ?? 0);
                    if (!Number.isFinite(amountGrams) || amountGrams <= 0) {
                        return null;
                    }

                    return {
                        id: Number(component.id ?? 0),
                        name: String(component.name ?? ''),
                        adaptedAmountGrams: round(amountGrams * (scale > 0 ? scale : 1), 4),
                        adaptedAmount: null,
                        unit: 'g',
                    };
                })
                .filter(Boolean),
        });
    }

    return batches.sort(
        (a, b) =>
            kitchenRecipeTypeSortIndex(a.typeLabel) - kitchenRecipeTypeSortIndex(b.typeLabel) ||
            a.mealName.localeCompare(b.mealName) ||
            a.key.localeCompare(b.key),
    );
}

/**
 * Merge meal + base batches and keep kitchen type order.
 *
 * @param {KitchenRecipeBatch[]} mealBatches
 * @param {KitchenRecipeBatch[]} baseBatches
 * @returns {KitchenRecipeBatch[]}
 */
export function mergeKitchenRecipeBatches(mealBatches, baseBatches) {
    return [...(mealBatches ?? []), ...(baseBatches ?? [])].sort(
        (a, b) =>
            kitchenRecipeTypeSortIndex(a.typeLabel ?? kitchenRecipeTypeLabel(a)) -
                kitchenRecipeTypeSortIndex(b.typeLabel ?? kitchenRecipeTypeLabel(b)) ||
            a.mealName.localeCompare(b.mealName) ||
            a.key.localeCompare(b.key),
    );
}

/**
 * Format a scaled ingredient line for kitchen print packs.
 *
 * @param {ScaledBatchIngredient} ingredient
 */
export function formatScaledIngredientDisplay(ingredient) {
    if (ingredient.totalAmount !== null && ingredient.unit && ingredient.unit !== 'g') {
        return `${round(ingredient.totalAmount, 2)}${ingredient.unit} ${ingredient.name}`;
    }

    return `${round(ingredient.totalGrams, 1)}g ${ingredient.name}`;
}

/**
 * Scale a batch's per-portion ingredients to the day's portion count.
 *
 * @param {KitchenRecipeBatch} batch
 * @returns {ScaledBatchIngredient[]}
 */
export function scaleKitchenRecipeBatchIngredients(batch) {
    // Base batches already store component grams scaled to the day's total need.
    const portions = batch?.kind === 'base' ? 1 : Math.max(0, Number(batch.portions ?? 0));

    return (batch.perPortionIngredients ?? [])
        .filter((ingredient) => Number(ingredient.adaptedAmountGrams ?? 0) > 0)
        .map((ingredient) => {
            const perPortionGrams = Number(ingredient.adaptedAmountGrams ?? 0);
            const perPortionAmount =
                ingredient.adaptedAmount !== null &&
                ingredient.adaptedAmount !== undefined &&
                Number.isFinite(Number(ingredient.adaptedAmount))
                    ? Number(ingredient.adaptedAmount)
                    : null;
            const totalGrams = round(perPortionGrams * portions, 2);
            const totalAmount =
                perPortionAmount !== null ? round(perPortionAmount * portions, 4) : null;

            /** @type {ScaledBatchIngredient & { isPreparedBase?: boolean }} */
            const scaled = {
                id: Number(ingredient.id ?? 0),
                name: String(ingredient.name ?? ''),
                unit: String(ingredient.unit ?? 'g') || 'g',
                perPortionGrams,
                totalGrams,
                perPortionAmount,
                totalAmount,
                displayLine: '',
                isPreparedBase: Boolean(ingredient.isPreparedBase),
            };
            scaled.displayLine = formatScaledIngredientDisplay(scaled);

            return scaled;
        });
}

/**
 * @typedef {{
 *   ingredient: string;
 *   unit: string;
 *   totalAmount: number;
 *   recipeCount: number;
 *   portionCount: number;
 *   usedInRecipes: string[];
 * }} KitchenBulkIngredientLine
 */

/**
 * @param {KitchenBaseRecipe} base
 * @returns {number}
 */
function baseRecipeBatchDivisorGrams(base) {
    const finished = Number(base?.finished_weight_grams ?? 0);

    if (Number.isFinite(finished) && finished > 0) {
        return finished;
    }

    return (base?.components ?? []).reduce((sum, component) => {
        const grams = Number(component?.amount_grams ?? 0);

        return sum + (Number.isFinite(grams) && grams > 0 ? grams : 0);
    }, 0);
}

/**
 * @param {Map<string, { ingredient: string; unit: string; totalGrams: number; recipeKeys: Map<string, string>; portionCount: number }>} map
 * @param {{ id?: number; name: string; unit?: string; totalGrams: number }} ingredient
 * @param {string} batchKey
 * @param {string} recipeName
 * @param {number} portions
 */
function addBulkIngredientLine(map, ingredient, batchKey, recipeName, portions) {
    const id = Number(ingredient.id ?? 0);
    const name = String(ingredient.name ?? '').trim();
    const unit = String(ingredient.unit ?? 'g') || 'g';
    const totalGrams = Number(ingredient.totalGrams ?? 0);

    if (!name || totalGrams <= 0) {
        return;
    }

    const key = id > 0 ? `id:${id}|${unit}` : `name:${name.toLowerCase()}|${unit}`;
    const existing = map.get(key);

    if (existing) {
        existing.totalGrams += totalGrams;
        existing.portionCount += portions;
        if (batchKey) {
            existing.recipeKeys.set(batchKey, recipeName);
        }

        return;
    }

    map.set(key, {
        ingredient: name,
        unit,
        totalGrams,
        recipeKeys: new Map(batchKey ? [[batchKey, recipeName]] : []),
        portionCount: portions,
    });
}

/**
 * Aggregate scaled recipe-batch ingredients into day bulk totals.
 * Prepared base recipes are exploded into their components (not listed as a whole base).
 *
 * @param {KitchenRecipeBatch[]} batches
 * @param {KitchenBaseRecipe[]} [baseRecipes]
 * @returns {KitchenBulkIngredientLine[]}
 */
export function aggregateBulkIngredientsFromRecipeBatches(batches, baseRecipes = []) {
    /** @type {Map<number, KitchenBaseRecipe>} */
    const basesById = new Map();

    for (const base of baseRecipes ?? []) {
        const id = Number(base?.id ?? 0);

        if (id > 0) {
            basesById.set(id, base);
        }
    }

    /** @type {Map<string, { ingredient: string; unit: string; totalGrams: number; recipeKeys: Map<string, string>; portionCount: number }>} */
    const map = new Map();

    for (const batch of batches ?? []) {
        if (batch?.kind === 'base') {
            continue;
        }

        const scaled = scaleKitchenRecipeBatchIngredients(batch);
        const batchKey = String(batch.key ?? '');
        const recipeName = String(batch.mealName ?? '').trim() || 'Recipe';
        const portions = Math.max(0, Number(batch.portions ?? 0));

        for (const ingredient of scaled) {
            const id = Number(ingredient.id ?? 0);
            const base = id > 0 ? basesById.get(id) : undefined;
            const isPreparedBase =
                Boolean(ingredient.isPreparedBase) ||
                Boolean(base) ||
                /\(base\)\s*$/i.test(String(ingredient.name ?? ''));

            if (isPreparedBase) {
                const components = Array.isArray(base?.components) ? base.components : [];
                const divisor = base ? baseRecipeBatchDivisorGrams(base) : 0;
                const portionGrams = Number(ingredient.totalGrams ?? 0);

                if (components.length > 0 && divisor > 0 && portionGrams > 0) {
                    const scale = portionGrams / divisor;

                    for (const component of components) {
                        const componentGrams = Number(component.amount_grams ?? 0);

                        if (!Number.isFinite(componentGrams) || componentGrams <= 0) {
                            continue;
                        }

                        addBulkIngredientLine(
                            map,
                            {
                                id: Number(component.id ?? 0),
                                name: String(component.name ?? ''),
                                unit: 'g',
                                totalGrams: componentGrams * scale,
                            },
                            batchKey,
                            recipeName,
                            portions,
                        );
                    }
                }

                continue;
            }

            addBulkIngredientLine(
                map,
                {
                    id,
                    name: String(ingredient.name ?? ''),
                    unit: String(ingredient.unit ?? 'g') || 'g',
                    totalGrams: Number(ingredient.totalGrams ?? 0),
                },
                batchKey,
                recipeName,
                portions,
            );
        }
    }

    return [...map.values()]
        .map((row) => {
            const usedInRecipes = [...row.recipeKeys.values()].sort((a, b) => a.localeCompare(b));

            return {
                ingredient: row.ingredient,
                unit: row.unit,
                totalAmount: round(row.totalGrams, 1),
                recipeCount: row.recipeKeys.size,
                portionCount: row.portionCount,
                usedInRecipes,
            };
        })
        .sort((a, b) => a.ingredient.localeCompare(b.ingredient));
}
