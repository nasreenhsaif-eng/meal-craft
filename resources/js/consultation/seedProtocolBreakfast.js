/**
 * @param {unknown} id
 */
function mealId(id) {
    if (id === null || id === undefined) {
        return '';
    }

    return String(id);
}

/**
 * Breakfast id to write for a weekday, or null to leave the slot unchanged.
 *
 * The adapted-menu API loads one weekday at a time. Seeding from the catalog
 * while Monday is still missing plants a classic-library id. Nutrient Density
 * then refuses to overwrite that pick, so the admin default never appears.
 *
 * @param {object} args
 * @param {Array<{ id?: unknown, isRecommended?: boolean }> | null | undefined} [args.assignedBreakfasts]
 * @param {unknown} [args.currentBreakfastId]
 * @param {boolean} [args.protectExisting] Keep a current pick that is still on today's deck (customer swap).
 * @returns {string | null}
 */
export function resolveProtocolBreakfastSeedId({
    assignedBreakfasts = null,
    currentBreakfastId = '',
    protectExisting = false,
} = {}) {
    const assigned = Array.isArray(assignedBreakfasts) ? assignedBreakfasts : [];
    const assignedIds = assigned.map((meal) => mealId(meal?.id)).filter((id) => id !== '');

    if (assignedIds.length === 0) {
        return null;
    }

    const current = mealId(currentBreakfastId);
    const recommended = assigned.find((meal) => meal?.isRecommended) ?? assigned[0];
    const recommendedId = mealId(recommended?.id);

    if (recommendedId === '') {
        return null;
    }

    if (protectExisting && current !== '' && assignedIds.includes(current)) {
        return current;
    }

    return recommendedId;
}
