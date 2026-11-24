import { WelcomeBackInner } from './Home.jsx';
import { withOnboardingMobileFrame } from '../Onboarding/onboardingStoryDecorators.jsx';

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
    title: 'Design System/06. Pages/App/Home',
    component: WelcomeBackInner,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Customer home at `/app` — welcome back with targets, choose-meals CTA when plan is ready, and feature teasers.',
            },
        },
    },
    decorators: withOnboardingMobileFrame,
};

export const PlanReady = {
    name: 'Plan ready — choose meals',
    render: () => (
        <WelcomeBackInner
            customerName="Ishaq Ibrahim"
            profile={PLAN_READY_PROFILE}
            mode="welcome"
            fulfillmentUrl="/checkout/fulfillment"
            chooseByFridayLabel="24th October"
            showPlanReady
            onChooseMeals={() => undefined}
        />
    ),
};

export const AlreadyChose = {
    name: 'Already chose meals',
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

export const ReceiveMode = {
    name: 'Receive meal plan',
    render: () => (
        <WelcomeBackInner
            customerName="Nasreen"
            profile={NASREEN_PROFILE}
            mode="receive"
            homeUrl="/app"
        />
    ),
};