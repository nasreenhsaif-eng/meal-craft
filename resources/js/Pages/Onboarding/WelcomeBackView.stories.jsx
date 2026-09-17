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
                    'Post-login home for customers who finished onboarding — welcome-back card with daily targets and onboarding-style choices (edit profile, edit meals plan, view summary). Actions continue from the meal plan summary.',
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
            onEditProfile={() => undefined}
            onEditMealsPlan={() => undefined}
            onViewSummary={() => undefined}
        />
    ),
};
