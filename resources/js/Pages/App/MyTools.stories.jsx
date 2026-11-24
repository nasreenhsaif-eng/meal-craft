import { calculateDailyTargets } from '../../meal-craft/dailyTargetsCalculator.js';
import { MyToolsInner } from './MyTools.jsx';
import { withOnboardingMobileFrame } from '../Onboarding/onboardingStoryDecorators.jsx';

const DEMO_PROFILE = {
    sex: 'female',
    age: 32,
    weight_kg: 68,
    height_cm: 165,
    activity_level: 'lightly_active',
    target_weight_kg: 65,
    diet_protocol: 'balanced',
};

const BODY_PLAN = {
    sexLabel: 'Female',
    dietProtocolLabel: 'Balanced',
    dateOfBirth: '1994-03-15',
    age: 32,
    heightCm: 165,
    weightKg: 68,
    targetWeightKg: 65,
    activityLevelLabel: 'Lightly active',
    dailyCaloriesMin: 1575,
    dailyCaloriesMax: 1825,
    macroSplitStyle: 'balanced',
    foodFilters: ['Dairy free'],
};

export default {
    title: 'Design System/06. Pages/App/MyTools',
    component: MyToolsInner,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component: 'My Tools — saved recipes, shopping list, daily targets, and body & plan tabs.',
            },
        },
    },
    decorators: withOnboardingMobileFrame,
};

export const Default = {
    name: 'Saved tab',
    render: () => (
        <MyToolsInner
            customerName="Nasreen"
            craftPlan={{ days: [] }}
            dailyTargets={calculateDailyTargets(DEMO_PROFILE)}
            bodyPlan={BODY_PLAN}
        />
    ),
};

export const DailyTargets = {
    name: 'Daily targets tab',
    render: () => (
        <MyToolsInner
            customerName="Nasreen"
            craftPlan={{ days: [] }}
            dailyTargets={calculateDailyTargets(DEMO_PROFILE)}
            bodyPlan={BODY_PLAN}
        />
    ),
};