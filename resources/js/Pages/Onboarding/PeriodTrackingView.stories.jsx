import { OnboardingPeriodTrackingInner } from './PeriodTracking.jsx';

const ONBOARDING_STEPS_FEMALE = [
    { value: 'welcome', label: 'Welcome' },
    { value: 'gender', label: 'Gender' },
    { value: 'period_tracking', label: 'Track your period' },
    { value: 'birthday', label: 'Birthday' },
    { value: 'height', label: 'Height' },
    { value: 'weight', label: 'Weight' },
    { value: 'target_weight', label: 'Target weight' },
    { value: 'activity', label: 'Activity' },
    { value: 'macros', label: 'Macro split' },
    { value: 'meals', label: 'Choose meals' },
    { value: 'review', label: 'Review' },
];

export default {
    title: 'MealCraft/Pages/Onboarding/PeriodTrackingView',
    component: OnboardingPeriodTrackingInner,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Female-only onboarding step for logging menstrual period date ranges with calendar range selection.',
            },
        },
    },
};

export const Default = {
    render: () => (
        <OnboardingPeriodTrackingInner
            steps={ONBOARDING_STEPS_FEMALE}
            currentStep="period_tracking"
            customerName="Amina Saif"
        />
    ),
};

export const WithLoggedPeriods = {
    name: 'With logged periods',
    render: () => (
        <OnboardingPeriodTrackingInner
            loggedPeriods={[
                { start: '2026-04-20', end: '2026-04-26' },
                { start: '2026-03-18', end: '2026-03-23' },
            ]}
            steps={ONBOARDING_STEPS_FEMALE}
            currentStep="period_tracking"
            customerName="Amina Saif"
        />
    ),
};

export const WithCalculatedCycleLength = {
    name: 'With calculated cycle length',
    render: () => (
        <OnboardingPeriodTrackingInner
            loggedPeriods={[
                { start: '2026-01-01', end: '2026-01-05' },
                { start: '2026-02-01', end: '2026-02-05' },
                { start: '2026-03-03', end: '2026-03-07' },
                { start: '2026-03-31', end: '2026-04-04' },
            ]}
            steps={ONBOARDING_STEPS_FEMALE}
            currentStep="period_tracking"
            customerName="Amina Saif"
        />
    ),
};
