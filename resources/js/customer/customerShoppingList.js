/**
 * Customer Cook It Yourself shopping list — same row shape and merge rules as the
 * admin meal-plan library list, stored per authenticated user in this browser.
 */

import {
    aggregateSelectedDayShoppingIngredients,
    mergeShoppingListItems,
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

    return aggregateSelectedDayShoppingIngredients({ meal: [meal] });
}

export { mergeShoppingListItems, aggregateSelectedDayShoppingIngredients };

/**
 * @param {number} amount
 * @param {string} unit
 */
export function formatShoppingAmount(amount, unit) {
    const rounded = Number.isInteger(amount) ? String(amount) : amount.toFixed(1).replace(/\.0$/, '');

    return `${rounded} ${unit}`;
}
