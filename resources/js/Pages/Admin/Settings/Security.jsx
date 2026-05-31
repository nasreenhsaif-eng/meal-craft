import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import Button from '../../../Components/Atoms/Button/Button.jsx';
import TextInput from '../../../Components/Atoms/TextInput/TextInput.jsx';
import { AdminSettingsLayout } from '../../../Components/Admin/AdminSettingsLayout.jsx';
import AdminInertiaShell from '../../../Layouts/AdminInertiaShell.jsx';

/**
 * Security settings markup (Storybook / Inertia).
 *
 * @param {{
 *   currentPassword?: string;
 *   password?: string;
 *   passwordConfirmation?: string;
 *   onCurrentPasswordChange?: (value: string) => void;
 *   onPasswordChange?: (value: string) => void;
 *   onPasswordConfirmationChange?: (value: string) => void;
 *   errors?: Record<string, string>;
 *   processing?: boolean;
 *   onSubmit?: () => void;
 *   onNavigate?: (section: 'profile' | 'security' | 'appearance') => void;
 * }} props
 */
export function AdminSettingsSecurityInner({
    currentPassword: currentPasswordProp,
    password: passwordProp,
    passwordConfirmation: passwordConfirmationProp,
    onCurrentPasswordChange,
    onPasswordChange,
    onPasswordConfirmationChange,
    errors = {},
    processing = false,
    onSubmit,
    onNavigate,
}) {
    const [demoCurrentPassword, setDemoCurrentPassword] = useState('');
    const [demoPassword, setDemoPassword] = useState('');
    const [demoPasswordConfirmation, setDemoPasswordConfirmation] = useState('');

    const currentPassword = currentPasswordProp ?? demoCurrentPassword;
    const password = passwordProp ?? demoPassword;
    const passwordConfirmation = passwordConfirmationProp ?? demoPasswordConfirmation;
    const handleCurrentPasswordChange = onCurrentPasswordChange ?? setDemoCurrentPassword;
    const handlePasswordChange = onPasswordChange ?? setDemoPassword;
    const handlePasswordConfirmationChange = onPasswordConfirmationChange ?? setDemoPasswordConfirmation;

    return (
        <AdminSettingsLayout
            active="security"
            heading="Security"
            subheading="Update your password and keep your account secure"
            onNavigate={onNavigate}
        >
            <form
                className="grid w-full max-w-xl gap-5"
                onSubmit={(event) => {
                    event.preventDefault();
                    onSubmit?.();

                    if (!onSubmit) {
                        handleCurrentPasswordChange('');
                        handlePasswordChange('');
                        handlePasswordConfirmationChange('');
                    }
                }}
            >
                <TextInput
                    label="Current password"
                    type="password"
                    value={currentPassword}
                    onChange={(event) => handleCurrentPasswordChange(event.target.value)}
                    error={errors.current_password}
                    revealPassword
                    autoComplete="current-password"
                    className="w-full max-w-full"
                />
                <TextInput
                    label="New password"
                    type="password"
                    value={password}
                    onChange={(event) => handlePasswordChange(event.target.value)}
                    error={errors.password}
                    revealPassword
                    autoComplete="new-password"
                    className="w-full max-w-full"
                />
                <TextInput
                    label="Confirm new password"
                    type="password"
                    value={passwordConfirmation}
                    onChange={(event) => handlePasswordConfirmationChange(event.target.value)}
                    error={errors.password_confirmation}
                    revealPassword
                    autoComplete="new-password"
                    className="w-full max-w-full"
                />
                <div>
                    <Button type="submit" label={processing ? 'Saving…' : 'Update password'} disabled={processing} />
                </div>
            </form>
        </AdminSettingsLayout>
    );
}

export default function Security() {
    const { data, setData, put, processing, errors, reset } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    return (
        <AdminSettingsSecurityInner
            currentPassword={data.current_password}
            password={data.password}
            passwordConfirmation={data.password_confirmation}
            onCurrentPasswordChange={(value) => setData('current_password', value)}
            onPasswordChange={(value) => setData('password', value)}
            onPasswordConfirmationChange={(value) => setData('password_confirmation', value)}
            errors={errors}
            processing={processing}
            onSubmit={() =>
                put('/admin/settings/security/password', {
                    onSuccess: () => reset(),
                })
            }
        />
    );
}

Security.layout = (page) => <AdminInertiaShell>{page}</AdminInertiaShell>;
