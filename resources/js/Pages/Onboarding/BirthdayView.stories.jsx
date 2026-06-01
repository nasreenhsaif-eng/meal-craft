import { OnboardingBirthdayInner } from './Birthday.jsx';
import { ONBOARDING_STEPS } from './onboardingSteps.js';

export default {
    title: 'MealCraft/Pages/Onboarding/BirthdayView',
    component: OnboardingBirthdayInner,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component: 'Customer onboarding birthday step with scrollable wheel date picker.',
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
        />
    ),
};

export const WithDate = {
    name: 'With date',
    render: () => (
        <OnboardingBirthdayInner
            dateOfBirth="1992-03-15"
            steps={ONBOARDING_STEPS}
            currentStep="birthday"
            customerName="Amina Saif"
        />
    ),
};
