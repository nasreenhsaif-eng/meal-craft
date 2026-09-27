/**
 * Aggregate ingredient grams from selected meals for one day.
 *
 * Prefers `kitchenIngredientRows` (adapted grams). Falls back to
 * `detailView.ingredients` string lines like "120g Beef Liver".
 */

/**
 * @typedef {{
 *   key: string;
 *   ingredientId: number;
 *   name: string;
 *   unit: string;
 *   amount: number;
 * }} DayShoppingIngredient
 */

/**
 * @param {string} line
 * @returns {{ name: string; amount: number; unit: string } | null}
 */
export function parseIngredientDisplayLine(line) {
    const raw = String(line ?? '').trim();

    if (raw === '') {
        return null;
    }

    const match = raw.match(/^([\d.]+)\s*(g|kg|ml|l)\s+(.+)$/i);

    if (match) {
        const amount = Number.parseFloat(match[1]);
        const unit = String(match[2] ?? 'g').toLowerCase();
        const name = String(match[3] ?? '').trim();

        if (!Number.isFinite(amount) || amount <= 0 || name === '') {
            return null;
        }

        return { name, amount, unit };
    }

    return { name: raw, amount: 0, unit: 'g' };
}

/**
 * @param {unknown} meal
 * @returns {DayShoppingIngredient[]}
 */
function ingredientsFromMeal(meal) {
    /** @type {DayShoppingIngredient[]} */
    const out = [];

    const kitchenRows = Array.isArray(meal?.kitchenIngredientRows) ? meal.kitchenIngredientRows : [];

    if (kitchenRows.length > 0) {
        for (const row of kitchenRows) {
            const name = String(row?.selectedName ?? row?.nameQuery ?? '').trim();
            const amount = Number.parseFloat(String(row?.amount ?? ''));
            const unit = String(row?.unit ?? 'g').trim() || 'g';
            const ingredientId = Number(row?.ingredientId ?? 0);

            if (name === '' || !Number.isFinite(amount) || amount <= 0) {
                continue;
            }

            const key =
                ingredientId > 0
                    ? `id:${ingredientId}`
                    : `name:${name.toLowerCase()}|${unit.toLowerCase()}`;

            out.push({ key, ingredientId, name, unit, amount });
        }

        return out;
    }

    const detailLines = Array.isArray(meal?.detailView?.ingredients) ? meal.detailView.ingredients : [];

    for (const line of detailLines) {
        if (typeof line !== 'string') {
            continue;
        }

        const parsed = parseIngredientDisplayLine(line);

        if (!parsed || parsed.amount <= 0) {
            continue;
        }

        const key = `name:${parsed.name.toLowerCase()}|${parsed.unit.toLowerCase()}`;
        out.push({
            key,
            ingredientId: 0,
            name: parsed.name,
            unit: parsed.unit,
            amount: parsed.amount,
        });
    }

    return out;
}

/**
 * @param {Record<string, unknown[]> | null | undefined} selectedCategories
 * @returns {DayShoppingIngredient[]}
 */
export function aggregateSelectedDayShoppingIngredients(selectedCategories) {
    /** @type {Map<string, DayShoppingIngredient>} */
    const byKey = new Map();

    for (const meals of Object.values(selectedCategories ?? {})) {
        if (!Array.isArray(meals)) {
            continue;
        }

        for (const meal of meals) {
            for (const row of ingredientsFromMeal(meal)) {
                const prev = byKey.get(row.key);

                if (prev) {
                    prev.amount += row.amount;
                } else {
                    byKey.set(row.key, { ...row });
                }
            }
        }
    }

    const list = [...byKey.values()].map((row) => ({
        ...row,
        amount: Math.round(row.amount * 100) / 100,
    }));

    list.sort((a, b) => a.name.localeCompare(b.name));

    return list;
}

/**
 * @param {number} mealPlanId
 * @returns {string}
 */
export function shoppingListStorageKey(mealPlanId) {
    return `mc-meal-plan-shopping-list-${mealPlanId}`;
}

/**
 * @param {number} mealPlanId
 * @returns {DayShoppingIngredient[]}
 */
export function readStoredShoppingList(mealPlanId) {
    if (typeof window === 'undefined' || mealPlanId <= 0) {
        return [];
    }

    try {
        const raw = window.localStorage.getItem(shoppingListStorageKey(mealPlanId));
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
 * @param {number} mealPlanId
 * @param {DayShoppingIngredient[]} items
 */
export function writeStoredShoppingList(mealPlanId, items) {
    if (typeof window === 'undefined' || mealPlanId <= 0) {
        return;
    }

    try {
        window.localStorage.setItem(shoppingListStorageKey(mealPlanId), JSON.stringify(items));
    } catch {
        // ignore storage errors
    }
}

/**
 * Merge day selections into the saved shopping list (sum amounts for matching keys).
 *
 * @param {DayShoppingIngredient[]} existing
 * @param {DayShoppingIngredient[]} additions
 * @returns {DayShoppingIngredient[]}
 */
export function mergeShoppingListItems(existing, additions) {
    /** @type {Map<string, DayShoppingIngredient>} */
    const byKey = new Map();

    for (const row of existing) {
        byKey.set(row.key, { ...row });
    }

    for (const row of additions) {
        const prev = byKey.get(row.key);

        if (prev) {
            prev.amount = Math.round((prev.amount + row.amount) * 100) / 100;
        } else {
            byKey.set(row.key, { ...row });
        }
    }

    const list = [...byKey.values()];
    list.sort((a, b) => a.name.localeCompare(b.name));

    return list;
}
