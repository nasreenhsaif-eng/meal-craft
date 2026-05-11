import { MealLibraryPageContent } from './MealLibraryPage.jsx';

export default {
    title: 'MealCraft/Pages/Admin/MealLibrary',
    component: MealLibraryPageContent,
    parameters: {
        layout: 'fullscreen',
        a11y: { config: { rules: [{ id: 'color-contrast', enabled: true }] } },
    },
};

export const Default = {
    render: () => <MealLibraryPageContent />,
};
