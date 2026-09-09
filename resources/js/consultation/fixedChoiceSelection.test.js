import { describe, expect, it } from 'vitest';
import {
    applyFixedChoiceToggle,
    isFixedChoiceComplete,
    resolveFixedChoiceSelectedMeals,
} from './fixedChoiceSelection.js';

describe('isFixedChoiceComplete', () => {
    it('allows one or two picks across side salad, dessert, and soup', () => {
        expect(isFixedChoiceComplete({ sideSalads: [], desserts: [], soup: [] })).toBe(false);
        expect(isFixedChoiceComplete({ sideSalads: ['1'], desserts: [], soup: [] })).toBe(true);
        expect(isFixedChoiceComplete({ sideSalads: ['1'], desserts: ['2'], soup: [] })).toBe(true);
        expect(isFixedChoiceComplete({ sideSalads: ['1'], desserts: ['2'], soup: ['3'] })).toBe(false);
    });
});

describe('applyFixedChoiceToggle', () => {
    it('blocks a third side once two categories are selected', () => {
        const current = { sideSalads: ['1'], desserts: ['2'], soup: [] };

        const { blocked } = applyFixedChoiceToggle(current, 'soup', '3');

        expect(blocked).toBe(true);
    });
});

describe('resolveFixedChoiceSelectedMeals', () => {
    it('falls back to the recommended card when the saved id is missing from the deck', () => {
        const recommended = { id: '42', title: 'Kimchi Purslane Side Salad', isRecommended: true };
        const other = { id: '43', title: 'Tahini Purslane Pepper Salad' };

        expect(
            resolveFixedChoiceSelectedMeals(['999'], [recommended, other]).map((meal) => meal.id),
        ).toEqual(['42']);
    });

    it('returns nothing when the category is unchecked', () => {
        expect(resolveFixedChoiceSelectedMeals([], [{ id: '42' }])).toEqual([]);
    });
});
