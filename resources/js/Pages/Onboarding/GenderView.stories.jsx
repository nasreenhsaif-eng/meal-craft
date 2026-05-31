import { OnboardingGenderInner } from './Gender.jsx';

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

const SEX_OPTIONS = [
    { value: 'male', label: 'Male' },
    { value: 'female', label: 'Female' },
];

export default {
    title: 'MealCraft/Pages/Onboarding/GenderView',
    component: OnboardingGenderInner,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Customer onboarding gender step — first profile question after account creation and welcome.',
            },
        },
    },
};

export const Default = {
    render: () => (
        <OnboardingGenderInner
            steps={ONBOARDING_STEPS}
            currentStep="gender"
            customerName="Amina Saif"
            options={SEX_OPTIONS}
        />
    ),
};

export const WithSelection = {
    name: 'With selection',
    render: () => (
        <OnboardingGenderInner
            sex="female"
            steps={ONBOARDING_STEPS}
            currentStep="gender"
            customerName="Amina Saif"
            options={SEX_OPTIONS}
        />
    ),
};

export const ValidationError = {
    name: 'Validation error',
    render: () => (
        <OnboardingGenderInner
            steps={ONBOARDING_STEPS}
            currentStep="gender"
            customerName="Amina Saif"
            options={SEX_OPTIONS}
            errors={{ sex: 'Please select your gender.' }}
        />
    ),
};
