import { OnboardingTargetWeightInner } from './TargetWeight.jsx';
import { withOnboardingMobileFrame } from './onboardingStoryDecorators.jsx';
import { ONBOARDING_STEPS } from './onboardingSteps.js';

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
    decorators: withOnboardingMobileFrame,
};

export const Default = {
    render: () => (
        <OnboardingTargetWeightInner
            steps={ONBOARDING_STEPS}
            currentStep="target_weight"
            customerName="Amina Saif"
            targetWeightKg={65}
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
            targetWeightKg={68}
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
            targetWeightKg={30}
            errors={{ target_weight_kg: 'Target weight must be at least 35 kg.' }}
        />
    ),
};
