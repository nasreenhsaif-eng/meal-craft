import { usePage } from '@inertiajs/react';
import AuthenticatedLayout from './AuthenticatedLayout.jsx';
import { ADMIN_NAV_PATHS } from '../Components/Admin/AdminSidebar.jsx';

const WIDE = 'mx-auto w-full max-w-[1400px]';

/** @type {Record<string, { pageTitle: string; activePath: string; showSearch: boolean; contentWrapperClassName?: string }>} */
const SHELL_BY_COMPONENT = {
    'Admin/Dashboard': {
        pageTitle: 'Dashboard',
        activePath: ADMIN_NAV_PATHS.dashboard,
        showSearch: false,
    },
    'Admin/IngredientsLibrary': {
        pageTitle: 'Ingredient Library',
        activePath: ADMIN_NAV_PATHS.ingredientDb,
        showSearch: true,
        contentWrapperClassName: WIDE,
    },
    'Admin/MealLibrary': {
        pageTitle: 'Meal Library',
        activePath: ADMIN_NAV_PATHS.mealHub,
        showSearch: true,
        contentWrapperClassName: WIDE,
    },
    'Admin/MealPlanLibrary': {
        pageTitle: 'Meal Plan Library',
        activePath: ADMIN_NAV_PATHS.mealPlans,
        showSearch: true,
        contentWrapperClassName: WIDE,
    },
};

/**
 * Single persistent Inertia layout for all admin pages so AdminSidebar stays mounted when navigating between admin routes.
 *
 * @param {{ children: import('react').ReactNode }} props
 */
export default function AdminInertiaShell({ children }) {
    const { component, auth } = usePage();
    const c = SHELL_BY_COMPONENT[component] ?? {
        pageTitle: 'Meal Craft Admin',
        activePath: '',
        showSearch: true,
    };

    return (
        <AuthenticatedLayout
            pageTitle={c.pageTitle}
            activePath={c.activePath}
            showSearch={c.showSearch}
            contentWrapperClassName={c.contentWrapperClassName}
            user={auth?.user}
        >
            {children}
        </AuthenticatedLayout>
    );
}
