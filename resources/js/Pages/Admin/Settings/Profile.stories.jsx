import { useState } from 'react';
import { AdminLayout } from '../../../Components/Admin/AdminLayout.jsx';
import { AdminSettingsProfileInner } from './Profile.jsx';

const SETTINGS_CONTENT_WRAPPER = 'mx-auto w-full max-w-[1400px]';

function SettingsStoryShell({ children }) {
    const [activePath, setActivePath] = useState('');

    return (
        <AdminLayout
            pageTitle="Settings"
            activePath={activePath}
            onNavigate={setActivePath}
            showSearch={false}
            hidePageTitle
            contentWrapperClassName={SETTINGS_CONTENT_WRAPPER}
            userAvatar={
                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#556C37] text-xs font-bold text-white">
                    NS
                </span>
            }
        >
            {children}
        </AdminLayout>
    );
}

export default {
    title: 'Design System/06. Pages/Admin/Settings/Profile',
    component: AdminSettingsProfileInner,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component: 'Admin account profile tab — name and email inside the settings shell.',
            },
        },
    },
};

export const Default = {
    name: 'Profile',
    render: () => (
        <SettingsStoryShell>
            <AdminSettingsProfileInner name="Nasreen Saif" email="nasreen@mealcrafthq.test" />
        </SettingsStoryShell>
    ),
};