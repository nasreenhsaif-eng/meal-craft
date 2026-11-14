import { describe, expect, it } from 'vitest';
import {
    CALORIC_THRESHOLD_NOTICE_MAX_CALORIES,
    isBelowMicronutrientEnforcedCalories,
} from './caloricThresholdNotice.ts';

describe('isBelowMicronutrientEnforcedCalories', () => {
    it('flags tiers at or below 1200 kcal', () => {
        expect(isBelowMicronutrientEnforcedCalories(800)).toBe(true);
        expect(isBelowMicronutrientEnforcedCalories(1000)).toBe(true);
        expect(isBelowMicronutrientEnforcedCalories(1200)).toBe(true);
        expect(CALORIC_THRESHOLD_NOTICE_MAX_CALORIES).toBe(1200);
    });

    it('does not flag tiers above 1200 kcal', () => {
        expect(isBelowMicronutrientEnforcedCalories(1201)).toBe(false);
        expect(isBelowMicronutrientEnforcedCalories(1250)).toBe(false);
        expect(isBelowMicronutrientEnforcedCalories(1500)).toBe(false);
        expect(isBelowMicronutrientEnforcedCalories(1800)).toBe(false);
    });

    it('ignores unset tier', () => {
        expect(isBelowMicronutrientEnforcedCalories(0)).toBe(false);
    });
});
