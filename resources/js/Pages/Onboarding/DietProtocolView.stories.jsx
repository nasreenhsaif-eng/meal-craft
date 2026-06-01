import { OnboardingDietProtocolInner } from './DietProtocol.jsx';
import { MobileStoryViewport } from '../../storybook/MobileStoryViewport.jsx';
import { ONBOARDING_STEPS } from './onboardingSteps.js';

export default {
    title: 'MealCraft/Pages/Onboarding/DietProtocolView',
    component: OnboardingDietProtocolInner,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Choose your diet protocol — uniform mobile stack, inline pop-up description under the active option, and full-width Next CTA.',
            },
        },
    },
    decorators: [
        (Story) => (
            <MobileStoryViewport>
                <Story />
            </MobileStoryViewport>
        ),
    ],
};

export const Default = {
    name: 'Choose your diet protocol',
    render: () => (
        <OnboardingDietProtocolInner
            steps={ONBOARDING_STEPS}
            currentStep="diet_protocol"
            customerName="Amina Saif"
            protocol="balanced"
        />
    ),
};

export const KetobioticSelected = {
    name: 'Ketobiotic selected',
    render: () => (
        <OnboardingDietProtocolInner
            protocol="ketobiotic"
            steps={ONBOARDING_STEPS}
            currentStep="diet_protocol"
            customerName="Amina Saif"
        />
    ),
};

export const CycleSyncSelected = {
    name: 'Cycle Sync selected',
    render: () => (
        <OnboardingDietProtocolInner
            protocol="cycle_sync"
            steps={ONBOARDING_STEPS}
            currentStep="diet_protocol"
            customerName="Amina Saif"
        />
    ),
};

export const SickleCellSelected = {
    name: 'Sickle cell warrior selected',
    render: () => (
        <OnboardingDietProtocolInner
            protocol="sickle_cell"
            steps={ONBOARDING_STEPS}
            currentStep="diet_protocol"
            customerName="Amina Saif"
        />
    ),
};

export const ValidationError = {
    name: 'Validation error',
    render: () => (
        <OnboardingDietProtocolInner
            steps={ONBOARDING_STEPS}
            currentStep="diet_protocol"
            customerName="Amina Saif"
            protocol="balanced"
            errors={{ diet_protocol: 'Please select a diet protocol to continue.' }}
        />
    ),
};
