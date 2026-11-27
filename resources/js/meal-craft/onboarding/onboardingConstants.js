/** @typedef {'gender' | 'period_tracking' | 'protocol_selection' | 'active_phase' | 'birthday' | 'height' | 'weight' | 'target_weight' | 'activity' | 'diet_protocol' | 'daily_targets' | 'food_filters'} OnboardingStepId */

/** @typedef {'male' | 'female'} OnboardingGender */

/**
 * @typedef {'sedentary' | 'lightly_active' | 'moderately_active' | 'very_active'
 *   | 'light' | 'moderate' | 'active'} OnboardingActivityLevel
 */

/**
 * @typedef {'balanced' | 'ketobiotic' | 'cycle_sync' | 'thyroid' | 'sickle_cell_warrior' | 'sickle_cell' | 'nutrient_dense'} OnboardingDietProtocol
 */

export const ONBOARDING_STORAGE_KEY = 'mealcraft.onboarding.wizard.v1';

/** @type {readonly OnboardingStepId[]} */
export const ONBOARDING_FLOW_STEPS = [
    'gender',
    'birthday',
    'height',
    'weight',
    'target_weight',
    'activity',
    'diet_protocol',
    'period_tracking',
    'protocol_selection',
    'active_phase',
    'daily_targets',
    'food_filters',
];

/** @type {readonly OnboardingStepId[]} */
export const CYCLE_SYNC_FLOW_STEPS = ['period_tracking', 'protocol_selection', 'active_phase'];

/** kcal → kJ conversion factor (1 kcal = 4.184 kJ). */
export const KCAL_TO_KJ = 4.184;

export const GOAL_CALORIE_DELTA = {
    deficit: 500,
    surplus: 300,
};
