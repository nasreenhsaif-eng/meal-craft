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
                    'Post-login home (Welcome back + calorie/macro summary and optional plan-ready CTA; account destinations live in the bottom nav) and summary-flow receive mode (vertical Partner Kitchen / DIY options with expandable details + Next; Back to home; no bottom nav).',
            },
        },
    },
    decorators: withOnboardingMobileFrame,
};

export const Default = {
    name: 'Welcome back — plan ready',
    render: () => (
        <WelcomeBackInner
            customerName="Nasreen"
            profile={NASREEN_PROFILE}
            mode="welcome"
            onEditMealsPlan={() => undefined}
            planDateRangeLabel="27th September to 3rd October"
            showPlanReady
        />
    ),
};

export const AlreadyChose = {
    name: 'Welcome back — already chose',
    render: () => (
        <WelcomeBackInner
            customerName="Nasreen"
            profile={NASREEN_PROFILE}
            mode="welcome"
            onEditMealsPlan={() => undefined}
            showPlanReady={false}
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
            homeUrl="/app"
        />
    ),
};
