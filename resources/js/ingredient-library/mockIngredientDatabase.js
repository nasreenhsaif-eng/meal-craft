/**
 * Shared mock ingredient library used by Storybook pages.
 * Values are per 100g.
 */

export const INGREDIENT_LIBRARY_ROWS = [
    {
        id: 'ing-1',
        name: 'Chicken breast, roasted',
        category: 'Proteins',
        fdc: '173686',
        highlights: ['B12', 'Zinc'],
        calories: 165,
        protein: 31,
        carbs: 0,
        fat: 3.6,
        b6: 0.6,
        b9_folate: 10,
        b12: 0.3,
        iron: 1,
        magnesium: 29,
        micronutrients: { zinc: 1.0 },
    },
    {
        id: 'ing-2',
        name: 'Spinach, raw',
        category: 'Vegetables',
        fdc: '168462',
        highlights: ['Folate', 'Iron', 'Magnesium'],
        calories: 23,
        protein: 2.9,
        carbs: 3.6,
        fat: 0.4,
        b6: 0.19,
        b9_folate: 194,
        b12: 0,
        iron: 2.7,
        magnesium: 79,
        micronutrients: { zinc: 0.5 },
    },
    {
        id: 'ing-3',
        name: 'Greek yogurt, plain',
        category: 'Other',
        fdc: '170885',
        highlights: ['B12', 'Calcium'],
        calories: 97,
        protein: 9,
        carbs: 3.6,
        fat: 5,
        b6: 0.05,
        b9_folate: 10,
        b12: 0.7,
        iron: 0,
        magnesium: 11,
        micronutrients: { calcium: 110, zinc: 0.6 },
    },
];

/** Minimal shape for the CSV calculator. */
export const INGREDIENT_DATABASE = INGREDIENT_LIBRARY_ROWS.map((r) => ({
    name: r.name,
    calories: r.calories,
    protein: r.protein,
    carbs: r.carbs,
    fat: r.fat,
    b6: r.b6,
    b9_folate: r.b9_folate,
    b12: r.b12,
    iron: r.iron,
    magnesium: r.magnesium,
    micronutrients: r.micronutrients,
}));

