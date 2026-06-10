/** @typedef {import('./onboardingConstants.js').OnboardingStepId} OnboardingStepId */
/** @typedef {import('./onboardingState.js').OnboardingWizardState} OnboardingWizardState */

import { activityLevelToServer, dietProtocolToServer } from './onboardingNormalize.js';

/**
 * @param {OnboardingStepId} step
 * @param {OnboardingWizardState} state
 * @returns {Record<string, unknown>}
 */
export function buildOnboardingStepPayload(step, state) {
    switch (step) {
        case 'gender':
            return { sex: state.gender };
        case 'period_tracking':
            return {
                logged_periods: state.periodTracking.loggedPeriods,
                average_cycle_length:
                    state.periodTracking.averageCycleLength ?? 28,
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
        case 'daily_targets':
            return {};
        case 'food_filters': {
            const allergies = [...state.foodFilters];
            const other = String(state.allergyOther ?? '').trim();

            if (allergies.includes('other') && other !== '') {
                allergies.push(other);
            }

            return {
                allergies,
                allergy_other: allergies.includes('other') ? other : null,
            };
        }
        default:
            return {};
    }
}

/**
 * @param {OnboardingStepId} step
 * @param {Record<string, string>} urls
 * @returns {string | null}
 */
export function resolveOnboardingStepPostUrl(step, urls) {
    const map = {
        gender: urls.gender,
        period_tracking: urls.periodTracking,
        birthday: urls.birthday,
        height: urls.height,
        weight: urls.weight,
        target_weight: urls.targetWeight,
        activity: urls.activity,
        diet_protocol: urls.dietProtocol,
        daily_targets: urls.dailyTargets,
        food_filters: urls.foodFilters,
    };

    return map[step] ?? null;
}
