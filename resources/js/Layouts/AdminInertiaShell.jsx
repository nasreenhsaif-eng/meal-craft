import { useEffect } from 'react';
import { usePage } from '@inertiajs/react';
import AuthenticatedLayout from './AuthenticatedLayout.jsx';
import { ADMIN_NAV_PATHS } from '../Components/Admin/AdminSidebar.jsx';
import { syncCsrfMetaTag } from '../lib/csrfToken.js';

const WIDE = 'mx-auto w-full max-w-[1400px]';

/** @type {Record<string, { pageTitle: string; activePath: string; showSearch: boolean; hidePageTitle?: boolean; contentWrapperClassName?: string }>} */
const SHELL_BY_COMPONENT = {
    'Admin/Dashboard': {
        pageTitle: 'Dashboard',
        activePath: ADMIN_NAV_PATHS.dashboard,
        showSearch: false,
        hidePageTitle: false,
    },
    'Admin/IngredientsLibrary': {
        pageTitle: 'Ingredients Library',
        activePath: ADMIN_NAV_PATHS.ingredientDb,
        showSearch: false,
        hidePageTitle: false,
        contentWrapperClassName: WIDE,
    },
    'Admin/MealLibrary': {
        pageTitle: 'Meal Library',
        activePath: ADMIN_NAV_PATHS.mealHub,
        showSearch: false,
        hidePageTitle: false,
        contentWrapperClassName: WIDE,
    },
    'Admin/MealPlanLibrary': {
        pageTitle: 'Meal Plan Library',
        activePath: ADMIN_NAV_PATHS.mealPlans,
        showSearch: false,
        hidePageTitle: false,
        contentWrapperClassName: WIDE,
    },
    'Admin/MealPlanDetail': {
        pageTitle: 'Meal Plan Details',
        activePath: ADMIN_NAV_PATHS.mealPlans,
        showSearch: false,
        hidePageTitle: true,
        contentWrapperClassName: WIDE,
    },
    'Admin/CustomerProfiles': {
        pageTitle: 'Customer Profiles',
        activePath: ADMIN_NAV_PATHS.customerProfiles,
        showSearch: false,
        hidePageTitle: true,
        contentWrapperClassName: WIDE,
    },
    'Admin/Settings/Profile': {
        pageTitle: 'Settings',
        activePath: '',
        showSearch: false,
        hidePageTitle: true,
        contentWrapperClassName: WIDE,
    },
    'Admin/Settings/Security': {
        pageTitle: 'Settings',
        activePath: '',
        showSearch: false,
        hidePageTitle: true,
        contentWrapperClassName: WIDE,
    },
    'Admin/Settings/Appearance': {
        pageTitle: 'Settings',
        activePath: '',
        showSearch: false,
        hidePageTitle: true,
        contentWrapperClassName: WIDE,
    },
};

/** Inertia `page.url` is the current path (may omit leading slash in some adapters). */
function isAdminAppPath(url) {
    if (typeof url !== 'string' || url.length === 0) {
        return false;
    }
    const path = url.startsWith('/') ? url : `/${url}`;
    return path === '/admin' || path.startsWith('/admin/');
}

/**
 * Single persistent Inertia layout for all admin pages so AdminSidebar stays mounted when navigating between admin routes.
 *
 * @param {{ children: import('react').ReactNode }} props
 */
export default function AdminInertiaShell({ children }) {
    const { component, url, props: pageProps } = usePage();
    const { auth, csrfToken } = pageProps;
    const csrfTokenString = typeof csrfToken === 'string' ? csrfToken : '';

    useEffect(() => {
        syncCsrfMetaTag(csrfTokenString);
    }, [csrfTokenString]);
    const c = SHELL_BY_COMPONENT[component] ?? {
        pageTitle: 'Meal Craft Admin',
        activePath: '',
        showSearch: false,
        hidePageTitle: false,
    };
    const showSearch = isAdminAppPath(url) ? false : Boolean(c.showSearch);
    const hidePageTitle = Boolean(c.hidePageTitle);

    return (
        <AuthenticatedLayout
            pageTitle={c.pageTitle}
            activePath={c.activePath}
            showSearch={showSearch}
            hidePageTitle={hidePageTitle}
            contentWrapperClassName={c.contentWrapperClassName}
            user={auth?.user}
        >
            {children}
        </AuthenticatedLayout>
    );
}
