import { useState } from 'react';
import { AdminLayout } from '../../Components/Admin/AdminLayout.jsx';
import { AdminSettingsAppearanceInner } from './Settings/Appearance.jsx';
import { AdminSettingsProfileInner } from './Settings/Profile.jsx';
import { AdminSettingsSecurityInner } from './Settings/Security.jsx';

const SETTINGS_CONTENT_WRAPPER = 'mx-auto w-full max-w-[1400px]';

export default {
    title: 'MealCraft/Pages/Admin/SettingsView',
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Admin account settings inside the Inertia admin shell: Profile, Security, and Appearance tabs.',
            },
        },
    },
};

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

export const Profile = {
    name: 'Profile',
    render: function Render() {
        return (
            <SettingsStoryShell>
                <AdminSettingsProfileInner emailVerified={false} />
            </SettingsStoryShell>
        );
    },
};

export const ProfileVerified = {
    name: 'Profile (verified email)',
    render: function Render() {
        return (
            <SettingsStoryShell>
                <AdminSettingsProfileInner
                    name="Nasreen Saif"
                    email="nasreen@mealcrafthq.test"
                    emailVerified
                />
            </SettingsStoryShell>
        );
    },
};

export const Security = {
    name: 'Security',
    render: function Render() {
        return (
            <SettingsStoryShell>
                <AdminSettingsSecurityInner />
            </SettingsStoryShell>
        );
    },
};

export const Appearance = {
    name: 'Appearance',
    render: function Render() {
        return (
            <SettingsStoryShell>
                <AdminSettingsAppearanceInner />
            </SettingsStoryShell>
        );
    },
};

export const InteractiveTabs = {
    name: 'Interactive tabs',
    render: function Render() {
        const [activeSection, setActiveSection] = useState('profile');

        return (
            <SettingsStoryShell>
                {activeSection === 'profile' ? (
                    <AdminSettingsProfileInner emailVerified={false} onNavigate={setActiveSection} />
                ) : null}
                {activeSection === 'security' ? (
                    <AdminSettingsSecurityInner onNavigate={setActiveSection} />
                ) : null}
                {activeSection === 'appearance' ? (
                    <AdminSettingsAppearanceInner onNavigate={setActiveSection} />
                ) : null}
            </SettingsStoryShell>
        );
    },
};
