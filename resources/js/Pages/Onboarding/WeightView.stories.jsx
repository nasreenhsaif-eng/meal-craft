import { OnboardingWeightInner } from './Weight.jsx';
import { withOnboardingMobileFrame } from './onboardingStoryDecorators.jsx';
import { ONBOARDING_STEPS } from './onboardingSteps.js';

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
    decorators: withOnboardingMobileFrame,
};

export const Default = {
    render: () => (
        <OnboardingWeightInner
            steps={ONBOARDING_STEPS}
            currentStep="weight"
            customerName="Amina Saif"
            weightKg={68}
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
            weightKg={72}
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
            errors={{ weight_kg: 'Weight must be at least 35 kg.' }}
        />
    ),
};
