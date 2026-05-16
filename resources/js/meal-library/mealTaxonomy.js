/** Admin Meal Library — aligned with `App\Support\MealLibraryTaxonomy`. */

export const MEAL_PLAN_TAG_OPTIONS = ['Balanced', 'Hormone Feast', 'Ketogenic', 'Sickle Cell Anemia'];

export const DIETARY_TAG_OPTIONS = ['Vegan', 'Vegetarian', 'Dairy-free', 'Gluten-free', 'Nut-free', 'Spicy'];

/** @param {unknown} raw */
export function resolveDietaryTagCanonical(raw) {
    const label = String(raw ?? '').trim();
    if (label === '') {
        return null;
    }
    return DIETARY_TAG_OPTIONS.find((canonical) => canonical.toLowerCase() === label.toLowerCase()) ?? null;
}

/** @param {unknown[]} tags */
export function canonicalDietTagsFromList(tags) {
    const out = [];
    for (const tag of tags) {
        const canonical = resolveDietaryTagCanonical(tag);
        if (canonical && !out.includes(canonical)) {
            out.push(canonical);
        }
    }
    return out;
}
