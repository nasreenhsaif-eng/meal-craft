import { DailyTargetsSummaryInner } from './DailyTargetsSummary.jsx';

const ONBOARDING_STEPS_FEMALE = [
    { value: 'welcome', label: 'Welcome' },
    { value: 'gender', label: 'Gender' },
    { value: 'period_tracking', label: 'Track your period' },
    { value: 'birthday', label: 'Birthday' },
    { value: 'height', label: 'Height' },
    { value: 'weight', label: 'Weight' },
    { value: 'target_weight', label: 'Target weight' },
    { value: 'activity', label: 'Activity' },
    { value: 'macros', label: 'Macro split' },
    { value: 'meals', label: 'Choose meals' },
    { value: 'review', label: 'Review' },
];

const DEMO_PROFILE = {
    sex: 'female',
    age: 32,
    weight_kg: 68,
    height_cm: 165,
    activity_level: 'moderate',
    goal: 'maintain',
    macro_split_style: 'high_protein',
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
            profile={DEMO_PROFILE}
            steps={ONBOARDING_STEPS_FEMALE}
            currentStep="review"
            customerName="Amina Saif"
            onStartPlan={() => undefined}
        />
    ),
};

export const MaleMaintainBalanced = {
    name: 'Male — balanced split',
    render: () => (
        <DailyTargetsSummaryInner
            profile={{
                sex: 'male',
                age: 35,
                weight_kg: 80,
                height_cm: 178,
                activity_level: 'light',
                goal: 'maintain',
                macro_split_style: 'balanced',
            }}
            steps={ONBOARDING_STEPS_FEMALE.filter((step) => step.value !== 'period_tracking')}
            currentStep="review"
            customerName="James Okonkwo"
            onStartPlan={() => undefined}
        />
    ),
};

export const FemaleWeightLoss = {
    name: 'Female — weight loss',
    render: () => (
        <DailyTargetsSummaryInner
            profile={{
                sex: 'female',
                age: 28,
                weight_kg: 72,
                height_cm: 168,
                target_weight_kg: 65,
                activity_level: 'active',
                macro_split_style: 'high_protein',
            }}
            steps={ONBOARDING_STEPS_FEMALE}
            currentStep="review"
            customerName="Amina Saif"
            onStartPlan={() => undefined}
        />
    ),
};

export const WithExplicitCalorieOverride = {
    name: 'With explicit calorie override',
    render: () => (
        <DailyTargetsSummaryInner
            profile={{
                ...DEMO_PROFILE,
                daily_calorie_target: 1929,
                protein_percentage: 40,
                carb_percentage: 35,
                fat_percentage: 25,
            }}
            steps={ONBOARDING_STEPS_FEMALE}
            currentStep="review"
            customerName="Amina Saif"
            onStartPlan={() => undefined}
        />
    ),
};
