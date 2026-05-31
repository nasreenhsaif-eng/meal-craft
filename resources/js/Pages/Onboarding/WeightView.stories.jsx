import { OnboardingWeightInner } from './Weight.jsx';

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
    title: 'MealCraft/Pages/Onboarding/WeightView',
    component: OnboardingWeightInner,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component: 'Customer onboarding weight step with scroll wheel and kg/lb unit toggle.',
            },
        },
    },
};

export const Default = {
    render: () => (
        <OnboardingWeightInner
            steps={ONBOARDING_STEPS}
            currentStep="weight"
            customerName="Amina Saif"
            weightKg={70}
        />
    ),
};

export const Pounds = {
    name: 'Pounds',
    render: () => (
        <OnboardingWeightInner
            steps={ONBOARDING_STEPS}
            currentStep="weight"
            customerName="Amina Saif"
            weightKg={68}
            defaultUnit="lb"
        />
    ),
};

export const ValidationError = {
    name: 'Validation error',
    render: () => (
        <OnboardingWeightInner
            steps={ONBOARDING_STEPS}
            currentStep="weight"
            customerName="Amina Saif"
            weightKg={30}
            errors={{ weight_kg: 'Weight must be at least 40 kg.' }}
        />
    ),
};
