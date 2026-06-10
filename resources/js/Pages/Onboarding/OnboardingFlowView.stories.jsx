import { OnboardingFlowViewInner } from './OnboardingFlowView.jsx';
import { withOnboardingMobileFrame } from './onboardingStoryDecorators.jsx';

export default {
    title: 'MealCraft/Pages/Onboarding/OnboardingFlowView',
    component: OnboardingFlowViewInner,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'End-to-end male onboarding wizard for Storybook. Use Continue on each step or the flow chrome Back control. Skips welcome and period tracking.',
            },
        },
    },
    decorators: withOnboardingMobileFrame,
};

export const MaleFlowMobile = {
    name: 'Male flow',
    render: () => <OnboardingFlowViewInner customerName="James Okonkwo" />,
};

export const StartingAtActivity = {
    name: 'Jump-in — activity step',
    render: () => (
        <OnboardingFlowViewInner
            customerName="James Okonkwo"
            initialStep="activity"
            initialState={{
                gender: 'male',
                birthdate: '1990-06-15',
                height: 178,
                weight: 82,
                targetWeight: 78,
                activityLevel: 'moderately_active',
            }}
        />
    ),
};

export const StartingAtDailyTargets = {
    name: 'Jump-in — daily targets',
    render: () => (
        <OnboardingFlowViewInner
            customerName="James Okonkwo"
            initialStep="daily_targets"
            initialState={{
                gender: 'male',
                birthdate: '1990-06-15',
                height: 178,
                weight: 82,
                targetWeight: 78,
                activityLevel: 'lightly_active',
                dietProtocol: 'balanced',
            }}
        />
    ),
};

export const WithoutFlowChrome = {
    name: 'Without flow chrome (step UI only)',
    render: () => <OnboardingFlowViewInner customerName="James Okonkwo" showFlowChrome={false} />,
};
