/** @typedef {import('./onboardingConstants.js').OnboardingStepId} OnboardingStepId */
/** @typedef {import('./onboardingState.js').OnboardingWizardState} OnboardingWizardState */

/**
 * @param {OnboardingStepId} step
 * @param {OnboardingWizardState} state
 * @returns {{ valid: boolean; errors: Record<string, string> }}
 */
export function validateOnboardingStep(step, state) {
    /** @type {Record<string, string>} */
    const errors = {};

    switch (step) {
        case 'gender':
            if (!state.gender) {
                errors.sex = 'Please select your gender.';
            }
            break;
        case 'period_tracking':
            break;
        case 'birthday':
            if (!state.birthdate) {
                errors.date_of_birth = 'Please select your birthday.';
            }
            break;
        case 'height':
            if (state.height == null || state.height <= 0) {
                errors.height_cm = 'Please enter your height.';
            }
            break;
        case 'weight':
            if (state.weight == null || state.weight <= 0) {
                errors.weight_kg = 'Please enter your weight.';
            }
            break;
        case 'target_weight':
            if (state.targetWeight == null || state.targetWeight <= 0) {
                errors.target_weight_kg = 'Please enter your target weight.';
            }
            break;
        case 'activity':
            if (!state.activityLevel) {
                errors.activity_level = 'Please select your activity level.';
            }
            break;
        case 'diet_protocol':
            if (!state.dietProtocol) {
                errors.diet_protocol = 'Please select a diet protocol.';
            }
            break;
        case 'daily_targets':
            break;
        case 'food_filters':
            if (state.foodFilters.includes('other') && !String(state.allergyOther ?? '').trim()) {
                errors.allergy_other = 'Please describe your other dietary restriction.';
            }
            break;
        default:
            break;
    }

    return { valid: Object.keys(errors).length === 0, errors };
}
