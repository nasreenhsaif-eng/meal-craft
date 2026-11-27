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

    it('keeps back navigation aligned to visible steps', () => {
        expect(getPreviousTabStep('daily_targets', cycleSyncVisibleSteps)).toBe('active_phase');
        expect(getPreviousTabStep('active_phase', cycleSyncVisibleSteps)).toBe('protocol_selection');
        expect(getPreviousTabStep('protocol_selection', cycleSyncVisibleSteps)).toBe('period_tracking');
        expect(getPreviousTabStep('period_tracking', cycleSyncVisibleSteps)).toBe('diet_protocol');
    });

    it('hides cycle sync steps for non cycle protocols', () => {
        expect(femaleBalancedSteps.some((step) => step.value === 'period_tracking')).toBe(false);
        expect(femaleBalancedSteps.some((step) => step.value === 'protocol_selection')).toBe(false);
        expect(femaleBalancedSteps.some((step) => step.value === 'active_phase')).toBe(false);
        expect(maleVisibleSteps.some((step) => step.value === 'period_tracking')).toBe(false);
    });

    it('advances through the three cycle sync screens', () => {
        expect(getNextTabStep('diet_protocol', cycleSyncVisibleSteps)).toBe('period_tracking');
        expect(getNextTabStep('period_tracking', cycleSyncVisibleSteps)).toBe('protocol_selection');
        expect(getNextTabStep('protocol_selection', cycleSyncVisibleSteps)).toBe('active_phase');
        expect(getNextTabStep('active_phase', cycleSyncVisibleSteps)).toBe('daily_targets');
    });
});
