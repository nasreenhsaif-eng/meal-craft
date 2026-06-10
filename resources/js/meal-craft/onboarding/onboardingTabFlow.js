/** @typedef {import('./onboardingConstants.js').OnboardingStepId} OnboardingStepId */

import { shouldShowPeriodTrackingStep } from './onboardingFlow.js';

/**
 * @param {Array<{ value: string; label: string }>} steps
 * @param {{ gender?: string; dietProtocol?: string }} context
 * @returns {Array<{ value: string; label: string }>}
 */
export function getVisibleOnboardingSteps(steps, context = {}) {
    if (!shouldShowPeriodTrackingStep(context)) {
        return steps.filter((step) => step.value !== 'period_tracking');
    }

    return steps;
}

/**
 * @param {OnboardingStepId | string} step
 * @param {Array<{ value: string }>} visibleSteps
 * @returns {number}
 */
export function getOnboardingStepIndex(step, visibleSteps) {
    return visibleSteps.findIndex((item) => item.value === step);
}

/**
 * Navigate using the visible step list so back/next always match the header counter.
 *
 * @param {OnboardingStepId | string} step
 * @param {Array<{ value: string }>} visibleSteps
 * @returns {OnboardingStepId | null}
 */
export function getNextTabStep(step, visibleSteps) {
    const currentIndex = getOnboardingStepIndex(step, visibleSteps);

    if (currentIndex === -1 || currentIndex >= visibleSteps.length - 1) {
        return null;
    }

    const next = visibleSteps[currentIndex + 1]?.value;

    return next ? /** @type {OnboardingStepId} */ (next) : null;
}

/**
 * @param {OnboardingStepId | string} step
 * @param {Array<{ value: string }>} visibleSteps
 * @returns {OnboardingStepId | null}
 */
export function getPreviousTabStep(step, visibleSteps) {
    const currentIndex = getOnboardingStepIndex(step, visibleSteps);

    if (currentIndex <= 0) {
        return null;
    }

    const previous = visibleSteps[currentIndex - 1]?.value;

    return previous ? /** @type {OnboardingStepId} */ (previous) : null;
}

/**
 * @param {OnboardingStepId | string} activeStep
 * @param {OnboardingStepId | string} candidateStep
 * @param {Array<{ value: string }>} visibleSteps
 * @returns {'active' | 'complete' | 'upcoming'}
 */
export function resolveOnboardingTabStatus(activeStep, candidateStep, visibleSteps) {
    const activeIndex = getOnboardingStepIndex(activeStep, visibleSteps);
    const candidateIndex = getOnboardingStepIndex(candidateStep, visibleSteps);

    if (candidateIndex === activeIndex) {
        return 'active';
    }

    if (candidateIndex >= 0 && candidateIndex < activeIndex) {
        return 'complete';
    }

    return 'upcoming';
}
