import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import Button from '../../../Components/Atoms/Button/Button.jsx';
import TextInput from '../../../Components/Atoms/TextInput/TextInput.jsx';
import { AdminSettingsLayout } from '../../../Components/Admin/AdminSettingsLayout.jsx';
import adminInertiaLayout from '../../../lib/adminInertiaLayout.jsx';

/**
 * Profile settings markup (Storybook / Inertia). Same layout and Tailwind as the live admin page.
 *
 * @param {{
 *   name?: string;
 *   email?: string;
 *   canEditProfile?: boolean;
 *   errors?: Record<string, string>;
 *   processing?: boolean;
 *   onNameChange?: (value: string) => void;
 *   onEmailChange?: (value: string) => void;
 *   onSubmit?: () => void;
 *   onNavigate?: (section: 'profile' | 'security' | 'appearance') => void;
 * }} props
 */
export function AdminSettingsProfileInner({
    name: nameProp,
    email: emailProp,
    canEditProfile = true,
    errors = {},
    processing = false,
    onNameChange,
    onEmailChange,
    onSubmit,
    onNavigate,
}) {
    const [demoName, setDemoName] = useState('Nasreen Saif');
    const [demoEmail, setDemoEmail] = useState('nasreen@example.com');

    const name = nameProp ?? demoName;
    const email = emailProp ?? demoEmail;
    const handleNameChange = onNameChange ?? setDemoName;
    const handleEmailChange = onEmailChange ?? setDemoEmail;

    return (
        <AdminSettingsLayout
            active="profile"
            heading="Profile"
            subheading={
                canEditProfile
                    ? 'Update your name and email address'
                    : 'Name and email can only be changed by the Craft Master General'
            }
            onNavigate={onNavigate}
        >
            <form
                className="grid w-full max-w-xl gap-5"
                onSubmit={(event) => {
                    event.preventDefault();
                    if (!canEditProfile) {
                        return;
                    }
                    onSubmit?.();
                }}
            >
                <TextInput
                    label="Name"
                    value={name}
                    onChange={(event) => handleNameChange(event.target.value)}
                    error={errors.name}
                    autoComplete="name"
                    className="w-full max-w-full"
                    disabled={!canEditProfile}
                    readOnly={!canEditProfile}
                />
                <TextInput
                    label="Email"
                    type="email"
                    value={email}
                    onChange={(event) => handleEmailChange(event.target.value)}
                    error={errors.email}
                    autoComplete="email"
                    className="w-full max-w-full"
                    disabled={!canEditProfile}
                    readOnly={!canEditProfile}
                />
                {canEditProfile ? (
                    <div>
                        <Button type="submit" label={processing ? 'Saving…' : 'Save'} disabled={processing} />
                    </div>
                ) : (
                    <p className="font-montserrat text-sm text-[#555555]">
                        Use Security to change your password. Ask the Craft Master General to update your name or email.
                    </p>
                )}
            </form>
        </AdminSettingsLayout>
    );
}

/**
 * @param {{
 *   profile: { name: string; email: string };
 *   canEditProfile?: boolean;
 * }} props
 */
export default function Profile({ profile, canEditProfile = false }) {
    const { data, setData, patch, processing, errors } = useForm({
        name: profile.name ?? '',
        email: profile.email ?? '',
    });

    return (
        <AdminSettingsProfileInner
            name={data.name}
            email={data.email}
            canEditProfile={canEditProfile}
            errors={errors}
            processing={processing}
            onNameChange={(value) => setData('name', value)}
            onEmailChange={(value) => setData('email', value)}
            onSubmit={() => patch('/admin/settings/profile')}
        />
    );
}

Profile.layout = adminInertiaLayout;
