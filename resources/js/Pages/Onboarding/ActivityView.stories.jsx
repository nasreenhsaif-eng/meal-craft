import { OnboardingActivityInner } from './Activity.jsx';
import { withOnboardingMobileFrame } from './onboardingStoryDecorators.jsx';
import { ONBOARDING_STEPS } from './onboardingSteps.js';

export default {
    title: 'MealCraft/Pages/Onboarding/ActivityView',
    component: OnboardingActivityInner,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Customer onboarding activity level step — secondary-style option stack with inline description under the active choice.',
            },
        },
    },
    decorators: withOnboardingMobileFrame,
};

export const Default = {
    render: () => (
        <OnboardingActivityInner
            steps={ONBOARDING_STEPS}
            currentStep="activity"
            customerName="Amina Saif"
            activityLevel="moderate"
        />
    ),
};

export const NotActive = {
    name: 'Not active',
    render: () => (
        <OnboardingActivityInner
            steps={ONBOARDING_STEPS}
            currentStep="activity"
            customerName="Amina Saif"
            activityLevel="sedentary"
        />
    ),
};

export const ValidationError = {
    name: 'Validation error',
    render: () => (
        <OnboardingActivityInner
            steps={ONBOARDING_STEPS}
            currentStep="activity"
            customerName="Amina Saif"
            activityLevel="moderate"
            errors={{ activity_level: 'Please select your activity level.' }}
        />
    ),
};
