import { useState } from 'react';
import { AdminLayout } from '../../Components/Admin/AdminLayout.jsx';
import { ADMIN_NAV_PATHS } from '../../Components/Admin/AdminSidebar.jsx';
import { consultationMeals } from '../../consultation/consultationMockMeals.js';
import { mealDetailViewFixture } from '../../Components/Molecules/MealDetailView/mealDetailViewFixture';
import MealPlanDetailPage from './MealPlanDetailPage.jsx';

const WIDE = 'mx-auto w-full max-w-[1400px]';

const breakfast = consultationMeals.find((m) => String(m.mealType).toLowerCase() === 'breakfast');
const mainMeal = consultationMeals.find((m) => String(m.mealType).toLowerCase() === 'meal');

/** @param {object} meal */
function withDetail(meal) {
    if (!meal) {
        return null;
    }

    return {
        ...meal,
        detailView: { ...mealDetailViewFixture, imageUrl: meal.imageUrl, imageAlt: meal.title },
    };
}

const STORY_DAYS = [
    {
        dayNumber: 1,
        label: 'Sunday',
        categories: {
            breakfasts: [withDetail(breakfast)].filter(Boolean),
            meals: [withDetail(mainMeal)].filter(Boolean),
            sideSalads: [],
            desserts: [],
            soup: [],
        },
    },
    {
        dayNumber: 2,
        label: 'Monday',
        categories: {
            breakfasts: [withDetail(breakfast)].filter(Boolean),
            meals: [withDetail(mainMeal)].filter(Boolean),
            sideSalads: [],
            desserts: [],
            soup: [],
        },
    },
];

const STORY_MEAL_PLAN = {
    id: 42,
    name: 'Full Craft — Demo',
    description: 'Seven-day balanced craft plan for Storybook.',
    goal: 'Balanced anti-inflammatory',
    publishedStartsOn: '2026-10-18',
    publishedEndsOn: '2026-10-24',
};

function MealPlanDetailStoryShell({ children }) {
    const [activePath, setActivePath] = useState(ADMIN_NAV_PATHS.mealPlans);
    return (
        <AdminLayout
            pageTitle="Meal plan detail"
            activePath={activePath}
            onNavigate={setActivePath}
            showSearch={false}
            contentWrapperClassName={WIDE}
        >
            {children}
        </AdminLayout>
    );
}

export default {
    title: 'Design System/06. Pages/Admin/MealPlanDetail',
    component: MealPlanDetailPage,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Admin meal plan editor — tier picker, day carousel, choose-your-meals deck, and publish range.',
            },
        },
    },
};

export const Default = {
    name: 'Plan detail',
    render: () => (
        <MealPlanDetailStoryShell>
            <MealPlanDetailPage
                mealPlan={STORY_MEAL_PLAN}
                days={STORY_DAYS}
                planTiers={[1250, 1500, 1800, 2000]}
                defaultPlanTier={1500}
                tierPreviewUrl=""
                libraryUrl="/admin/meal-plan-library"
                dietProtocol="balanced"
            />
        </MealPlanDetailStoryShell>
    ),
};