import { OnboardingTargetWeightInner } from './TargetWeight.jsx';

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
    title: 'MealCraft/Pages/Onboarding/TargetWeightView',
    component: OnboardingTargetWeightInner,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component: 'Customer onboarding target weight step with scroll wheel and kg/lb unit toggle.',
            },
        },
    },
};

export const Default = {
    render: () => (
        <OnboardingTargetWeightInner
            steps={ONBOARDING_STEPS}
            currentStep="target_weight"
            customerName="Amina Saif"
            weightKg={68}
        />
    ),
};

export const Pounds = {
    name: 'Pounds',
    render: () => (
        <OnboardingTargetWeightInner
            steps={ONBOARDING_STEPS}
            currentStep="target_weight"
            customerName="Amina Saif"
            weightKg={65}
            defaultUnit="lb"
        />
    ),
};

export const ValidationError = {
    name: 'Validation error',
    render: () => (
        <OnboardingTargetWeightInner
            steps={ONBOARDING_STEPS}
            currentStep="target_weight"
            customerName="Amina Saif"
            weightKg={30}
            errors={{ target_weight_kg: 'Target weight must be at least 40 kg.' }}
        />
    ),
};
