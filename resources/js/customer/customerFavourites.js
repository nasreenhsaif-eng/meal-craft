/**
 * Saved recipes for Cook It Yourself — browser-local, keyed by auth user id.
 */

/**
 * @typedef {{
 *   calories?: string | number;
 *   protein?: string | number;
 *   carbs?: string | number;
 *   fat?: string | number;
 * }} FavouriteMacros
 */

/**
 * @typedef {{
 *   id: string;
 *   title: string;
 *   imageUrl?: string;
 *   category?: string;
 *   macros?: FavouriteMacros;
 *   safetyAlerts?: object[];
 *   sickleCellHighlights?: string[];
 *   hasG6pdTrigger?: boolean;
 *   detailView?: object;
 * }} FavouriteRecipe
 */

/**
 * @param {number | string | null | undefined} userId
 * @returns {string}
 */
export function customerFavouritesStorageKey(userId) {
    return `mc-customer-favourites-${userId ?? 0}`;
}

/**
 * @param {unknown} value
 * @returns {FavouriteMacros | undefined}
 */
function normalizeMacros(value) {
    if (!value || typeof value !== 'object') {
        return undefined;
    }

    const macros = /** @type {FavouriteMacros} */ (value);

    if (
        macros.calories == null &&
        macros.protein == null &&
        macros.carbs == null &&
        macros.fat == null
    ) {
        return undefined;
    }

    return {
        calories: macros.calories,
        protein: macros.protein,
        carbs: macros.carbs,
        fat: macros.fat,
    };
}

/**
 * @param {unknown} value
 * @returns {object[] | undefined}
 */
function normalizeAlerts(value) {
    if (!Array.isArray(value)) {
        return undefined;
    }

    return value.filter((alert) => alert && typeof alert === 'object');
}

/**
 * @param {unknown} value
 * @returns {string[] | undefined}
 */
function normalizeHighlights(value) {
    if (!Array.isArray(value)) {
        return undefined;
    }

    return value.filter((badge) => typeof badge === 'string' && badge !== '');
}

/**
 * @param {unknown} row
 * @returns {FavouriteRecipe | null}
 */
function normalizeFavourite(row) {
    if (!row || typeof row !== 'object') {
        return null;
    }

    const record = /** @type {Record<string, unknown>} */ (row);
    const id = String(record.id ?? '');
    const title = String(record.title ?? '').trim();

    if (id === '' || title === '') {
        return null;
    }

    return {
        id,
        title,
        imageUrl: typeof record.imageUrl === 'string' ? record.imageUrl : undefined,
        category: typeof record.category === 'string' ? record.category : undefined,
        macros: normalizeMacros(record.macros),
        safetyAlerts: normalizeAlerts(record.safetyAlerts),
        sickleCellHighlights: normalizeHighlights(record.sickleCellHighlights),
        hasG6pdTrigger: Boolean(record.hasG6pdTrigger),
        detailView:
            record.detailView && typeof record.detailView === 'object'
                ? /** @type {object} */ (record.detailView)
                : undefined,
    };
}

/**
 * Snapshot stored when a Cook It Yourself meal is saved.
 *
 * @param {object} meal
 * @param {string} [imageUrl]
 * @returns {FavouriteRecipe}
 */
export function favouriteRecipeFromMeal(meal, imageUrl) {
    return {
        id: String(meal?.id ?? ''),
        title: String(meal?.title ?? 'Meal').trim(),
        imageUrl: typeof imageUrl === 'string' && imageUrl !== '' ? imageUrl : undefined,
        category: typeof meal?.category === 'string' ? meal.category : undefined,
        macros: normalizeMacros(meal?.macros),
        safetyAlerts: normalizeAlerts(meal?.safetyAlerts) ?? [],
        sickleCellHighlights: normalizeHighlights(meal?.sickleCellHighlights) ?? [],
        hasG6pdTrigger: Boolean(meal?.hasG6pdTrigger),
        detailView:
            meal?.detailView && typeof meal.detailView === 'object' ? meal.detailView : undefined,
    };
}

/**
 * Meal shape the saved-recipe card and detail panel read.
 *
 * @param {FavouriteRecipe} recipe
 */
export function mealFromFavouriteRecipe(recipe) {
    return {
        id: recipe.id,
        title: recipe.title,
        imageUrl: recipe.imageUrl,
        category: recipe.category,
        macros: recipe.macros,
        safetyAlerts: recipe.safetyAlerts ?? [],
        sickleCellHighlights: recipe.sickleCellHighlights ?? [],
        hasG6pdTrigger: Boolean(recipe.hasG6pdTrigger),
        detailView: recipe.detailView,
    };
}

/**
 * @param {number | string | null | undefined} userId
 * @returns {FavouriteRecipe[]}
 */
export function readCustomerFavourites(userId) {
    if (typeof window === 'undefined' || userId == null || userId === '') {
        return [];
    }

    try {
        const raw = window.localStorage.getItem(customerFavouritesStorageKey(userId));
        const parsed = raw ? JSON.parse(raw) : [];

        if (!Array.isArray(parsed)) {
            return [];
        }

        return parsed.map((row) => normalizeFavourite(row)).filter((row) => row !== null);
    } catch {
        return [];
    }
}

/**
 * @param {number | string | null | undefined} userId
 * @param {FavouriteRecipe[]} items
 */
export function writeCustomerFavourites(userId, items) {
    if (typeof window === 'undefined' || userId == null || userId === '') {
        return;
    }

    try {
        window.localStorage.setItem(customerFavouritesStorageKey(userId), JSON.stringify(items));
    } catch {
        // ignore storage errors
    }
}

/**
 * @param {FavouriteRecipe[]} existing
 * @param {FavouriteRecipe} recipe
 * @returns {FavouriteRecipe[]}
 */
export function addFavouriteRecipe(existing, recipe) {
    const normalized = normalizeFavourite(recipe);

    if (normalized === null) {
        return existing;
    }

    if (existing.some((row) => row.id === normalized.id)) {
        return existing;
    }

    return [normalized, ...existing];
}

/**
 * @param {FavouriteRecipe[]} existing
 * @param {string} recipeId
 * @returns {FavouriteRecipe[]}
 */
export function removeFavouriteRecipe(existing, recipeId) {
    const id = String(recipeId ?? '');

    return existing.filter((row) => row.id !== id);
}

/**
 * @param {FavouriteRecipe[]} favourites
 * @param {string} recipeId
 */
export function isFavouriteRecipe(favourites, recipeId) {
    const id = String(recipeId ?? '');

    return favourites.some((row) => row.id === id);
}
