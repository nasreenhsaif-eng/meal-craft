/** @typedef {'gender' | 'period_tracking' | 'birthday' | 'height' | 'weight' | 'target_weight' | 'activity' | 'diet_protocol' | 'daily_targets' | 'food_filters'} OnboardingStepId */

/** @typedef {'male' | 'female'} OnboardingGender */

/**
 * @typedef {'sedentary' | 'lightly_active' | 'moderately_active' | 'very_active'
 *   | 'light' | 'moderate' | 'active'} OnboardingActivityLevel
 */

/**
 * @typedef {'balanced' | 'ketobiotic' | 'cycle_sync' | 'thyroid' | 'sickle_cell_warrior' | 'sickle_cell'} OnboardingDietProtocol
 */

export const ONBOARDING_STORAGE_KEY = 'mealcraft.onboarding.wizard.v1';

/** @type {readonly OnboardingStepId[]} */
export const ONBOARDING_FLOW_STEPS = [
    'gender',
    'diet_protocol',
    'period_tracking',
    'birthday',
    'height',
    'weight',
    'target_weight',
    'activity',
    'daily_targets',
    'food_filters',
];

/** kcal → kJ conversion factor (1 kcal = 4.184 kJ). */
export const KCAL_TO_KJ = 4.184;

export const GOAL_CALORIE_DELTA = {
    deficit: 500,
    surplus: 300,
};
