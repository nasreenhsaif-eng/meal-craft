import { describe, expect, it } from 'vitest';
import {
    isBelowMicronutrientEnforcedCalories,
    MICRONUTRIENT_ENFORCED_MIN_CALORIES,
} from './caloricThresholdNotice.ts';

describe('isBelowMicronutrientEnforcedCalories', () => {
    it('flags tiers below 1500 kcal', () => {
        expect(isBelowMicronutrientEnforcedCalories(1000)).toBe(true);
        expect(isBelowMicronutrientEnforcedCalories(1200)).toBe(true);
    });

    it('does not flag enforced tiers', () => {
        expect(isBelowMicronutrientEnforcedCalories(MICRONUTRIENT_ENFORCED_MIN_CALORIES)).toBe(false);
        expect(isBelowMicronutrientEnforcedCalories(1800)).toBe(false);
        expect(isBelowMicronutrientEnforcedCalories(2000)).toBe(false);
    });

    it('ignores unset tier', () => {
        expect(isBelowMicronutrientEnforcedCalories(0)).toBe(false);
    });
});
