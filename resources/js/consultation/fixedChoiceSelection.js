/** @typedef {'sideSalads' | 'desserts' | 'soup'} FixedChoiceCategoryKey */

/** Side salad / dessert / soup — pick 1–2 total across these categories. */
export const FIXED_CHOICE_CATEGORY_KEYS = Object.freeze(
    /** @type {const} */ (['sideSalads', 'desserts', 'soup']),
);

export const FIXED_CHOICE_MAX_COUNT = 2;

/** @deprecated Use {@link FIXED_CHOICE_MAX_COUNT} */
export const FIXED_CHOICE_REQUIRED_COUNT = FIXED_CHOICE_MAX_COUNT;

export const FIXED_CHOICE_MIN_COUNT = 1;

/** Display order for the horizontal toggle bar — soup is always last. */
export const FIXED_CHOICE_TOGGLE_OPTIONS = Object.freeze([
    {
        selectionKey: 'sideSalads',
        label: 'Side Salad',
        deckSuffix: 'sidesalad',
        header: 'Side Salads',
        mealTypeLabel: 'Side salad',
    },
    {
        selectionKey: 'desserts',
        label: 'Dessert',
        deckSuffix: 'dessert',
        header: 'Desserts',
        mealTypeLabel: 'Dessert',
    },
    {
        selectionKey: 'soup',
        label: 'Soup',
        deckSuffix: 'soup',
        header: 'Soups',
        mealTypeLabel: 'Soup',
    },
]);

/** @deprecated Use {@link FIXED_CHOICE_TOGGLE_OPTIONS} */
export const FIXED_CHOICE_SECTIONS = FIXED_CHOICE_TOGGLE_OPTIONS;

/**
 * @param {Partial<Record<FixedChoiceCategoryKey, string[]>> | null | undefined} categorySelections
 */
export function countFixedChoiceSelections(categorySelections) {
    return FIXED_CHOICE_CATEGORY_KEYS.reduce(
        (sum, key) => sum + (categorySelections?.[key]?.length ?? 0),
        0,
    );
}

/**
 * @param {Partial<Record<FixedChoiceCategoryKey, string[]>> | null | undefined} categorySelections
 */
export function isFixedChoiceComplete(categorySelections) {
    const count = countFixedChoiceSelections(categorySelections);

    return count >= FIXED_CHOICE_MIN_COUNT && count <= FIXED_CHOICE_MAX_COUNT;
}

/**
 * @param {Partial<Record<FixedChoiceCategoryKey, string[]>> | null | undefined} categorySelections
 * @returns {FixedChoiceCategoryKey[]}
 */
export function visibleFixedChoiceCategoriesFromSelections(categorySelections) {
    /** @type {FixedChoiceCategoryKey[]} */
    const visible = [];

    for (const option of FIXED_CHOICE_TOGGLE_OPTIONS) {
        if ((categorySelections?.[option.selectionKey]?.length ?? 0) > 0) {
            visible.push(option.selectionKey);
        }
    }

    return visible;
}

/**
 * @param {Partial<Record<FixedChoiceCategoryKey, string[]>>} current
 * @param {FixedChoiceCategoryKey} categoryKey
 * @param {string} mealId
 * @returns {{ next: Partial<Record<FixedChoiceCategoryKey, string[]>>; blocked: boolean }}
 */
export function applyFixedChoiceToggle(current, categoryKey, mealId) {
    const normalizedId = String(mealId);
    const existing = (current[categoryKey] ?? []).map((id) => String(id));
    const isOn = existing.includes(normalizedId);

    if (isOn) {
        return {
            next: { ...current, [categoryKey]: existing.filter((id) => id !== normalizedId) },
            blocked: false,
        };
    }

    if (existing.length >= 1) {
        return {
            next: { ...current, [categoryKey]: [normalizedId] },
            blocked: false,
        };
    }

    const otherTotal = FIXED_CHOICE_CATEGORY_KEYS.filter((key) => key !== categoryKey).reduce(
        (sum, key) => sum + (current[key]?.length ?? 0),
        0,
    );

    if (otherTotal + existing.length >= FIXED_CHOICE_MAX_COUNT) {
        return { next: current, blocked: true };
    }

    return {
        next: { ...current, [categoryKey]: [...existing, normalizedId] },
        blocked: false,
    };
}

/**
 * Meals to show under a checked side category. Falls back to the recommended/first card
 * when saved ids no longer match the on-screen deck.
 *
 * @param {Array<string|number>} selectedIds
 * @param {object[]} cards
 * @param {Partial<Record<string, object[]>>} [allDecks]
 * @returns {object[]}
 */
export function resolveFixedChoiceSelectedMeals(selectedIds, cards = [], allDecks = {}) {
    const normalizedIds = (selectedIds ?? []).map((id) => String(id)).filter((id) => id !== '');
    /** @type {object[]} */
    const resolved = [];
    const seen = new Set();

    for (const id of normalizedIds) {
        const fromCategory = (cards ?? []).find((meal) => String(meal?.id ?? '') === id);
        let found = fromCategory ?? null;

        if (!found) {
            for (const deck of Object.values(allDecks ?? {})) {
                found = (deck ?? []).find((meal) => String(meal?.id ?? '') === id) ?? null;
                if (found) {
                    break;
                }
            }
        }

        if (!found || seen.has(String(found.id))) {
            continue;
        }

        seen.add(String(found.id));
        resolved.push(found);
    }

    if (resolved.length > 0) {
        return resolved;
    }

    if (normalizedIds.length === 0) {
        return [];
    }

    const fallback = (cards ?? []).find((meal) => meal?.isRecommended || meal?.is_recommended) ?? (cards ?? [])[0];

    return fallback ? [fallback] : [];
}
