import { AdminSettingsLayout } from '../../../Components/Admin/AdminSettingsLayout.jsx';
import AdminInertiaShell from '../../../Layouts/AdminInertiaShell.jsx';

/**
 * Appearance settings markup (Storybook / Inertia).
 *
 * @param {{ onNavigate?: (section: 'profile' | 'security' | 'appearance') => void }} props
 */
export function AdminSettingsAppearanceInner({ onNavigate }) {
    return (
        <AdminSettingsLayout
            active="appearance"
            heading="Appearance"
            subheading="Update the appearance settings for your account"
            onNavigate={onNavigate}
        >
            <p className="font-montserrat text-sm leading-relaxed text-[#555555]">
                The admin portal currently uses the light theme. Dark mode and system appearance preferences are
                available in the legacy kitchen settings for Livewire pages.
            </p>
        </AdminSettingsLayout>
    );
}

export default function Appearance() {
    return <AdminSettingsAppearanceInner />;
}

Appearance.layout = (page) => <AdminInertiaShell>{page}</AdminInertiaShell>;
