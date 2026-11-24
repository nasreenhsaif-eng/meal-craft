import { CookItYourselfInner } from './CookItYourself.jsx';
import { consultationMeals } from '../../consultation/consultationMockMeals.js';
import { withOnboardingMobileFrame } from '../Onboarding/onboardingStoryDecorators.jsx';

const breakfast = consultationMeals.find((m) => String(m.mealType).toLowerCase() === 'breakfast');
const mainMeal = consultationMeals.find((m) => String(m.mealType).toLowerCase() === 'meal');

const STORY_CRAFT_PLAN = {
    craftKey: 'full',
    craftTitle: 'Full Craft',
    days: [
        {
            dayNumber: 1,
            label: 'Sunday',
            dateLabel: '18 Oct',
            categories: {
                breakfasts: breakfast ? [breakfast] : [],
                meals: mainMeal ? [mainMeal] : [],
                sideSalads: [],
                desserts: [],
                soup: [],
            },
        },
    ],
};

export default {
    title: 'Design System/06. Pages/App/CookItYourself',
    component: CookItYourselfInner,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component: 'DIY cook flow — day list, nutritional tabs, and recipe detail with favourites / shopping list.',
            },
        },
    },
    decorators: withOnboardingMobileFrame,
};

export const SingleDay = {
    name: 'Single day',
    render: () => (
        <CookItYourselfInner
            customerName="Nasreen"
            craftPlan={STORY_CRAFT_PLAN}
            sex="female"
            activityLevel="lightly_active"
            dailyCalorieTarget={1700}
        />
    ),
};