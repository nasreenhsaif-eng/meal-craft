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
                    'Post-onboarding customer home — welcome-back card with daily calorie range, macro style, and meal-plan actions.',
            },
        },
    },
    decorators: withOnboardingMobileFrame,
};

export const Default = {
    name: 'Welcome back — plan saved',
    render: () => (
        <WelcomeBackInner
            customerName="Nasreen"
            profile={NASREEN_PROFILE}
            hasSubmittedPlan
            onViewSummary={() => undefined}
            onEditPlan={() => undefined}
            onActions={() => undefined}
        />
    ),
};

export const ChooseMeals = {
    name: 'Welcome back — choose meals',
    render: () => (
        <WelcomeBackInner
            customerName="Nasreen"
            profile={NASREEN_PROFILE}
            onStartPlan={() => undefined}
            onActions={() => undefined}
        />
    ),
};
