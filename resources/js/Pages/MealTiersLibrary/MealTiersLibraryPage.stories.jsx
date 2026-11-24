import { useState } from 'react';
import { AdminLayout } from '../../Components/Admin/AdminLayout.jsx';
import { ADMIN_NAV_PATHS } from '../../Components/Admin/AdminSidebar.jsx';
import { mealDetailViewFixture } from '../../Components/Molecules/MealDetailView/mealDetailViewFixture';
import MealTiersLibraryPage from './MealTiersLibraryPage.jsx';

const WIDE = 'mx-auto w-full max-w-[1400px]';

const sampleBrowseTabs = [
    { id: 'all', label: 'All' },
    { id: 'breakfast', label: 'Breakfast' },
    { id: 'chicken', label: 'Chicken' },
];

const sampleMeals = [
    {
        id: 'mt-1',
        title: 'Berry overnight oats',
        category: 'Breakfast',
        browseTab: 'breakfast',
        description: 'Tiered breakfast base.',
        calorieTiers: [
            {
                calorie_tier: 400,
                ingredients: [{ name: 'Rolled oats', ingredient_id: 1, amount_grams: 60 }],
            },
            {
                calorie_tier: 500,
                ingredients: [{ name: 'Rolled oats', ingredient_id: 1, amount_grams: 75 }],
            },
        ],
        tabValues: [400, 500],
        detailView: mealDetailViewFixture,
    },
    {
        id: 'mt-2',
        title: 'Grilled chicken plate',
        category: 'Meal',
        browseTab: 'chicken',
        calorieTiers: [
            {
                calorie_tier: 550,
                ingredients: [{ name: 'Chicken breast', ingredient_id: 2, amount_grams: 120 }],
            },
        ],
        tabValues: [550, 600, 700],
        detailView: mealDetailViewFixture,
    },
];

function MealTiersLibraryStoryShell({ children }) {
    const [activePath, setActivePath] = useState(ADMIN_NAV_PATHS.mealTiers);
    return (
        <AdminLayout
            pageTitle="Meal Tiers Library"
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
    title: 'Design System/06. Pages/Admin/MealTiersLibrary',
    component: MealTiersLibraryPage,
    parameters: { layout: 'fullscreen' },
};

export const Default = {
    name: 'Tier library',
    render: () => (
        <MealTiersLibraryStoryShell>
            <MealTiersLibraryPage
                meals={sampleMeals}
                ingredientProfiles={[]}
                categoryOptions={['Breakfast', 'Meal', 'Main Salad']}
                browseTabs={sampleBrowseTabs}
                proteinStructures={{
                    breakfast_calorie_tiers: [400, 500],
                    main_calorie_tiers: [550, 600, 700],
                }}
            />
        </MealTiersLibraryStoryShell>
    ),
};