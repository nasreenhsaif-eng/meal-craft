import { OnboardingActivityInner } from './Activity.jsx';

const ONBOARDING_STEPS = [
    { value: 'welcome', label: 'Welcome' },
    { value: 'gender', label: 'Gender' },
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
    title: 'MealCraft/Pages/Onboarding/ActivityView',
    component: OnboardingActivityInner,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component: 'Customer onboarding activity level step with scroll wheel and dynamic description.',
            },
        },
    },
};

export const Default = {
    render: () => (
        <OnboardingActivityInner
            steps={ONBOARDING_STEPS}
            currentStep="activity"
            customerName="Amina Saif"
            activityLevel="moderate"
        />
    ),
};

export const NotActive = {
    name: 'Not active',
    render: () => (
        <OnboardingActivityInner
            steps={ONBOARDING_STEPS}
            currentStep="activity"
            customerName="Amina Saif"
            activityLevel="sedentary"
        />
    ),
};

export const ValidationError = {
    name: 'Validation error',
    render: () => (
        <OnboardingActivityInner
            steps={ONBOARDING_STEPS}
            currentStep="activity"
            customerName="Amina Saif"
            activityLevel=""
            errors={{ activity_level: 'Please select your activity level.' }}
        />
    ),
};
