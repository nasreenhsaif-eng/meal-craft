/** @typedef {import('./onboardingConstants.js').OnboardingStepId} OnboardingStepId */
/** @typedef {import('./onboardingConstants.js').OnboardingGender} OnboardingGender */
/** @typedef {import('./onboardingConstants.js').OnboardingDietProtocol} OnboardingDietProtocol */

import { CYCLE_SYNC_FLOW_STEPS, ONBOARDING_FLOW_STEPS } from './onboardingConstants.js';

/**
 * @param {{ gender?: OnboardingGender | ''; dietProtocol?: OnboardingDietProtocol | '' }} context
 */
export function shouldShowPeriodTrackingStep(context = {}) {
    return context.dietProtocol === 'cycle_sync';
}

/**
 * @param {OnboardingStepId | string} step
 */
export function isCycleSyncFlowStep(step) {
    return CYCLE_SYNC_FLOW_STEPS.includes(/** @type {import('./onboardingConstants.js').OnboardingStepId} */ (step));
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

    let cursor = index + 1;

    while (cursor < ONBOARDING_FLOW_STEPS.length) {
        const next = ONBOARDING_FLOW_STEPS[cursor];

        if (!isCycleSyncFlowStep(next) || shouldShowPeriodTrackingStep(context)) {
            return next;
        }

        cursor += 1;
    }

    return null;
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

    let cursor = index - 1;

    while (cursor >= 0) {
        const previous = ONBOARDING_FLOW_STEPS[cursor];

        if (!isCycleSyncFlowStep(previous) || shouldShowPeriodTrackingStep(context)) {
            return previous;
        }

        cursor -= 1;
    }

    return null;
}

/**
 * @param {OnboardingStepId} step
 * @returns {boolean}
 */
export function isOnboardingFlowComplete(step) {
    return step === 'food_filters';
}
