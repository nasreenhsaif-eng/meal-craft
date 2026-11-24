import { useState } from 'react';
import { AdminLayout } from '../../../Components/Admin/AdminLayout.jsx';
import { AdminSettingsAppearanceInner } from './Appearance.jsx';

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
    title: 'Design System/06. Pages/Admin/Settings/Appearance',
    component: AdminSettingsAppearanceInner,
    parameters: { layout: 'fullscreen' },
};

export const Default = {
    name: 'Appearance',
    render: () => (
        <SettingsStoryShell>
            <AdminSettingsAppearanceInner />
        </SettingsStoryShell>
    ),
};