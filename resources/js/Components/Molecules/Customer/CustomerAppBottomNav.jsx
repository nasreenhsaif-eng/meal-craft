import { Link } from '@inertiajs/react';
import { IconHome, IconMeals, IconProfile, IconShop, IconTools } from '../../Atoms/SvgIcons.jsx';

/**
 * @typedef {'home' | 'meals' | 'shop' | 'tools' | 'profile'} CustomerAppTab
 */

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
                            : 'text-[#6B7280] hover:bg-surface-soft hover:text-[#374151]',
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
