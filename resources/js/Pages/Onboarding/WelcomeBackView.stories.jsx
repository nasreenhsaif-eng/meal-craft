import { WelcomeBackInner } from '../App/Home.jsx';
import { withOnboardingMobileFrame } from './onboardingStoryDecorators.jsx';

const NASREEN_PROFILE = {
    dailyCaloriesMin: 1575,
    dailyCaloriesMax: 1825,
    macroSplitStyle: 'balanced',
};

export default {
    title: 'Design System/05. Templates & Pages/Onboarding/WelcomeBack',
    component: WelcomeBackInner,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Post-login home (Welcome back + ghost outline actions) and summary-flow receive mode (Partner Kitchen / DIY cards, then the same actions under the cards).',
            },
        },
    },
    decorators: withOnboardingMobileFrame,
};

export const Default = {
    name: 'Welcome back',
    render: () => (
        <WelcomeBackInner
            customerName="Nasreen"
            profile={NASREEN_PROFILE}
            mode="welcome"
            onEditProfile={() => undefined}
            onEditMealsPlan={() => undefined}
            onViewSummary={() => undefined}
        />
    ),
};

export const FromSummary = {
    name: 'From summary — receive meal plan',
    render: () => (
        <WelcomeBackInner
            customerName="Nasreen"
            profile={NASREEN_PROFILE}
            mode="receive"
            onEditProfile={() => undefined}
            onEditMealsPlan={() => undefined}
            onViewSummary={() => undefined}
        />
    ),
};
