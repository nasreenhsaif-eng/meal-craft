import { WelcomeBackInner } from '../App/Home.jsx';
import { withOnboardingMobileFrame } from './onboardingStoryDecorators.jsx';

/** Plan-ready welcome: targets + choose-meals CTA + feature teasers. */
const PLAN_READY_PROFILE = {
    dailyCaloriesMin: 2540,
    dailyCaloriesMax: 2640,
    macroSplitStyle: 'balanced',
};

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
                    'Post-login home. **Plan ready** (default): welcome, calorie/macro summary, choose-meals CTA, and coming-soon teasers. **Already chose** hides the CTA. Receive (Partner Kitchen / DIY) lives in consultation after day selection; Storybook still demos that UI via receive mode.',
            },
        },
    },
    decorators: withOnboardingMobileFrame,
};

function PlanReadyWelcome() {
    return (
        <WelcomeBackInner
            customerName="Ishaq Ibrahim"
            profile={PLAN_READY_PROFILE}
            mode="welcome"
            fulfillmentUrl="/checkout/fulfillment"
            chooseByFridayLabel="24th October"
            showPlanReady
            onChooseMeals={() => undefined}
        />
    );
}

/** Default — plan ready with Choose your meals under the targets card. */
export const Default = {
    name: 'Welcome back — plan ready',
    render: () => <PlanReadyWelcome />,
};

/** Same as Default — keeps older Storybook URLs (`--choose-meals`) working. */
export const ChooseMeals = {
    name: 'Welcome back — plan ready (choose meals)',
    render: () => <PlanReadyWelcome />,
};

export const AlreadyChose = {
    name: 'Welcome back — already chose',
    render: () => (
        <WelcomeBackInner
            customerName="Ishaq Ibrahim"
            profile={PLAN_READY_PROFILE}
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
