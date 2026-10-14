import { useEffect, useState } from 'react';
import MealCraftLogo from '../../Components/Atoms/Logo/MealCraftLogo.jsx';
import TextInput from '../../Components/Atoms/TextInput/TextInput';
import Button from '../../Components/Atoms/Button/Button';
import TextLink from '../../Components/Atoms/TextLink.jsx';

/** Join form width — matches `pages/auth/join.blade.php` `max-w-[492px]`. */
const contentWidthLayout = 'mx-auto w-full max-w-[90%] md:max-w-[492px]';
const contentWidthClass = `${contentWidthLayout} text-center`;
const fieldStackClass = `${contentWidthLayout} space-y-8 text-left [&_input]:text-left`;

/**
 * @param {unknown} errors
 * @param {string} key
 * @returns {string}
 */
function firstError(errors, key) {
    const value = errors?.[key];

    if (Array.isArray(value)) {
        return typeof value[0] === 'string' ? value[0] : '';
    }

    return typeof value === 'string' ? value : '';
}

/**
 * @param {string} phone
 * @returns {boolean}
 */
function phoneLooksInvalid(phone) {
    const trimmed = phone.trim();

    if (trimmed === '' || trimmed.includes('@')) {
        return true;
    }

    const digits = trimmed.replace(/\D/g, '');

    return digits.length < 8 || digits.length > 15;
}

/**
 * @param {Response} response
 * @returns {Promise<Record<string, unknown> | null>}
 */
async function readJson(response) {
    try {
        const text = await response.text();

        if (text === '') {
            return null;
        }

        const data = JSON.parse(text);

        return data !== null && typeof data === 'object' ? data : null;
    } catch {
        return null;
    }
}

/**
 * Customer sign-up — `/join` and Fortify `/register`.
 *
 * Laravel: pass `formAction` + `csrfToken` so the form posts to `register.store`.
 *
 * @param {object} [props]
 * @param {string} [props.formAction]
 * @param {string} [props.csrfToken]
 * @param {string} [props.loginHref]
 * @param {string} [props.initialName]
 * @param {string} [props.initialEmail]
 * @param {string} [props.initialPhone]
 * @param {string} [props.nameError]
 * @param {string} [props.emailError]
 * @param {string} [props.phoneError]
 * @param {string} [props.passwordError]
 * @param {string} [props.statusMessage]
 * @param {string} [props.errorMessage]
 * @param {(event: import('react').FormEvent<HTMLFormElement>) => void} [props.onSubmit]
 */
export default function JoinPage({
    formAction,
    csrfToken,
    loginHref = '/login',
    initialName = '',
    initialEmail = '',
    initialPhone = '',
    nameError: initialNameError = '',
    emailError: initialEmailError = '',
    phoneError: initialPhoneError = '',
    passwordError: initialPasswordError = '',
    statusMessage = '',
    errorMessage: initialErrorMessage = '',
    onSubmit,
}) {
    const isServerForm = Boolean(formAction);
    const [name, setName] = useState(initialName);
    const [email, setEmail] = useState(initialEmail);
    const [phone, setPhone] = useState(initialPhone);
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [nameError, setNameError] = useState(initialNameError);
    const [emailError, setEmailError] = useState(initialEmailError);
    const [phoneError, setPhoneError] = useState(initialPhoneError);
    const [passwordError, setPasswordError] = useState(initialPasswordError);
    const [errorMessage, setErrorMessage] = useState(initialErrorMessage);
    const [isSubmitting, setIsSubmitting] = useState(false);

    useEffect(() => {
        setName(initialName);
        setEmail(initialEmail);
        setPhone(initialPhone);
        setNameError(initialNameError);
        setEmailError(initialEmailError);
        setPhoneError(initialPhoneError);
        setPasswordError(initialPasswordError);
        setErrorMessage(initialErrorMessage);
    }, [
        initialName,
        initialEmail,
        initialPhone,
        initialNameError,
        initialEmailError,
        initialPhoneError,
        initialPasswordError,
        initialErrorMessage,
    ]);

    const handleServerSubmit = async (event) => {
        event.preventDefault();
        setNameError('');
        setEmailError('');
        setPhoneError('');
        setPasswordError('');
        setErrorMessage('');
        setIsSubmitting(true);

        const localPhoneError = phoneLooksInvalid(phone)
            ? 'Enter a valid mobile number, including the country code.'
            : '';
        const localPasswordError = password !== passwordConfirmation
            ? 'The password confirmation does not match.'
            : '';

        if (localPhoneError) {
            setPhoneError(localPhoneError);
        }

        if (localPasswordError) {
            setPasswordError(localPasswordError);
        }

        try {
            const formData = new FormData(event.currentTarget);
            const response = await fetch(formAction, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
                credentials: 'same-origin',
                redirect: 'manual',
            });

            const data = await readJson(response);

            if (response.ok) {
                if (typeof data?.redirect === 'string' && data.redirect.length > 0) {
                    window.location.assign(data.redirect);
                    return;
                }
            }

            if (response.status === 419 || response.type === 'opaqueredirect' || response.status === 0) {
                setErrorMessage('Your session expired. Please refresh the page and try again.');
                return;
            }

            const errors = data?.errors ?? {};
            const nameMessage = firstError(errors, 'name');
            const emailMessage = firstError(errors, 'email');
            const phoneMessage = firstError(errors, 'phone') || localPhoneError;
            const passwordMessage = firstError(errors, 'password')
                || firstError(errors, 'password_confirmation')
                || localPasswordError;

            setNameError(nameMessage);
            setEmailError(emailMessage);
            setPhoneError(phoneMessage);
            setPasswordError(passwordMessage);

            const messages = [nameMessage, emailMessage, phoneMessage, passwordMessage].filter(Boolean);

            if (messages.length > 0) {
                setErrorMessage(messages.join(' '));
                return;
            }

            if (typeof data?.message === 'string' && data.message.length > 0) {
                setErrorMessage(data.message);
                return;
            }

            setErrorMessage('Sign up failed. Please try again.');
        } catch {
            const messages = [localPhoneError, localPasswordError].filter(Boolean);
            setErrorMessage(messages.join(' ') || 'Sign up failed. Please try again.');
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleSubmit = (event) => {
        if (isServerForm) {
            void handleServerSubmit(event);
            return;
        }

        event.preventDefault();
        onSubmit?.(event);
    };

    return (
        <div className="relative min-h-screen w-screen bg-[#FFFFFF]">
            <main className="relative z-0 flex min-h-screen w-screen flex-col items-center justify-center bg-[#FFFFFF] px-2 py-10 sm:px-4 md:px-6">
                <div className="flex w-full max-w-full flex-col items-center gap-10 md:px-12">
                    <div className="flex justify-center">
                        <MealCraftLogo
                            variant="vertical-smart"
                            width={280}
                            className="h-auto max-w-full shrink-0"
                            alt="Meal Craft"
                        />
                    </div>

                    <header className={`flex w-full flex-col items-center gap-[19px] ${contentWidthClass}`}>
                        <h1 className="font-montserrat text-2xl font-semibold leading-tight text-black">
                            Start your meal plan
                        </h1>
                        <p className="font-montserrat text-base font-normal leading-normal text-black">
                            Create your customer account to personalize meals and track your nutrition goals.
                        </p>
                    </header>

                    {errorMessage ? (
                        <p className={`w-full text-sm text-red-600 ${contentWidthClass}`} role="alert">
                            {errorMessage}
                        </p>
                    ) : null}

                    {statusMessage ? (
                        <p className={`w-full text-sm text-[#556C37] ${contentWidthClass}`} role="status">
                            {statusMessage}
                        </p>
                    ) : null}

                    <form
                        onSubmit={handleSubmit}
                        method={isServerForm ? 'post' : undefined}
                        action={isServerForm ? formAction : undefined}
                        className="w-full"
                        noValidate={!isServerForm}
                    >
                        {isServerForm ? <input type="hidden" name="_token" value={csrfToken} /> : null}
                        <div className={`${fieldStackClass} !space-y-8`}>
                            <TextInput
                                label="Name"
                                type="text"
                                name="name"
                                placeholder="Full name"
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                                autoComplete="name"
                                error={nameError || undefined}
                                className="!max-w-none"
                                required={isServerForm}
                                disabled={isSubmitting}
                            />
                            <TextInput
                                label="Email address"
                                type="email"
                                name="email"
                                placeholder="email@example.com"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                autoComplete="email"
                                error={emailError || undefined}
                                className="!max-w-none"
                                required={isServerForm}
                                disabled={isSubmitting}
                            />
                            <TextInput
                                label="Mobile number"
                                type="tel"
                                name="phone"
                                placeholder="+973 XXXX XXXX"
                                value={phone}
                                onChange={(e) => setPhone(e.target.value)}
                                autoComplete="tel"
                                error={phoneError || undefined}
                                className="!max-w-none"
                                required={isServerForm}
                                disabled={isSubmitting}
                            />
                            <TextInput
                                label="Password"
                                type="password"
                                name="password"
                                placeholder="Password"
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                autoComplete="new-password"
                                error={passwordError || undefined}
                                className="!max-w-none"
                                required={isServerForm}
                                disabled={isSubmitting}
                            />
                            <TextInput
                                label="Confirm password"
                                type="password"
                                name="password_confirmation"
                                placeholder="Confirm password"
                                value={passwordConfirmation}
                                onChange={(e) => setPasswordConfirmation(e.target.value)}
                                autoComplete="new-password"
                                className="!max-w-none"
                                required={isServerForm}
                                disabled={isSubmitting}
                            />

                            <div className="flex justify-center pt-2">
                                <Button
                                    label={isSubmitting ? 'Creating account…' : 'Create account'}
                                    variant="primary"
                                    type="submit"
                                    className="min-w-[190px]"
                                    disabled={isSubmitting}
                                    data-test="register-user-button"
                                />
                            </div>
                        </div>
                    </form>

                    <p className={`font-montserrat text-base font-medium text-black ${contentWidthClass}`}>
                        <span>Already have an account?</span>{' '}
                        <TextLink href={loginHref} className="rounded-sm font-semibold">
                            Log in
                        </TextLink>
                    </p>
                </div>
            </main>
        </div>
    );
}
