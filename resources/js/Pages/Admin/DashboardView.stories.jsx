import { useState } from 'react';
import { AdminLayout } from '../../Components/Admin/AdminLayout.jsx';
import { ADMIN_NAV_PATHS } from '../../Components/Admin/AdminSidebar.jsx';
import { AdminDashboardInner } from './Dashboard.jsx';

export default {
    title: 'MealCraft/Pages/Admin/DashboardView',
    component: AdminDashboardInner,
    parameters: { layout: 'fullscreen' },
};

export const Default = {
    render: function Render() {
        const [activePath, setActivePath] = useState(ADMIN_NAV_PATHS.dashboard);
        return (
            <AdminLayout pageTitle="Dashboard" activePath={activePath} onNavigate={setActivePath} showSearch={false}>
                <AdminDashboardInner adminName="Amina Saif" adminEmail="amina@mealcrafthq.test" />
            </AdminLayout>
        );
    },
};
