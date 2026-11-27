import { useState } from 'react';
import { AdminLayout } from '../../Components/Admin/AdminLayout.jsx';
import { ADMIN_NAV_PATHS } from '../../Components/Admin/AdminSidebar.jsx';
import KitchenLogistics from './KitchenLogistics.jsx';

const WIDE = 'mx-auto w-full max-w-[1400px]';

function KitchenLogisticsStoryShell({ children }) {
    const [activePath, setActivePath] = useState(ADMIN_NAV_PATHS.kitchenLogistics);
    return (
        <AdminLayout
            pageTitle="Kitchen production"
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
    title: 'Design System/06. Pages/Admin/KitchenLogistics',
    component: KitchenLogistics,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Live kitchen logistics page — loads daily sheet via API (Storybook shows loading / error without a backend).',
            },
        },
    },
};

export const Default = {
    name: 'Kitchen production',
    render: () => (
        <KitchenLogisticsStoryShell>
            <KitchenLogistics
                initialProductionDate="2026-11-15"
                kitchenDailySheetUrl="/api/admin/kitchen/daily-sheet"
            />
        </KitchenLogisticsStoryShell>
    ),
};