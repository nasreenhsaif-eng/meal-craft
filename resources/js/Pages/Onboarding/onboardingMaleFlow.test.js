import { describe, expect, it } from 'vitest';
import { listMaleOnboardingSteps, MALE_ONBOARDING_START_STEP } from './onboardingMaleFlow.js';

describe('onboardingMaleFlow', () => {
    it('lists the male linear sequence without welcome or period tracking', () => {
        expect(listMaleOnboardingSteps()).toEqual([
            'gender',
            'diet_protocol',
            'birthday',
            'height',
            'weight',
            'target_weight',
            'activity',
            'daily_targets',
            'food_filters',
        ]);
        expect(MALE_ONBOARDING_START_STEP).toBe('gender');
    });
});
