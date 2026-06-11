/** Same page structure as production: sticky title → Create + CSV → full-width search → plan cards. */
import { useState } from 'react';
import { AdminLayout } from '../../Components/Admin/AdminLayout.jsx';
import { ADMIN_NAV_PATHS } from '../../Components/Admin/AdminSidebar.jsx';
import { MealPlanLibraryPageContent } from './MealPlanLibraryPage.jsx';

const WIDE = 'mx-auto w-full max-w-[1400px]';

function MealPlanLibraryStoryShell({ children }) {
    const [activePath, setActivePath] = useState(ADMIN_NAV_PATHS.mealPlans);
    return (
        <AdminLayout
            pageTitle="Meal Plan Library"
            activePath={activePath}
            onNavigate={setActivePath}
            showSearch={false}
            hidePageTitle={false}
            contentWrapperClassName={WIDE}
        >
            {children}
        </AdminLayout>
    );
}

export default {
    title: 'MealCraft/Pages/Admin/MealPlanLibrary',
    component: MealPlanLibraryPageContent,
    parameters: {
        layout: 'fullscreen',
        a11y: { config: { rules: [{ id: 'color-contrast', enabled: true }] } },
        docs: {
            description: {
                component:
                    'Vertical order: sticky page title → Create + CSV → full-width search (name, category, tags) → plan cards.',
            },
        },
    },
};

const STORY_SCHEDULER_MEALS = [
    { id: 1, name: 'Berry oats bowl', category: 'Breakfast' },
    { id: 2, name: 'Avocado toast', category: 'Breakfast' },
    { id: 3, name: 'Grilled salmon plate', category: 'Meal' },
    { id: 4, name: 'Tomato basil soup', category: 'Soup' },
    { id: 5, name: 'Garden side salad', category: 'Side Salad' },
    { id: 6, name: 'Dark chocolate mousse', category: 'Dessert' },
];

export const Default = {
    render: () => (
        <MealPlanLibraryStoryShell>
            <MealPlanLibraryPageContent schedulerMeals={STORY_SCHEDULER_MEALS} />
        </MealPlanLibraryStoryShell>
    ),
};
