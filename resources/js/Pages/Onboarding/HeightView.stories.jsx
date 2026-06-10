import { OnboardingHeightInner } from './Height.jsx';
import { withOnboardingMobileFrame } from './onboardingStoryDecorators.jsx';
import { ONBOARDING_STEPS } from './onboardingSteps.js';

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
    decorators: withOnboardingMobileFrame,
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
