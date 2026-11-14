import { describe, expect, it } from 'vitest';
import { herbFlavorMultiplier, mealScalingRoleFromName } from './macroFirstMainMealScale.ts';

describe('mealScalingRoleFromName', () => {
    it('classifies protein carb herb vegetable fat and sauce ingredients', () => {
        expect(mealScalingRoleFromName('Chicken Breast')).toBe('protein');
        expect(mealScalingRoleFromName('Turmeric Rice (Base)')).toBe('carb');
        expect(mealScalingRoleFromName('Fresh Rosemary')).toBe('herb_spice');
        expect(mealScalingRoleFromName('Zucchini')).toBe('vegetable');
        expect(mealScalingRoleFromName('Olive Oil (Extra Virgin)')).toBe('fat');
        expect(mealScalingRoleFromName('Harissa Paste (Base)')).toBe('sauce');
    });
});

describe('herbFlavorMultiplier', () => {
    it('blends protein and carb multipliers within clamp bounds', () => {
        expect(herbFlavorMultiplier(1.44, 0.8)).toBeCloseTo(1.073, 2);
        expect(herbFlavorMultiplier(4, 4)).toBe(2);
        expect(herbFlavorMultiplier(0.1, 0.1)).toBe(0.5);
    });
});
