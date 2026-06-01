import { OnboardingPeriodTrackingInner } from './PeriodTracking.jsx';
import { ONBOARDING_STEPS } from './onboardingSteps.js';

export default {
    title: 'MealCraft/Pages/Onboarding/PeriodTrackingView',
    component: OnboardingPeriodTrackingInner,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Female-only onboarding step for logging menstrual period date ranges with calendar range selection.',
            },
        },
    },
};

export const Default = {
    render: () => (
        <OnboardingPeriodTrackingInner
            steps={ONBOARDING_STEPS}
            currentStep="period_tracking"
            customerName="Amina Saif"
        />
    ),
};

export const WithLoggedPeriods = {
    name: 'With logged periods',
    render: () => (
        <OnboardingPeriodTrackingInner
            loggedPeriods={[
                { start: '2026-04-20', end: '2026-04-26' },
                { start: '2026-03-18', end: '2026-03-23' },
            ]}
            steps={ONBOARDING_STEPS}
            currentStep="period_tracking"
            customerName="Amina Saif"
        />
    ),
};

export const WithCalculatedCycleLength = {
    name: 'With calculated cycle length',
    render: () => (
        <OnboardingPeriodTrackingInner
            loggedPeriods={[
                { start: '2026-01-01', end: '2026-01-05' },
                { start: '2026-02-01', end: '2026-02-05' },
                { start: '2026-03-03', end: '2026-03-07' },
                { start: '2026-03-31', end: '2026-04-04' },
            ]}
            steps={ONBOARDING_STEPS}
            currentStep="period_tracking"
            customerName="Amina Saif"
        />
    ),
};
