import { describe, expect, it } from 'vitest';
import { listMaleOnboardingSteps, MALE_ONBOARDING_START_STEP } from './onboardingMaleFlow.js';

describe('onboardingMaleFlow', () => {
    it('lists the male linear sequence without welcome or period tracking', () => {
        expect(listMaleOnboardingSteps()).toEqual([
            'gender',
            'birthday',
            'height',
            'weight',
            'target_weight',
            'activity',
            'diet_protocol',
            'daily_targets',
            'food_filters',
        ]);
        expect(MALE_ONBOARDING_START_STEP).toBe('gender');
    });
});
