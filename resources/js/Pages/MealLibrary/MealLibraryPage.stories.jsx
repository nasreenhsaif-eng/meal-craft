/** Same page structure as production: sticky title → Create + CSV → full-width search → meal grid. */
import { useState } from 'react';
import { AdminLayout } from '../../Components/Admin/AdminLayout.jsx';
import { ADMIN_NAV_PATHS } from '../../Components/Admin/AdminSidebar.jsx';
import { MealLibraryPageContent } from './MealLibraryPage.jsx';

import { mealDetailViewFixture } from '../../Components/Molecules/MealDetailView/mealDetailViewFixture';

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
        detailView: mealDetailViewFixture,
    },
    {
        id: '2',
        title: 'Greek Yogurt Parfait',
        imageUrl: '',
        mealType: 'Breakfast',
        category: 'Breakfast',
        prepMinutes: 5,
        macros: { calories: 320, protein: 18, carbs: 38, fat: 10 },
        tags: [{ label: 'High Protein', type: 'dietary' }],
        nutrientHighlights: ['B12'],
        detailView: mealDetailViewFixture,
    },
    {
        id: '3',
        title: 'Lentil Soup',
        imageUrl: '',
        mealType: 'Soup',
        category: 'Soup',
        prepMinutes: 40,
        macros: { calories: 210, protein: 12, carbs: 34, fat: 3 },
        tags: [{ label: 'Vegan', type: 'dietary' }],
        nutrientHighlights: ['Iron', 'Folate'],
        detailView: mealDetailViewFixture,
    },
];

export function MealLibraryStoryShell({ children }) {
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
        docs: {
            description: {
                component:
                    'Vertical order: sticky page title → Create meal + CSV → search + grid/list toggle → meal cards or list table. Meals save to the meal library only; use Ingredient Library → Create Base Ingredient for prepared components.',
            },
        },
    },
    argTypes: {
        initialViewMode: {
            control: 'radio',
            options: ['grid', 'list'],
            description: 'Starting view: grid cards (no row checkboxes) or list table with bulk selection.',
        },
    },
    args: {
        initialViewMode: 'grid',
    },
};

export const Default = {
    render: (args) => (
        <div className="min-h-screen w-full bg-gray-50 p-8">
            <MealLibraryStoryShell>
                <MealLibraryPageContent {...args} meals={sampleMeals} ingredientProfiles={sampleIngredientProfiles} />
            </MealLibraryStoryShell>
        </div>
    ),
};
