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
    },
};

export const Default = {
    render: () => (
        <MealPlanLibraryStoryShell>
            <MealPlanLibraryPageContent />
        </MealPlanLibraryStoryShell>
    ),
};
