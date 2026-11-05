/**
 * Customer Cook It Yourself shopping list — same row shape and merge rules as the
 * admin meal-plan library list, stored per authenticated user in this browser.
 */

import {
    aggregateSelectedDayShoppingIngredients,
    canonicalShoppingIngredientName,
    mergeShoppingListItems,
    parseIngredientDisplayLine,
    shoppingIngredientMergeKey,
} from '../Pages/MealPlanLibrary/dayShoppingList.js';

/**
 * @typedef {import('../Pages/MealPlanLibrary/dayShoppingList.js').DayShoppingIngredient} DayShoppingIngredient
 */

/**
 * @param {number | string | null | undefined} userId
 * @returns {string}
 */
export function customerShoppingListStorageKey(userId) {
    return `mc-customer-shopping-list-${userId ?? 0}`;
}

/**
 * @param {number | string | null | undefined} userId
 * @returns {DayShoppingIngredient[]}
 */
export function readCustomerShoppingList(userId) {
    if (typeof window === 'undefined' || userId == null || userId === '') {
        return [];
    }

    try {
        const raw = window.localStorage.getItem(customerShoppingListStorageKey(userId));
        const parsed = raw ? JSON.parse(raw) : [];

        if (!Array.isArray(parsed)) {
            return [];
        }

        return parsed
            .map((row) => ({
                key: String(row?.key ?? ''),
                ingredientId: Number(row?.ingredientId ?? 0),
                name: String(row?.name ?? '').trim(),
                unit: String(row?.unit ?? 'g').trim() || 'g',
                amount: Number(row?.amount ?? 0),
            }))
            .filter((row) => row.key !== '' && row.name !== '' && Number.isFinite(row.amount) && row.amount > 0);
    } catch {
        return [];
    }
}

/**
 * @param {number | string | null | undefined} userId
 * @param {DayShoppingIngredient[]} items
 */
export function writeCustomerShoppingList(userId, items) {
    if (typeof window === 'undefined' || userId == null || userId === '') {
        return;
    }

    try {
        window.localStorage.setItem(customerShoppingListStorageKey(userId), JSON.stringify(items));
    } catch {
        // ignore storage errors
    }
}

/**
 * @param {DayShoppingIngredient[]} existing
 * @param {string[]} keysToRemove
 * @returns {DayShoppingIngredient[]}
 */
export function removeShoppingListKeys(existing, keysToRemove) {
    const remove = new Set(keysToRemove.map((key) => String(key)));

    return existing.filter((row) => !remove.has(row.key));
}

/**
 * Shopping rows for one meal card (admin aggregator, single meal).
 *
 * @param {unknown} meal
 * @returns {DayShoppingIngredient[]}
 */
export function shoppingIngredientsForMeal(meal) {
    if (!meal || typeof meal !== 'object') {
        return [];
    }

    const fromKitchen = aggregateSelectedDayShoppingIngredients({ meal: [meal] });

    if (fromKitchen.length > 0) {
        return fromKitchen;
    }

    return shoppingRowsFromDetailView(meal.detailView);
}

/**
 * One shopping row for a recipe line the customer can see.
 * Quantity lines keep grams; lines like "2 eggs raw" stay as a single item.
 *
 * @param {string} line
 * @param {number | null | undefined} [ingredientId]
 * @returns {DayShoppingIngredient | null}
 */
export function shoppingRowFromDisplayLine(line, ingredientId = 0) {
    const raw = String(line ?? '').trim();

    if (raw === '' || /no ingredients/i.test(raw)) {
        return null;
    }

    const parsed = parseIngredientDisplayLine(raw);
    const id = Number(ingredientId ?? 0) || 0;

    if (parsed && parsed.amount > 0) {
        const name = canonicalShoppingIngredientName(parsed.name) || parsed.name;

        return {
            key: shoppingIngredientMergeKey({ name, unit: parsed.unit, ingredientId: id }),
            ingredientId: id,
            name,
            unit: parsed.unit,
            amount: parsed.amount,
        };
    }

    return {
        key: shoppingIngredientMergeKey({ name: raw, unit: 'item', ingredientId: id }),
        ingredientId: id,
        name: raw,
        unit: 'item',
        amount: 1,
    };
}

/**
 * @param {unknown} detailView
 * @returns {DayShoppingIngredient[]}
 */
export function shoppingRowsFromDetailView(detailView) {
    if (!detailView || typeof detailView !== 'object') {
        return [];
    }

    /** @type {Array<{ line: string, ingredientId?: number }>} */
    const items = [];
    const sections = Array.isArray(detailView.ingredientSections) ? detailView.ingredientSections : [];

    if (sections.length > 0) {
        for (const section of sections) {
            const sectionItems = Array.isArray(section?.items) ? section.items : [];

            for (const item of sectionItems) {
                if (typeof item === 'string') {
                    items.push({ line: item });
                } else if (item && typeof item === 'object') {
                    items.push({
                        line: String(item.line ?? ''),
                        ingredientId: Number(item.ingredientId ?? 0) || 0,
                    });
                }
            }
        }
    } else if (Array.isArray(detailView.ingredientItems) && detailView.ingredientItems.length > 0) {
        for (const item of detailView.ingredientItems) {
            if (typeof item === 'string') {
                items.push({ line: item });
            } else if (item && typeof item === 'object') {
                items.push({
                    line: String(item.line ?? ''),
                    ingredientId: Number(item.ingredientId ?? 0) || 0,
                });
            }
        }
    } else if (Array.isArray(detailView.ingredients)) {
        for (const line of detailView.ingredients) {
            if (typeof line === 'string') {
                items.push({ line });
            }
        }
    }

    /** @type {Map<string, DayShoppingIngredient>} */
    const byKey = new Map();

    for (const item of items) {
        const row = shoppingRowFromDisplayLine(item.line, item.ingredientId);

        if (!row || byKey.has(row.key)) {
            continue;
        }

        byKey.set(row.key, row);
    }

    return [...byKey.values()];
}

export { mergeShoppingListItems, aggregateSelectedDayShoppingIngredients };

/**
 * @param {number} amount
 * @param {string} unit
 */
export function formatShoppingAmount(amount, unit) {
    const normalizedUnit = String(unit ?? '').trim();

    if (normalizedUnit === '' || normalizedUnit === 'item') {
        return '';
    }

    const rounded = Number.isInteger(amount) ? String(amount) : amount.toFixed(1).replace(/\.0$/, '');

    return `${rounded} ${normalizedUnit}`;
}
