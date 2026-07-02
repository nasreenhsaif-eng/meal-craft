import { describe, expect, it } from 'vitest';
import {
    applyFixedChoiceToggle,
    isFixedChoiceComplete,
} from './fixedChoiceSelection.js';

describe('isFixedChoiceComplete', () => {
    it('requires exactly two picks across side salad, dessert, and soup', () => {
        expect(isFixedChoiceComplete({ sideSalads: ['1'], desserts: [], soup: [] })).toBe(false);
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
