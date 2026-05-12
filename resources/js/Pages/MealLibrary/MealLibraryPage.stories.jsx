import { useState } from 'react';
import { AdminLayout } from '../../Components/Admin/AdminLayout.jsx';
import { ADMIN_NAV_PATHS } from '../../Components/Admin/AdminSidebar.jsx';
import { MealLibraryPageContent } from './MealLibraryPage.jsx';

const WIDE = 'mx-auto w-full max-w-[1400px]';

const sampleIngredientProfiles = [
    {
        name: 'Rice',
        calories: 130,
        protein: 2.7,
        carbs: 28,
        fat: 0.3,
        b6: 0,
        b9_folate: 0,
        b12: 0,
        iron: 0,
        magnesium: 0,
        micronutrients: {},
    },
];

const sampleMeals = [
    {
        id: '1',
        title: 'Rice Bowl',
        imageUrl: '',
        mealType: 'Meal',
        category: 'Meal',
        prepMinutes: 0,
        macros: { calories: 260, protein: 5.4, carbs: 56, fat: 0.6 },
        tags: [{ label: 'Meal', type: 'category' }],
        nutrientHighlights: [],
    },
];

function MealLibraryStoryShell({ children }) {
    const [activePath, setActivePath] = useState(ADMIN_NAV_PATHS.mealHub);
    return (
        <AdminLayout
            pageTitle="Meal Library"
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
    title: 'MealCraft/Pages/Admin/MealLibrary',
    component: MealLibraryPageContent,
    parameters: {
        layout: 'fullscreen',
        a11y: { config: { rules: [{ id: 'color-contrast', enabled: true }] } },
    },
};

export const Default = {
    render: () => (
        <MealLibraryStoryShell>
            <MealLibraryPageContent
                meals={sampleMeals}
                ingredientProfiles={sampleIngredientProfiles}
                mealCategoryOptions={['Breakfast', 'Meal', 'Side Salad', 'Soup', 'Dessert']}
            />
        </MealLibraryStoryShell>
    ),
};
