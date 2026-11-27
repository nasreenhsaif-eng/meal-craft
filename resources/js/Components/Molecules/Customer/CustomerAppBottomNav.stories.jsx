import CustomerAppBottomNav from './CustomerAppBottomNav.jsx';

export default {
    title: 'Design System/03. Molecules/Customer/CustomerAppBottomNav',
    component: CustomerAppBottomNav,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Persistent footer tabs for completed-customer app pages and checkout (Home / Favourites / Shop / Tools / Profile). Meal plan lives under Tools. Hidden on receive mode and onboarding.',
            },
        },
    },
};

/**
 * @param {'home' | 'favourites' | 'shop' | 'tools' | 'profile'} activeTab
 */
function NavPreview({ activeTab }) {
    return (
        <div className="relative min-h-[12rem] bg-surface-soft">
            <p className="px-4 pt-6 font-body text-sm text-body">Page content sits above the bar.</p>
            <CustomerAppBottomNav activeTab={activeTab} />
        </div>
    );
}

export const HomeActive = {
    name: 'Home active',
    render: () => <NavPreview activeTab="home" />,
};

export const FavouritesActive = {
    name: 'Favourites active',
    render: () => <NavPreview activeTab="favourites" />,
};

export const ShopActive = {
    name: 'Shop active',
    render: () => <NavPreview activeTab="shop" />,
};

export const ToolsActive = {
    name: 'Tools active',
    render: () => <NavPreview activeTab="tools" />,
};

export const ProfileActive = {
    name: 'Profile active',
    render: () => <NavPreview activeTab="profile" />,
};
