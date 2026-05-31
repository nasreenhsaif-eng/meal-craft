import {
    IconBeans,
    IconDairy,
    IconEggs,
    IconGluten,
    IconNightshades,
    IconNuts,
    IconOther,
    IconShellfish,
    IconSoy,
    IconSpicy,
} from './FoodFilterIcons.jsx';

/** @typedef {'dairy' | 'gluten' | 'eggs' | 'soy' | 'nightshades' | 'beans' | 'nuts' | 'spicy' | 'shellfish' | 'other'} FoodFilterId */

/**
 * @typedef {{
 *   id: FoodFilterId;
 *   label: string;
 *   Icon: import('react').ComponentType<{ className?: string }>;
 * }} FoodFilterOption
 */

/** Strict 10-item food filter matrix. */
/** @type {FoodFilterOption[]} */
export const FOOD_FILTER_OPTIONS = [
    { id: 'dairy', label: 'Dairy', Icon: IconDairy },
    { id: 'gluten', label: 'Gluten', Icon: IconGluten },
    { id: 'eggs', label: 'Eggs', Icon: IconEggs },
    { id: 'soy', label: 'Soy', Icon: IconSoy },
    { id: 'nightshades', label: 'Nightshades', Icon: IconNightshades },
    { id: 'beans', label: 'Beans', Icon: IconBeans },
    { id: 'nuts', label: 'Nuts', Icon: IconNuts },
    { id: 'spicy', label: 'Spicy', Icon: IconSpicy },
    { id: 'shellfish', label: 'Shellfish', Icon: IconShellfish },
    { id: 'other', label: 'Other', Icon: IconOther },
];

export const FOOD_FILTER_OTHER_ID = 'other';

/** @param {FoodFilterId} id */
export function foodFilterLabel(id) {
    return FOOD_FILTER_OPTIONS.find((option) => option.id === id)?.label ?? id;
}
