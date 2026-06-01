import { calculateDailyTargets } from '../../meal-craft/dailyTargetsCalculator.js';
import { DailyTargetsSummaryInner } from './DailyTargetsSummary.jsx';
import { ONBOARDING_STEPS } from './onboardingSteps.js';

const DEMO_PROFILE = {
    sex: 'female',
    age: 32,
    weight_kg: 68,
    height_cm: 165,
    activity_level: 'lightly_active',
    target_weight_kg: 68,
    diet_protocol: 'balanced',
};

export default {
    title: 'MealCraft/Pages/Onboarding/DailyTargetsSummary',
    component: DailyTargetsSummaryInner,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Summarizes calculated daily calorie and macro targets from onboarding metrics using Mifflin-St Jeor and preset macro splits.',
            },
        },
    },
};

export const Default = {
    render: () => (
        <DailyTargetsSummaryInner
            targets={calculateDailyTargets(DEMO_PROFILE)}
            steps={ONBOARDING_STEPS}
            currentStep="daily_targets"
            customerName="Amina Saif"
            onStartPlan={() => undefined}
        />
    ),
};

export const MaleMaintainBalanced = {
    name: 'Male — balanced split',
    render: () => (
        <DailyTargetsSummaryInner
            targets={calculateDailyTargets({
                sex: 'male',
                age: 35,
                weight_kg: 80,
                height_cm: 178,
                activity_level: 'lightly_active',
                target_weight_kg: 80,
                diet_protocol: 'balanced',
            })}
            steps={ONBOARDING_STEPS.filter((step) => step.value !== 'period_tracking')}
            currentStep="daily_targets"
            customerName="James Okonkwo"
            onStartPlan={() => undefined}
        />
    ),
};

export const FemaleWeightLoss = {
    name: 'Female — weight loss',
    render: () => (
        <DailyTargetsSummaryInner
            targets={calculateDailyTargets({
                sex: 'female',
                age: 28,
                weight_kg: 72,
                height_cm: 168,
                target_weight_kg: 65,
                activity_level: 'moderately_active',
                diet_protocol: 'ketobiotic',
            })}
            steps={ONBOARDING_STEPS}
            currentStep="daily_targets"
            customerName="Amina Saif"
            onStartPlan={() => undefined}
        />
    ),
};

export const WithExplicitCalorieOverride = {
    name: 'With explicit calorie override',
    render: () => (
        <DailyTargetsSummaryInner
            targets={calculateDailyTargets({
                ...DEMO_PROFILE,
                daily_calorie_target: 1929,
                protein_percentage: 40,
                carb_percentage: 35,
                fat_percentage: 25,
            })}
            steps={ONBOARDING_STEPS}
            currentStep="daily_targets"
            customerName="Amina Saif"
            onStartPlan={() => undefined}
        />
    ),
};
