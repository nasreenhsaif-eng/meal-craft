import { OnboardingHeightInner } from './Height.jsx';

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
    title: 'MealCraft/Pages/Onboarding/HeightView',
    component: OnboardingHeightInner,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component: 'Customer onboarding height step with scrollable wheel picker and ft/in vs cm toggle.',
            },
        },
    },
};

export const Default = {
    render: () => (
        <OnboardingHeightInner
            steps={ONBOARDING_STEPS}
            currentStep="height"
            customerName="Amina Saif"
            heightCm={170}
        />
    ),
};

export const FeetAndInches = {
    name: 'Feet and inches',
    render: () => (
        <OnboardingHeightInner
            steps={ONBOARDING_STEPS}
            currentStep="height"
            customerName="Amina Saif"
            heightCm={175}
            defaultUnit="ft_in"
        />
    ),
};

export const ValidationError = {
    name: 'Validation error',
    render: () => (
        <OnboardingHeightInner
            steps={ONBOARDING_STEPS}
            currentStep="height"
            customerName="Amina Saif"
            heightCm={90}
            errors={{ height_cm: 'Height must be at least 100 cm.' }}
        />
    ),
};
