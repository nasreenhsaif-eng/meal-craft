import { describe, expect, it } from 'vitest';
import {
    filterMealsForCombobox,
    SCHEDULER_BREAKFAST_CATEGORIES,
    SCHEDULER_MEAL_CHOICE_CATEGORIES,
} from './mealSearch.ts';

const meals = [
    { id: 1, name: 'Berry oats bowl', category: 'Breakfast' },
    { id: 2, name: 'Grilled salmon plate', category: 'Meal' },
    { id: 3, name: 'Tomato basil soup', category: 'Soup' },
    { id: 4, name: 'Garden side salad', category: 'Side Salad' },
    { id: 5, name: 'Dark chocolate mousse', category: 'Dessert' },
];

describe('filterMealsForCombobox', () => {
    it('filters breakfast slots to breakfast category only', () => {
        const results = filterMealsForCombobox(meals, 'berry', SCHEDULER_BREAKFAST_CATEGORIES);
        expect(results.map((m) => m.id)).toEqual([1]);
    });

    it('filters meal choice slots across meal, salad, dessert, and soup categories', () => {
        const results = filterMealsForCombobox(meals, 'a', SCHEDULER_MEAL_CHOICE_CATEGORIES);
        expect(results.map((m) => m.id).sort()).toEqual([2, 3, 4, 5].sort());
    });

    it('returns no matches for an empty query', () => {
        expect(filterMealsForCombobox(meals, '', SCHEDULER_MEAL_CHOICE_CATEGORIES)).toEqual([]);
    });
});
