/**
 * Saved recipes for Cook It Yourself — browser-local, keyed by auth user id.
 */

/**
 * @typedef {{
 *   id: string;
 *   title: string;
 *   imageUrl?: string;
 *   category?: string;
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

        return parsed
            .map((row) => ({
                id: String(row?.id ?? ''),
                title: String(row?.title ?? '').trim(),
                imageUrl: typeof row?.imageUrl === 'string' ? row.imageUrl : undefined,
                category: typeof row?.category === 'string' ? row.category : undefined,
            }))
            .filter((row) => row.id !== '' && row.title !== '');
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
    const id = String(recipe?.id ?? '');

    if (id === '' || String(recipe?.title ?? '').trim() === '') {
        return existing;
    }

    if (existing.some((row) => row.id === id)) {
        return existing;
    }

    return [
        {
            id,
            title: String(recipe.title).trim(),
            imageUrl: typeof recipe.imageUrl === 'string' ? recipe.imageUrl : undefined,
            category: typeof recipe.category === 'string' ? recipe.category : undefined,
        },
        ...existing,
    ];
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
