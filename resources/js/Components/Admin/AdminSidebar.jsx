import MealCraftLogo from '../Atoms/Logo/MealCraftLogo.jsx';
import NavButton from '../Atoms/Button/NavButton.jsx';

/** Accessible brand green for active nav (WCAG-friendly on white). */
export const ADMIN_NAV_ACTIVE = '#556C37';

/** Stable ids for Storybook / tests and parent `activePath` matching. */
export const ADMIN_NAV_PATHS = {
    dashboard: 'dashboard',
    ingredientDb: 'ingredient-db',
    mealHub: 'meal-hub',
    mealPlans: 'meal-plans',
    kitchenLogistics: 'kitchen-logistics',
    customerProfiles: 'customer-profiles',
    discoveryInsights: 'discovery-insights',
};

/** Live Laravel admin URLs keyed by {@link ADMIN_NAV_PATHS} id (used by sidebar + Inertia shell). */
export const ADMIN_NAV_HREFS = {
    [ADMIN_NAV_PATHS.dashboard]: '/admin/dashboard',
    [ADMIN_NAV_PATHS.ingredientDb]: '/admin/ingredient-library',
    [ADMIN_NAV_PATHS.mealHub]: '/admin/meal-library',
    [ADMIN_NAV_PATHS.mealPlans]: '/admin/meal-plan-library',
    [ADMIN_NAV_PATHS.kitchenLogistics]: '/admin/kitchen-logistics',
    [ADMIN_NAV_PATHS.customerProfiles]: '/admin/customers',
};

const GROUPS = [
    {
        label: 'User management',
        items: [{ path: ADMIN_NAV_PATHS.dashboard, label: 'Dashboard', Icon: null, href: ADMIN_NAV_HREFS[ADMIN_NAV_PATHS.dashboard] }],
    },
    {
        label: 'Kitchen engine',
        items: [
            {
                path: ADMIN_NAV_PATHS.ingredientDb,
                label: 'Ingredient Library',
                Icon: IconDatabase,
                href: ADMIN_NAV_HREFS[ADMIN_NAV_PATHS.ingredientDb],
            },
            {
                path: ADMIN_NAV_PATHS.mealHub,
                label: 'Meal Library',
                Icon: IconMealHub,
                href: ADMIN_NAV_HREFS[ADMIN_NAV_PATHS.mealHub],
            },
            {
                path: ADMIN_NAV_PATHS.mealPlans,
                label: 'Meal Plan Library',
                Icon: IconCalendar,
                href: ADMIN_NAV_HREFS[ADMIN_NAV_PATHS.mealPlans],
            },
            {
                path: ADMIN_NAV_PATHS.kitchenLogistics,
                label: 'Kitchen Production',
                Icon: IconKitchen,
                href: ADMIN_NAV_HREFS[ADMIN_NAV_PATHS.kitchenLogistics],
            },
        ],
    },
    {
        label: 'User intelligence',
        items: [
            { path: ADMIN_NAV_PATHS.customerProfiles, label: 'Customer Profiles', Icon: IconUsers, href: ADMIN_NAV_HREFS[ADMIN_NAV_PATHS.customerProfiles] },
            { path: ADMIN_NAV_PATHS.discoveryInsights, label: 'Discovery Insights', Icon: IconChart },
        ],
    },
];

function IconDatabase({ className }) {
    return (
        <svg className={className} width={20} height={20} viewBox="0 0 24 24" fill="none" aria-hidden>
            <ellipse cx="12" cy="5" rx="9" ry="3" stroke="currentColor" strokeWidth="2" />
            <path d="M3 5v14c0 1.66 4.03 3 9 3s9-1.34 9-3V5" stroke="currentColor" strokeWidth="2" />
            <path d="M3 12c0 1.66 4.03 3 9 3s9-1.34 9-3" stroke="currentColor" strokeWidth="2" />
        </svg>
    );
}

function IconMealHub({ className }) {
    return (
        <svg className={className} width={20} height={20} viewBox="0 0 24 24" fill="none" aria-hidden>
            <path
                d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2M7 2v20M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h2l1 5M15 15h4"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

function IconCalendar({ className }) {
    return (
        <svg className={className} width={20} height={20} viewBox="0 0 24 24" fill="none" aria-hidden>
            <rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" strokeWidth="2" />
            <path d="M16 2v4M8 2v4M3 10h18" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
        </svg>
    );
}

function IconKitchen({ className }) {
    return (
        <svg className={className} width={20} height={20} viewBox="0 0 24 24" fill="none" aria-hidden>
            <path
                d="M4 10h16M6 6h12M8 14h8v8H8z"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

function IconUsers({ className }) {
    return (
        <svg className={className} width={20} height={20} viewBox="0 0 24 24" fill="none" aria-hidden>
            <path
                d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

function IconChart({ className }) {
    return (
        <svg className={className} width={20} height={20} viewBox="0 0 24 24" fill="none" aria-hidden>
            <path d="M3 3v18h18" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
            <path d="M7 16l4-6 3 3 5-8" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

/**
 * @param {object} props
 * @param {string} [props.activePath]
 * @param {(path: string) => void} [props.onNavigate]
 */
export function AdminSidebar({ activePath = '', onNavigate }) {
    return (
        <div className="relative flex h-full flex-col bg-white font-sans">
            <div className="relative z-0 flex shrink-0 items-center border-b border-gray-200 bg-white px-5 py-4">
                <div className="flex min-w-0 items-center gap-3">
                    <div className="shrink-0" aria-hidden>
                        <MealCraftLogo variant="seal-xs" width={40} alt="" />
                    </div>
                    <div className="min-w-0">
                        <div className="relative z-10 font-montserrat text-base font-bold leading-tight tracking-tight text-[#262A22]">
                            Meal Craft
                        </div>
                        <div className="relative z-10 font-montserrat text-[11px] font-bold uppercase tracking-[0.14em] text-[#555555]">
                            Smart Kitchen
                        </div>
                    </div>
                </div>
            </div>

            <nav className="relative z-0 flex-1 overflow-y-auto bg-white px-3 py-4" aria-label="Admin primary">
                {GROUPS.map((group) => (
                    <div key={group.label} className="mb-6 last:mb-0">
                        <p className="relative z-10 mb-2 px-3 font-montserrat text-[11px] font-bold uppercase tracking-[0.14em] text-[#555555]">
                            {group.label}
                        </p>
                        <ul className="m-0 list-none space-y-0.5 p-0">
                            {group.items.map(({ path, label, Icon, href }) => {
                                const active = activePath === path;
                                return (
                                    <li key={path}>
                                        <NavButton
                                            icon={Icon ? <Icon className="text-current" /> : undefined}
                                            label={label}
                                            isActive={active}
                                            href={href}
                                            onClick={() => {
                                                onNavigate?.(path);
                                            }}
                                        />
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                ))}
            </nav>
        </div>
    );
}

export default AdminSidebar;
