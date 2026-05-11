import { MealPlanLibraryPageContent } from './MealPlanLibraryPage.jsx';

export default {
    title: 'MealCraft/Pages/Admin/MealPlanLibrary',
    component: MealPlanLibraryPageContent,
    parameters: {
        layout: 'fullscreen',
        a11y: { config: { rules: [{ id: 'color-contrast', enabled: true }] } },
    },
};

export const Default = {
    render: () => <MealPlanLibraryPageContent />,
};

