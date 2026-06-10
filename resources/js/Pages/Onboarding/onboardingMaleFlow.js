/** @typedef {import('../../meal-craft/onboarding/onboardingConstants.js').OnboardingStepId} OnboardingStepId */

import { getNextOnboardingStep, getPreviousOnboardingStep } from '../../meal-craft/onboarding/onboardingFlow.js';

export const MALE_ONBOARDING_START_STEP = /** @type {const} */ ('gender');

/**
 * Linear male onboarding steps (skips welcome + period tracking).
 *
 * @returns {OnboardingStepId[]}
 */
export function listMaleOnboardingSteps() {
    /** @type {OnboardingStepId[]} */
    const steps = [];
    let step = MALE_ONBOARDING_START_STEP;

    while (step) {
        steps.push(step);
        step = getNextOnboardingStep(step, { gender: 'male' });
    }

    return steps;
}

/**
 * @param {OnboardingStepId} step
 * @param {{ gender?: string }} [context]
 * @returns {OnboardingStepId | null}
 */
export function advanceMaleOnboardingStep(step, context = { gender: 'male' }) {
    return getNextOnboardingStep(step, context);
}

/**
 * @param {OnboardingStepId} step
 * @param {{ gender?: string }} [context]
 * @returns {OnboardingStepId | null}
 */
export function retreatMaleOnboardingStep(step, context = { gender: 'male' }) {
    return getPreviousOnboardingStep(step, context);
}
