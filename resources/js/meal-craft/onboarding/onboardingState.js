/** @typedef {import('./onboardingConstants.js').OnboardingStepId} OnboardingStepId */
/** @typedef {import('./onboardingConstants.js').OnboardingGender} OnboardingGender */
/** @typedef {import('./onboardingConstants.js').OnboardingActivityLevel} OnboardingActivityLevel */
/** @typedef {import('./onboardingConstants.js').OnboardingDietProtocol} OnboardingDietProtocol */
/** @typedef {import('../dailyTargetsCalculator.js').DailyTargetsResult} DailyTargetsResult */

import { calculateAgeFromBirthdate } from './onboardingDates.js';
import { normalizeActivityLevel, normalizeDietProtocol } from './onboardingNormalize.js';

/**
 * @typedef {{
 *   loggedPeriods: Array<{ start: string; end: string }>;
 *   averageCycleLength: number | null;
 * }} PeriodTrackingState
 */

/**
 * @typedef {{
 *   currentStep: OnboardingStepId;
 *   gender: OnboardingGender | '';
 *   periodTracking: PeriodTrackingState;
 *   birthdate: string;
 *   height: number | null;
 *   weight: number | null;
 *   targetWeight: number | null;
 *   activityLevel: OnboardingActivityLevel;
 *   dietProtocol: OnboardingDietProtocol;
 *   foodFilters: string[];
 *   allergyOther: string;
 *   computedTargets: DailyTargetsResult | null;
 * }} OnboardingWizardState
 */

export function createInitialOnboardingState() {
    return {
        currentStep: 'welcome',
        gender: '',
        periodTracking: {
            loggedPeriods: [],
            averageCycleLength: null,
        },
        birthdate: '',
        height: null,
        weight: null,
        targetWeight: null,
        activityLevel: 'lightly_active',
        dietProtocol: 'balanced',
        foodFilters: [],
        allergyOther: '',
        computedTargets: null,
    };
}

/**
 * @param {Partial<OnboardingWizardState>} state
 * @returns {import('../dailyTargetsCalculator.js').OnboardingProfileInput}
 */
export function onboardingStateToProfile(state) {
    const age = state.birthdate ? calculateAgeFromBirthdate(state.birthdate) : null;

    return {
        sex: state.gender || 'female',
        age: age ?? undefined,
        height_cm: state.height,
        weight_kg: state.weight,
        target_weight_kg: state.targetWeight ?? state.weight,
        activity_level: state.activityLevel,
        activityLevel: state.activityLevel,
        diet_protocol: state.dietProtocol,
        dietProtocol: state.dietProtocol,
        allergies: state.foodFilters,
    };
}

/**
 * Hydrate wizard state from Inertia `mealCraft.onboarding` payload.
 *
 * @param {OnboardingWizardState} state
 * @param {object} onboarding
 * @returns {OnboardingWizardState}
 */
export function hydrateOnboardingFromServer(state, onboarding) {
    const profile = onboarding?.profile ?? {};
    const allergies = Array.isArray(profile.allergies) ? profile.allergies : [];

    return {
        ...state,
        currentStep: onboarding?.currentStep ?? state.currentStep,
        gender: profile.sex === 'male' || profile.sex === 'female' ? profile.sex : state.gender,
        periodTracking: {
            loggedPeriods: Array.isArray(profile.logged_periods)
                ? profile.logged_periods
                : Array.isArray(profile.loggedPeriods)
                  ? profile.loggedPeriods
                  : state.periodTracking.loggedPeriods,
            averageCycleLength:
                profile.average_cycle_length ?? profile.averageCycleLength ?? state.periodTracking.averageCycleLength,
        },
        birthdate: profile.date_of_birth ?? profile.dateOfBirth ?? state.birthdate,
        height: profile.height_cm ?? profile.heightCm ?? state.height,
        weight: profile.weight_kg ?? profile.weightKg ?? state.weight,
        targetWeight: profile.target_weight_kg ?? profile.targetWeightKg ?? state.targetWeight,
        activityLevel: normalizeActivityLevel(profile.activity_level ?? profile.activityLevel ?? state.activityLevel),
        dietProtocol: normalizeDietProtocol(profile.diet_protocol ?? profile.dietProtocol ?? state.dietProtocol),
        foodFilters: allergies,
        allergyOther: profile.allergy_other ?? profile.allergyOther ?? state.allergyOther,
    };
}

/**
 * @param {OnboardingWizardState} state
 * @param {Partial<OnboardingWizardState>} patch
 * @returns {OnboardingWizardState}
 */
export function patchOnboardingState(state, patch) {
    return {
        ...state,
        ...patch,
        periodTracking: patch.periodTracking
            ? { ...state.periodTracking, ...patch.periodTracking }
            : state.periodTracking,
    };
}
