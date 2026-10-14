/**
 * Aggregate ingredient grams from selected meals for one day.
 *
 * Prefers `kitchenIngredientRows` (adapted grams). Falls back to
 * `detailView.ingredients` string lines like "120g Beef Liver".
 *
 * Prepared base recipes are expanded into scaled component ingredients using
 * `finished_weight_grams` (cooked yield) when available, else the raw component sum.
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
 * @typedef {{
 *   id: number;
 *   name: string;
 *   amount_grams: number;
 * }} ShoppingBaseComponent
 */

/**
 * @typedef {{
 *   id: number;
 *   name?: string;
 *   is_prepared_base?: boolean;
 *   finished_weight_grams?: number | null;
 *   components?: ShoppingBaseComponent[];
 * }} ShoppingIngredientProfile
 */

const PREP_STATE_SUFFIX_RE =
    /\s*\((?:base|cooked plated portion|prepared dressing|prepared portion|dry weight|drained weight|drained canned|raw, before cooking)\)\s*$/iu;

/** Leading kitchen spoon / pinch labels, e.g. "(1 tbsp) ", "(½ tsp) ", "(pinch) ". */
const SPOON_PREFIX_RE = /^\([^)]*\)\s+/u;

/**
 * Strip spoon prefixes and prep-state suffixes so the same food merges to one line.
 *
 * @param {string} name
 * @returns {string}
 */
export function canonicalShoppingIngredientName(name) {
    let next = String(name ?? '').trim();
    next = next.replace(SPOON_PREFIX_RE, '').trim();
    next = next.replace(PREP_STATE_SUFFIX_RE, '').trim();

    return next;
}

/**
 * @param {{ name?: string; unit?: string; ingredientId?: number }} row
 * @returns {string}
 */
export function shoppingIngredientMergeKey(row) {
    const canonical = canonicalShoppingIngredientName(row?.name ?? '').toLowerCase();
    const unit = String(row?.unit ?? 'g').trim().toLowerCase() || 'g';

    if (canonical === '') {
        const id = Number(row?.ingredientId ?? 0);

        return id > 0 ? `id:${id}|${unit}` : `empty|${unit}`;
    }

    return `name:${canonical}|${unit}`;
}

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
 * @param {ShoppingIngredientProfile[] | null | undefined} profiles
 * @returns {Map<number, ShoppingIngredientProfile>}
 */
export function indexShoppingIngredientProfiles(profiles) {
    /** @type {Map<number, ShoppingIngredientProfile>} */
    const byId = new Map();

    for (const profile of profiles ?? []) {
        const id = Number(profile?.id ?? 0);

        if (id > 0) {
            byId.set(id, profile);
        }
    }

    return byId;
}

/**
 * @param {ShoppingIngredientProfile | undefined} profile
 * @returns {boolean}
 */
function profileHasExplodableComponents(profile) {
    return Array.isArray(profile?.components) && profile.components.length > 0;
}

/**
 * @param {ShoppingBaseComponent[]} components
 * @param {number | null | undefined} finishedWeightGrams
 * @returns {number}
 */
function baseBatchDivisorGrams(components, finishedWeightGrams) {
    const finished = Number(finishedWeightGrams ?? 0);

    if (Number.isFinite(finished) && finished > 0) {
        return finished;
    }

    return components.reduce((sum, row) => {
        const grams = Number(row?.amount_grams ?? 0);

        return sum + (Number.isFinite(grams) && grams > 0 ? grams : 0);
    }, 0);
}

/**
 * Expand a plated base portion into shopping rows (recursively for nested bases).
 *
 * @param {number} portionGrams
 * @param {ShoppingIngredientProfile} profile
 * @param {Map<number, ShoppingIngredientProfile>} profilesById
 * @param {Set<number>} expandingIds
 * @returns {DayShoppingIngredient[]}
 */
export function explodePreparedBasePortion(portionGrams, profile, profilesById, expandingIds = new Set()) {
    if (!Number.isFinite(portionGrams) || portionGrams <= 0 || !profileHasExplodableComponents(profile)) {
        return [];
    }

    const baseId = Number(profile.id ?? 0);

    if (baseId > 0) {
        if (expandingIds.has(baseId)) {
            return [];
        }

        expandingIds.add(baseId);
    }

    const components = profile.components ?? [];
    const divisor = baseBatchDivisorGrams(components, profile.finished_weight_grams);

    if (divisor <= 0) {
        if (baseId > 0) {
            expandingIds.delete(baseId);
        }

        return [];
    }

    const scale = portionGrams / divisor;
    /** @type {DayShoppingIngredient[]} */
    const out = [];

    for (const component of components) {
        const childId = Number(component?.id ?? 0);
        const childName = String(component?.name ?? '').trim();
        const batchGrams = Number(component?.amount_grams ?? 0);

        if (childName === '' || !Number.isFinite(batchGrams) || batchGrams <= 0) {
            continue;
        }

        const scaledGrams = batchGrams * scale;
        const childProfile = childId > 0 ? profilesById.get(childId) : undefined;

        if (profileHasExplodableComponents(childProfile)) {
            out.push(
                ...explodePreparedBasePortion(scaledGrams, childProfile, profilesById, new Set(expandingIds)),
            );
            continue;
        }

        const displayName = canonicalShoppingIngredientName(childName) || childName;

        out.push({
            key: shoppingIngredientMergeKey({ name: displayName, unit: 'g', ingredientId: childId }),
            ingredientId: childId,
            name: displayName,
            unit: 'g',
            amount: scaledGrams,
        });
    }

    if (baseId > 0) {
        expandingIds.delete(baseId);
    }

    return out;
}

/**
 * @param {DayShoppingIngredient} row
 * @param {Map<number, ShoppingIngredientProfile>} profilesById
 * @returns {DayShoppingIngredient[]}
 */
function shoppingRowsForIngredient(row, profilesById) {
    const profile = row.ingredientId > 0 ? profilesById.get(row.ingredientId) : undefined;

    if (profileHasExplodableComponents(profile)) {
        return explodePreparedBasePortion(row.amount, profile, profilesById);
    }

    return [row];
}

/**
 * @param {unknown} meal
 * @param {Map<number, ShoppingIngredientProfile>} profilesById
 * @returns {DayShoppingIngredient[]}
 */
function ingredientsFromMeal(meal, profilesById) {
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

            const displayName = canonicalShoppingIngredientName(name) || name;

            out.push(
                ...shoppingRowsForIngredient(
                    {
                        key: shoppingIngredientMergeKey({
                            name: displayName,
                            unit,
                            ingredientId,
                        }),
                        ingredientId,
                        name: displayName,
                        unit,
                        amount,
                    },
                    profilesById,
                ),
            );
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

        const displayName = canonicalShoppingIngredientName(parsed.name) || parsed.name;

        out.push({
            key: shoppingIngredientMergeKey({
                name: displayName,
                unit: parsed.unit,
                ingredientId: 0,
            }),
            ingredientId: 0,
            name: displayName,
            unit: parsed.unit,
            amount: parsed.amount,
        });
    }

    return out;
}

/**
 * @param {Record<string, unknown[]> | null | undefined} selectedCategories
 * @param {ShoppingIngredientProfile[] | null | undefined} [ingredientProfiles]
 * @returns {DayShoppingIngredient[]}
 */
export function aggregateSelectedDayShoppingIngredients(selectedCategories, ingredientProfiles = []) {
    const profilesById = indexShoppingIngredientProfiles(ingredientProfiles);
    /** @type {Map<string, DayShoppingIngredient>} */
    const byKey = new Map();

    for (const meals of Object.values(selectedCategories ?? {})) {
        if (!Array.isArray(meals)) {
            continue;
        }

        for (const meal of meals) {
            for (const row of ingredientsFromMeal(meal, profilesById)) {
                const key = shoppingIngredientMergeKey(row);
                const prev = byKey.get(key);

                if (prev) {
                    prev.amount += row.amount;

                    if (!(prev.ingredientId > 0) && row.ingredientId > 0) {
                        prev.ingredientId = row.ingredientId;
                    }

                    // Prefer a cleaner / longer canonical label if we started with a sparse name.
                    if (row.name.length > prev.name.length) {
                        prev.name = row.name;
                    }
                } else {
                    byKey.set(key, { ...row, key });
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

    for (const row of [...existing, ...additions]) {
        const displayName = canonicalShoppingIngredientName(row.name) || row.name;
        const key = shoppingIngredientMergeKey({
            name: displayName,
            unit: row.unit,
            ingredientId: row.ingredientId,
        });
        const prev = byKey.get(key);

        if (prev) {
            prev.amount = Math.round((prev.amount + row.amount) * 100) / 100;

            if (!(prev.ingredientId > 0) && row.ingredientId > 0) {
                prev.ingredientId = row.ingredientId;
            }

            if (displayName.length > prev.name.length) {
                prev.name = displayName;
            }
        } else {
            byKey.set(key, {
                ...row,
                key,
                name: displayName,
            });
        }
    }

    const list = [...byKey.values()];
    list.sort((a, b) => a.name.localeCompare(b.name));

    return list;
}
