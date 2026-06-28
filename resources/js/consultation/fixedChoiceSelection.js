/** @typedef {'sideSalads' | 'desserts' | 'soup'} FixedChoiceCategoryKey */

/** Side salad / dessert / soup — pick exactly 2 total across these categories. */
export const FIXED_CHOICE_CATEGORY_KEYS = Object.freeze(
    /** @type {const} */ (['sideSalads', 'desserts', 'soup']),
);

export const FIXED_CHOICE_REQUIRED_COUNT = 2;

/** Display order for the horizontal toggle bar. */
export const FIXED_CHOICE_TOGGLE_OPTIONS = Object.freeze([
    {
        selectionKey: 'sideSalads',
        label: 'Side Salad',
        deckSuffix: 'sidesalad',
        header: 'Side Salads',
        mealTypeLabel: 'Side salad',
    },
    {
        selectionKey: 'soup',
        label: 'Soup',
        deckSuffix: 'soup',
        header: 'Soups',
        mealTypeLabel: 'Soup',
    },
    {
        selectionKey: 'desserts',
        label: 'Dessert',
        deckSuffix: 'dessert',
        header: 'Desserts',
        mealTypeLabel: 'Dessert',
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
    return countFixedChoiceSelections(categorySelections) === FIXED_CHOICE_REQUIRED_COUNT;
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
    const existing = current[categoryKey] ?? [];
    const isOn = existing.includes(mealId);

    if (isOn) {
        return {
            next: { ...current, [categoryKey]: existing.filter((id) => id !== mealId) },
            blocked: false,
        };
    }

    if (existing.length >= 1) {
        return {
            next: { ...current, [categoryKey]: [mealId] },
            blocked: false,
        };
    }

    const otherTotal = FIXED_CHOICE_CATEGORY_KEYS.filter((key) => key !== categoryKey).reduce(
        (sum, key) => sum + (current[key]?.length ?? 0),
        0,
    );

    if (otherTotal + existing.length >= FIXED_CHOICE_REQUIRED_COUNT) {
        return { next: current, blocked: true };
    }

    return {
        next: { ...current, [categoryKey]: [...existing, mealId] },
        blocked: false,
    };
}
