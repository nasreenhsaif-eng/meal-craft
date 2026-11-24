import { useState } from 'react';
import { AdminLayout } from './AdminLayout.jsx';
import { ADMIN_NAV_PATHS } from './AdminSidebar.jsx';

export default {
    title: 'Design System/05. Templates/AdminLayout',
    component: AdminLayout,
    parameters: { layout: 'fullscreen' },
};

export const Shell = {
    render: function Render() {
        const [activePath, setActivePath] = useState(ADMIN_NAV_PATHS.dashboard);
        return (
            <AdminLayout
                pageTitle="Admin shell"
                activePath={activePath}
                onNavigate={setActivePath}
                searchLabel="Search"
            >
                <div className="rounded-[16px] border border-gray-200 bg-white p-6">
                    <p className="font-body text-sm text-body">AdminLayout shell content (Storybook only).</p>
                </div>
            </AdminLayout>
        );
    },
};
