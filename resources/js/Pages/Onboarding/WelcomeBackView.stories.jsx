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
                    'Post-login home (Welcome back + calorie/macro summary; plan-ready deadline copy + Choose your meals when applicable; coming-soon feature teasers; Meal Plan in bottom nav) and summary-flow receive mode (vertical Partner Kitchen / DIY options with expandable details + Next; Back to home; no bottom nav).',
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
            fulfillmentUrl="/checkout/fulfillment"
            chooseByFridayLabel="24th October"
            showPlanReady
            onChooseMeals={() => undefined}
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
            fulfillmentUrl="/checkout/fulfillment"
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
