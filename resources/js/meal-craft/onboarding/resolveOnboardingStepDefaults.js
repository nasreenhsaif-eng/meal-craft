/** @typedef {import('./onboardingConstants.js').OnboardingStepId} OnboardingStepId */
/** @typedef {import('./onboardingState.js').OnboardingWizardState} OnboardingWizardState */

import { defaultHeightCm } from '../../Components/Molecules/Onboarding/heightUtils.js';
import { defaultWeightKg, resolveTargetWeightKg } from '../../Components/Molecules/Onboarding/weightUtils.js';
import {
    defaultBirthdayValue,
    parseIsoDate,
    toIsoDate,
} from '../../Components/Molecules/Onboarding/wheelDateUtils.js';

/**
 * Wheel pickers show a default value even when wizard state is still empty.
 * Next must submit that visible value instead of failing validation.
 *
 * @param {OnboardingStepId | string} step
 * @param {OnboardingWizardState} state
 * @returns {OnboardingWizardState}
 */
export function resolveOnboardingStepDefaults(step, state) {
    switch (step) {
        case 'birthday':
            if (parseIsoDate(state.birthdate)) {
                return state;
            }

            return { ...state, birthdate: toIsoDate(defaultBirthdayValue()) };
        case 'height':
            if (state.height != null && state.height > 0) {
                return state;
            }

            return { ...state, height: defaultHeightCm() };
        case 'weight':
            if (state.weight != null && state.weight > 0) {
                return state;
            }

            return { ...state, weight: defaultWeightKg() };
        case 'target_weight':
            if (state.targetWeight != null && state.targetWeight > 0) {
                return state;
            }

            return {
                ...state,
                targetWeight: resolveTargetWeightKg(state.targetWeight, state.weight),
            };
        default:
            return state;
    }
}
