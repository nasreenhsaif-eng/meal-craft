import { Link } from '@inertiajs/react';

/**
 * @typedef {'home' | 'meals' | 'shop' | 'tools' | 'profile'} CustomerAppTab
 */

/**
 * @param {{ className?: string }} props
 */
function IconHome({ className = '' }) {
    return (
        <svg className={className} width={22} height={22} viewBox="0 0 24 24" fill="none" aria-hidden>
            <path
                d="M4 11.5 12 4l8 7.5V20a2 2 0 0 1-2 2h-4v-6H10v6H6a2 2 0 0 1-2-2v-8.5Z"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinejoin="round"
            />
        </svg>
    );
}

/**
 * @param {{ className?: string }} props
 */
function IconMeals({ className = '' }) {
    return (
        <svg className={className} width={22} height={22} viewBox="0 0 24 24" fill="none" aria-hidden>
            <path
                d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2M7 2v20M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h2l1 5M15 15h4"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

/**
 * @param {{ className?: string }} props
 */
function IconShop({ className = '' }) {
    return (
        <svg className={className} width={22} height={22} viewBox="0 0 24 24" fill="none" aria-hidden>
            <path
                d="M6 7h12l-1 13H7L6 7ZM9 7V5a3 3 0 0 1 6 0v2"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

/**
 * @param {{ className?: string }} props
 */
function IconTools({ className = '' }) {
    return (
        <svg className={className} width={22} height={22} viewBox="0 0 24 24" fill="none" aria-hidden>
            <path
                d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18v3h3l6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.1-2.1 2.6-2.4Z"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

/**
 * @param {{ className?: string }} props
 */
function IconProfile({ className = '' }) {
    return (
        <svg className={className} width={22} height={22} viewBox="0 0 24 24" fill="none" aria-hidden>
            <path
                d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

const TAB_ICONS = {
    home: IconHome,
    meals: IconMeals,
    shop: IconShop,
    tools: IconTools,
    profile: IconProfile,
};

/**
 * Persistent customer app footer tabs: Home / Meal plan / Shop / Tools / Profile.
 *
 * @param {object} props
 * @param {CustomerAppTab | null} [props.activeTab]
 * @param {string} [props.homeUrl]
 * @param {string} [props.mealsUrl]
 * @param {string} [props.shopUrl]
 * @param {string} [props.toolsUrl]
 * @param {string} [props.profileUrl]
 * @param {string} [props.className]
 * @param {boolean} [props.navigationDisabled] Decorative tabs (landing preview) — no Inertia links.
 */
export default function CustomerAppBottomNav({
    activeTab = null,
    homeUrl = '/app',
    mealsUrl = '/app/meal-plan',
    shopUrl = '/app/shop',
    toolsUrl = '/app/tools',
    profileUrl = '/app/profile',
    className = '',
    navigationDisabled = false,
}) {
    const items = [
        { id: /** @type {CustomerAppTab} */ ('home'), label: 'Home', href: homeUrl },
        { id: /** @type {CustomerAppTab} */ ('meals'), label: 'Meal plan', href: mealsUrl },
        { id: /** @type {CustomerAppTab} */ ('shop'), label: 'Shop', href: shopUrl },
        { id: /** @type {CustomerAppTab} */ ('tools'), label: 'Tools', href: toolsUrl },
        { id: /** @type {CustomerAppTab} */ ('profile'), label: 'Profile', href: profileUrl },
    ];

    return (
        <nav
            className={[
                'absolute inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white/95 backdrop-blur-sm',
                'pb-[max(0.5rem,env(safe-area-inset-bottom))]',
                className,
            ]
                .join(' ')
                .trim()}
            aria-label="Customer app"
        >
            <ul className="mx-auto flex w-full items-stretch justify-between px-0.5 pt-1 sm:px-1">
                {items.map((item) => {
                    const isActive = item.id === activeTab;
                    const Icon = TAB_ICONS[item.id];
                    const tabClassName = [
                        'flex min-h-[3.5rem] flex-col items-center justify-center gap-0.5 rounded-[12px] px-0.5 py-1.5',
                        'font-montserrat text-[9px] font-bold uppercase tracking-[0.04em] sm:text-[10px] sm:tracking-[0.06em]',
                        'transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44]/35 focus-visible:ring-offset-2',
                        isActive
                            ? 'bg-[#5A6B44]/10 text-[#5A6B44]'
                            : 'text-[#6B7280] hover:bg-[#F8F9F6] hover:text-[#374151]',
                    ]
                        .join(' ')
                        .trim();

                    return (
                        <li key={item.id} className="min-w-0 flex-1">
                            {navigationDisabled ? (
                                <span
                                    aria-current={isActive ? 'page' : undefined}
                                    className={tabClassName}
                                >
                                    <Icon className="shrink-0" />
                                    <span>{item.label}</span>
                                </span>
                            ) : (
                                <Link
                                    href={item.href}
                                    aria-current={isActive ? 'page' : undefined}
                                    className={tabClassName}
                                >
                                    <Icon className="shrink-0" />
                                    <span>{item.label}</span>
                                </Link>
                            )}
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
