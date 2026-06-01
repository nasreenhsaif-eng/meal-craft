import { OnboardingGenderInner } from './Gender.jsx';
import { ONBOARDING_STEPS } from './onboardingSteps.js';

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
