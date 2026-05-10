/** Keys stored on each user / kitchen row (one column per slot). */
export const MEAL_PLAN_KEYS = ['breakfast', 'm1', 'm2', 'soup', 'sideSalad', 'dessert'];

/** Display headers for tables and CSV (must stay in sync for Drive sorting). */
export const MEAL_PLAN_HEADERS = [
    'Breakfast',
    'M1 (Meal 1)',
    'M2 (Meal 2)',
    'Soup',
    'Side Salad',
    'Dessert',
];

/**
 * @param {string | null | undefined} value
 * @returns {string}
 */
export function displayMealCell(value) {
    const s = String(value ?? '').trim();
    return s.length > 0 ? s : '—';
}
