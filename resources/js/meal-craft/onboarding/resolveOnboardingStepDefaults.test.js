import { describe, expect, it } from 'vitest';
import { defaultHeightCm } from '../../Components/Molecules/Onboarding/heightUtils.js';
import { defaultWeightKg } from '../../Components/Molecules/Onboarding/weightUtils.js';
import { defaultBirthdayValue, toIsoDate } from '../../Components/Molecules/Onboarding/wheelDateUtils.js';
import { createInitialOnboardingState } from './onboardingState.js';
import { resolveOnboardingStepDefaults } from './resolveOnboardingStepDefaults.js';
import { validateOnboardingStep } from './validateOnboardingStep.js';

describe('resolveOnboardingStepDefaults', () => {
    it('fills the visible birthday default so next can submit without spinning the wheel', () => {
        const state = createInitialOnboardingState();
        const resolved = resolveOnboardingStepDefaults('birthday', state);

        expect(state.birthdate).toBe('');
        expect(resolved.birthdate).toBe(toIsoDate(defaultBirthdayValue()));
        expect(validateOnboardingStep('birthday', state).valid).toBe(false);
        expect(validateOnboardingStep('birthday', resolved).valid).toBe(true);
    });

    it('keeps an explicit birthday the customer already chose', () => {
        const state = { ...createInitialOnboardingState(), birthdate: '1990-06-15' };

        expect(resolveOnboardingStepDefaults('birthday', state)).toBe(state);
    });

    it('fills visible height and weight defaults used by later wheel steps', () => {
        const state = createInitialOnboardingState();

        expect(resolveOnboardingStepDefaults('height', state).height).toBe(defaultHeightCm());
        expect(resolveOnboardingStepDefaults('weight', state).weight).toBe(defaultWeightKg());
        expect(resolveOnboardingStepDefaults('target_weight', state).targetWeight).toBe(defaultWeightKg());
    });
});
