import { OnboardingProtocolSelectionInner } from './ProtocolSelection.jsx';
import { withOnboardingMobileFrame } from './onboardingStoryDecorators.jsx';
import { ONBOARDING_STEPS } from './onboardingSteps.js';

export default {
    title: 'Design System/06. Pages/Onboarding/ProtocolSelectionView',
    component: OnboardingProtocolSelectionInner,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Screen 2 after Period Logging: Meals only (default) or Meals + fasting, using the same option-button pattern as Diet Protocol.',
            },
        },
    },
    decorators: withOnboardingMobileFrame,
};

export const Default = {
    name: 'Meals only (default)',
    render: () => (
        <OnboardingProtocolSelectionInner
            protocolMode="non_fasting"
            steps={ONBOARDING_STEPS}
            currentStep="protocol_selection"
            customerName="Amina Saif"
            onSubmit={() => undefined}
        />
    ),
};

export const FastingSelected = {
    name: 'Meals + fasting selected',
    render: () => (
        <OnboardingProtocolSelectionInner
            protocolMode="fasting"
            steps={ONBOARDING_STEPS}
            currentStep="protocol_selection"
            customerName="Amina Saif"
            onSubmit={() => undefined}
        />
    ),
};
