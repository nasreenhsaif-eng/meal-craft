import { describe, expect, it } from 'vitest';
import { getNextOnboardingStep, getPreviousOnboardingStep, shouldShowPeriodTrackingStep } from './onboardingFlow.js';

describe('onboardingFlow', () => {
    it('skips period tracking for male customers', () => {
        expect(shouldShowPeriodTrackingStep('male')).toBe(false);
        expect(getNextOnboardingStep('gender', { gender: 'male' })).toBe('birthday');
    });

    it('includes period tracking for female customers', () => {
        expect(shouldShowPeriodTrackingStep('female')).toBe(true);
        expect(getNextOnboardingStep('gender', { gender: 'female' })).toBe('period_tracking');
    });

    it('walks the full linear sequence after period tracking', () => {
        expect(getNextOnboardingStep('period_tracking', { gender: 'female' })).toBe('birthday');
        expect(getNextOnboardingStep('activity', { gender: 'female' })).toBe('diet_protocol');
        expect(getNextOnboardingStep('diet_protocol', { gender: 'female' })).toBe('daily_targets');
        expect(getNextOnboardingStep('daily_targets', { gender: 'female' })).toBe('food_filters');
    });

    it('skips period tracking when navigating backwards for male customers', () => {
        expect(getPreviousOnboardingStep('birthday', { gender: 'male' })).toBe('gender');
    });
});
