import { router } from '@inertiajs/react';
import { useCallback } from 'react';
import { activityLevelToServer, dietProtocolToServer } from './onboardingNormalize.js';
import { useOnboardingStore } from './OnboardingProvider.jsx';

/**
 * @typedef {import('./onboardingConstants.js').OnboardingStepId} OnboardingStepId
 */

const STEP_SUBMIT_URL = {
    gender: 'gender',
    period_tracking: 'periodTracking',
    birthday: 'birthday',
    height: 'height',
    weight: 'weight',
    target_weight: 'targetWeight',
    activity: 'activity',
    diet_protocol: 'dietProtocol',
    daily_targets: 'dailyTargets',
    food_filters: 'foodFilters',
};

/**
 * @param {OnboardingStepId} stepId
 */
export function useOnboardingWizard(stepId) {
    const { state, patch, serverOnboarding, computeTargetsBeforeSummary, nextStep } = useOnboardingStore();

    const urls = serverOnboarding.urls ?? {};

    const advance = useCallback(
        (payload = {}) => {
            const urlKey = STEP_SUBMIT_URL[stepId];
            const url = urlKey ? urls[urlKey] : null;

            if (!url) {
                return;
            }

            if (stepId === 'diet_protocol') {
                computeTargetsBeforeSummary();
            }

            router.post(url, payload, {
                preserveScroll: true,
                onSuccess: () => {
                    patch({ currentStep: nextStep(stepId) ?? state.currentStep });
                },
            });
        },
        [stepId, urls, computeTargetsBeforeSummary, patch, nextStep, state.currentStep],
    );

    return {
        state,
        patch,
        advance,
        urls,
        computeTargetsBeforeSummary,
        nextStep: nextStep(stepId),
    };
}

/**
 * Build POST body for a step from store snapshot.
 *
 * @param {OnboardingStepId} stepId
 * @param {import('./onboardingState.js').OnboardingWizardState} state
 */
export function buildStepPayload(stepId, state) {
    switch (stepId) {
        case 'gender':
            return { sex: state.gender };
        case 'period_tracking':
            return {
                logged_periods: state.periodTracking.loggedPeriods,
                average_cycle_length: state.periodTracking.averageCycleLength,
            };
        case 'birthday':
            return { date_of_birth: state.birthdate };
        case 'height':
            return { height_cm: state.height };
        case 'weight':
            return { weight_kg: state.weight };
        case 'target_weight':
            return { target_weight_kg: state.targetWeight };
        case 'activity':
            return { activity_level: activityLevelToServer(state.activityLevel) };
        case 'diet_protocol':
            return { diet_protocol: dietProtocolToServer(state.dietProtocol) };
        case 'food_filters':
            return { allergies: state.foodFilters };
        default:
            return {};
    }
}

export default useOnboardingWizard;
