/**
 * Read global Meal Craft props shared from {@see App\Http\Middleware\HandleInertiaRequests}.
 *
 * @typedef {object} MealCraftShared
 * @property {object} [urls]
 * @property {object} [constants]
 * @property {object} [taxonomy]
 * @property {object} [csv]
 * @property {object} [notices]
 */

/**
 * @param {object} pageProps Inertia `usePage().props`
 * @returns {MealCraftShared}
 */
export function mealCraftFromPage(pageProps) {
    const mc = pageProps?.mealCraft;
    return mc && typeof mc === 'object' ? mc : {};
}

/**
 * @param {object} pageProps
 * @returns {Record<string, string>}
 */
export function mealLibraryUrls(pageProps) {
    const urls = mealCraftFromPage(pageProps).urls?.mealLibrary;
    return urls && typeof urls === 'object' ? urls : {};
}

/**
 * @param {object} pageProps
 * @returns {Record<string, string>}
 */
export function ingredientLibraryUrls(pageProps) {
    const urls = mealCraftFromPage(pageProps).urls?.ingredientLibrary;
    return urls && typeof urls === 'object' ? urls : {};
}

/**
 * @param {object} pageProps
 * @returns {{ value: string; label: string }[]}
 */
export function cyclePhasesFromPage(pageProps) {
    const phases = mealCraftFromPage(pageProps).taxonomy?.cyclePhases;
    return Array.isArray(phases) ? phases : [];
}

/**
 * @param {object} pageProps
 * @returns {{ value: string; label: string }[]}
 */
export function dietTagsFromPage(pageProps) {
    const tags = mealCraftFromPage(pageProps).taxonomy?.dietTags;
    return Array.isArray(tags) ? tags : [];
}

/**
 * @param {string | undefined} primary
 * @param {string | undefined | null} fallback
 * @returns {string}
 */
export function resolveUrl(primary, fallback) {
    if (typeof primary === 'string' && primary.trim() !== '') {
        return primary.trim();
    }
    if (typeof fallback === 'string' && fallback.trim() !== '') {
        return fallback.trim();
    }
    return '';
}
