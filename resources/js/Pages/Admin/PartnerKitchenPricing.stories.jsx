import { useState } from 'react';
import { AdminLayout } from '../../Components/Admin/AdminLayout.jsx';
import { ADMIN_NAV_PATHS } from '../../Components/Admin/AdminSidebar.jsx';
import PartnerKitchenPricing from './PartnerKitchenPricing.jsx';

const WIDE = 'mx-auto w-full max-w-[1400px]';

const STORY_PLANS = [
    { key: 'full', name: 'Full Craft', calorieTiers: [1200, 1500, 2000] },
    { key: 'day', name: 'Day Craft', calorieTiers: [850, 1200, 1400] },
];

const STORY_PRICES = [
    {
        id: 1,
        planKey: 'full',
        calorieTier: 1200,
        daysCount: 20,
        baseTotalPrice: 220,
        discountedTotalPrice: null,
    },
    {
        id: 2,
        planKey: 'full',
        calorieTier: 1200,
        daysCount: 24,
        baseTotalPrice: 255,
        discountedTotalPrice: 240,
    },
    {
        id: 3,
        planKey: 'full',
        calorieTier: 1500,
        daysCount: 28,
        baseTotalPrice: 320,
        discountedTotalPrice: null,
    },
    {
        id: 4,
        planKey: 'full',
        calorieTier: 2000,
        daysCount: 28,
        baseTotalPrice: 380,
        discountedTotalPrice: 350,
    },
];

function PartnerKitchenPricingStoryShell({ children }) {
    const [activePath, setActivePath] = useState(ADMIN_NAV_PATHS.pricing);
    return (
        <AdminLayout
            pageTitle="Partner kitchen pricing"
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
    title: 'Design System/06. Pages/Admin/PartnerKitchenPricing',
    component: PartnerKitchenPricing,
    parameters: { layout: 'fullscreen' },
};

export const Default = {
    name: 'Pricing matrix',
    render: () => (
        <PartnerKitchenPricingStoryShell>
            <PartnerKitchenPricing plans={STORY_PLANS} prices={STORY_PRICES} currency="BHD" />
        </PartnerKitchenPricingStoryShell>
    ),
};