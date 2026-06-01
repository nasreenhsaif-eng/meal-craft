/**
 * Canonical onboarding step chips — keep in sync with {@see App\Enums\OnboardingStep}.
 */
export const ONBOARDING_STEPS = [
    { value: 'welcome', label: 'Welcome' },
    { value: 'gender', label: 'Gender' },
    { value: 'period_tracking', label: 'Track your period' },
    { value: 'birthday', label: 'Birthday' },
    { value: 'height', label: 'Height' },
    { value: 'weight', label: 'Weight' },
    { value: 'target_weight', label: 'Target weight' },
    { value: 'activity', label: 'Activity' },
    { value: 'diet_protocol', label: 'Diet protocol' },
    { value: 'daily_targets', label: 'Daily targets' },
    { value: 'food_filters', label: 'Food filters' },
];

/**
 * @param {{ includePeriodTracking?: boolean }} [options]
 * @returns {typeof ONBOARDING_STEPS}
 */
export function onboardingSteps({ includePeriodTracking = true } = {}) {
    if (includePeriodTracking) {
        return ONBOARDING_STEPS;
    }

    return ONBOARDING_STEPS.filter((step) => step.value !== 'period_tracking');
}
