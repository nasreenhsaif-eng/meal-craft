import { OnboardingBirthdayInner } from './Birthday.jsx';

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
    title: 'MealCraft/Pages/Onboarding/BirthdayView',
    component: OnboardingBirthdayInner,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component: 'Customer onboarding birthday step with scrollable month / day / year wheels.',
            },
        },
    },
};

export const Default = {
    render: () => (
        <OnboardingBirthdayInner
            steps={ONBOARDING_STEPS}
            currentStep="birthday"
            customerName="Amina Saif"
            dateOfBirth="1952-03-03"
        />
    ),
};

export const ValidationError = {
    name: 'Validation error',
    render: () => (
        <OnboardingBirthdayInner
            steps={ONBOARDING_STEPS}
            currentStep="birthday"
            customerName="Amina Saif"
            dateOfBirth="2015-01-01"
            errors={{ date_of_birth: 'You must be at least 13 years old to join Meal Craft.' }}
        />
    ),
};
