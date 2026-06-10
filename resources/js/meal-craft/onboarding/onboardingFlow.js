/** @typedef {import('./onboardingConstants.js').OnboardingStepId} OnboardingStepId */
/** @typedef {import('./onboardingConstants.js').OnboardingGender} OnboardingGender */
/** @typedef {import('./onboardingConstants.js').OnboardingDietProtocol} OnboardingDietProtocol */

import { ONBOARDING_FLOW_STEPS } from './onboardingConstants.js';

/**
 * @param {{ gender?: OnboardingGender | ''; dietProtocol?: OnboardingDietProtocol | '' }} context
 */
export function shouldShowPeriodTrackingStep(context = {}) {
    return context.dietProtocol === 'cycle_sync';
}

/**
 * @param {OnboardingStepId} step
 * @param {{ gender?: OnboardingGender | ''; dietProtocol?: OnboardingDietProtocol | '' }} context
 * @returns {OnboardingStepId | null}
 */
export function getNextOnboardingStep(step, context = {}) {
    const index = ONBOARDING_FLOW_STEPS.indexOf(step);

    if (index === -1) {
        return 'gender';
    }

    let next = ONBOARDING_FLOW_STEPS[index + 1] ?? null;

    if (next === 'period_tracking' && !shouldShowPeriodTrackingStep(context)) {
        next = ONBOARDING_FLOW_STEPS[index + 2] ?? null;
    }

    return next;
}

/**
 * @param {OnboardingStepId} step
 * @param {{ gender?: OnboardingGender | ''; dietProtocol?: OnboardingDietProtocol | '' }} context
 * @returns {OnboardingStepId | null}
 */
export function getPreviousOnboardingStep(step, context = {}) {
    const index = ONBOARDING_FLOW_STEPS.indexOf(step);

    if (index <= 0) {
        return null;
    }

    let previous = ONBOARDING_FLOW_STEPS[index - 1] ?? null;

    if (previous === 'period_tracking' && !shouldShowPeriodTrackingStep(context)) {
        previous = ONBOARDING_FLOW_STEPS[index - 2] ?? null;
    }

    return previous;
}

/**
 * @param {OnboardingStepId} step
 * @returns {boolean}
 */
export function isOnboardingFlowComplete(step) {
    return step === 'food_filters';
}
