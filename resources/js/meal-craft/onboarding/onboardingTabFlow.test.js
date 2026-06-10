import { describe, expect, it } from 'vitest';
import {
    getNextTabStep,
    getPreviousTabStep,
    getVisibleOnboardingSteps,
} from './onboardingTabFlow.js';
import { ONBOARDING_STEPS } from '../../Pages/Onboarding/onboardingSteps.js';

describe('onboardingTabFlow', () => {
    const maleVisibleSteps = getVisibleOnboardingSteps(ONBOARDING_STEPS, {
        gender: 'male',
        dietProtocol: 'balanced',
    });
    const femaleBalancedSteps = getVisibleOnboardingSteps(ONBOARDING_STEPS, {
        gender: 'female',
        dietProtocol: 'balanced',
    });
    const cycleSyncVisibleSteps = getVisibleOnboardingSteps(ONBOARDING_STEPS, {
        gender: 'female',
        dietProtocol: 'cycle_sync',
    });

    it('walks male customers back through the reordered flow after diet protocol', () => {
        expect(getPreviousTabStep('birthday', maleVisibleSteps)).toBe('diet_protocol');
        expect(getPreviousTabStep('diet_protocol', maleVisibleSteps)).toBe('gender');
        expect(getPreviousTabStep('gender', maleVisibleSteps)).toBeNull();
    });

    it('walks cycle sync customers back through period tracking after diet protocol', () => {
        expect(getPreviousTabStep('birthday', cycleSyncVisibleSteps)).toBe('period_tracking');
        expect(getPreviousTabStep('period_tracking', cycleSyncVisibleSteps)).toBe('diet_protocol');
        expect(getPreviousTabStep('diet_protocol', cycleSyncVisibleSteps)).toBe('gender');
    });

    it('skips period tracking for female customers without cycle sync', () => {
        expect(femaleBalancedSteps.some((step) => step.value === 'period_tracking')).toBe(false);
        expect(getPreviousTabStep('birthday', femaleBalancedSteps)).toBe('diet_protocol');
        expect(getNextTabStep('diet_protocol', femaleBalancedSteps)).toBe('birthday');
    });

    it('advances cycle sync customers forward from diet protocol to period tracking', () => {
        expect(getNextTabStep('diet_protocol', cycleSyncVisibleSteps)).toBe('period_tracking');
    });

    it('does not jump from diet protocol back to activity in the male flow', () => {
        expect(getPreviousTabStep('diet_protocol', maleVisibleSteps)).not.toBe('activity');
    });
});
