import { useCallback, useEffect, useId, useState } from 'react';
import MealCraftLogo from '../../Components/Atoms/Logo/MealCraftLogo.jsx';
import TextInput from '../../Components/Atoms/TextInput/TextInput';
import Button from '../../Components/Atoms/Button/Button';
import TextLink from '../../Components/Atoms/TextLink.jsx';
import SquareCheckbox from '../../Components/Atoms/Icons/SquareCheckbox.jsx';

/** Shared width; pair with `text-center` (hero / footer) or `text-left` (field stack). */
const contentWidthLayout = 'mx-auto w-full max-w-[90%] md:max-w-[440px]';

const contentWidthClass = `${contentWidthLayout} text-center`;

/** Form fields: left-aligned labels + inputs; keeps inputs visually dominant vs. headings. */
const fieldStackClass = `${contentWidthLayout} space-y-8 text-left [&_input]:text-left`;

/** Success seal fade-in duration */
const SUCCESS_SEAL_FADE_MS = 700;

/**
 * Full-viewport sign-in — static form first; after successful auth, plays the seal animation then redirects.
 *
 * Laravel: pass `formAction` + `csrfToken` + validation props from `auth/login.blade.php`.
 *
 * @param {object} [props]
 * @param {string} [props.formAction] POST target (e.g. Fortify `login.store`). Enables native form submit.
 * @param {string} [props.csrfToken] Laravel `_token` when `formAction` is set.
 * @param {string} [props.forgotPasswordHref]
 * @param {boolean} [props.showForgotPassword]
 * @param {string} [props.signUpHref]
 * @param {boolean} [props.showSignUp]
 * @param {string} [props.initialEmail]
 * @param {boolean} [props.initialRemember]
 * @param {string} [props.emailError]
 * @param {string} [props.passwordError]
 * @param {string} [props.statusMessage] Session flash (e.g. reset link sent).
 * @param {string} [props.errorMessage] Session flash error (e.g. CSRF expired).
 * @param {(event: import('react').FormEvent<HTMLFormElement>) => void} [props.onSubmit]
 */
export default function LoginPage({
    formAction,
    csrfToken,
    forgotPasswordHref = '#',
    showForgotPassword = true,
    signUpHref = '#',
    showSignUp = true,
    initialEmail = '',
    initialRemember = false,
    emailError: initialEmailError = '',
    passwordError: initialPasswordError = '',
    statusMessage = '',
    errorMessage: initialErrorMessage = '',
    onSubmit,
}) {
    const isServerForm = Boolean(formAction);
    const rememberInputId = useId();

    const [email, setEmail] = useState(initialEmail);
    const [password, setPassword] = useState('');
    const [rememberMe, setRememberMe] = useState(initialRemember);
    const [emailError, setEmailError] = useState(initialEmailError);
    const [passwordError, setPasswordError] = useState(initialPasswordError);
    const [errorMessage, setErrorMessage] = useState(initialErrorMessage);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [successSealVisible, setSuccessSealVisible] = useState(false);
    const [redirectAfterSeal, setRedirectAfterSeal] = useState('');

    useEffect(() => {
        setEmail(initialEmail);
        setRememberMe(initialRemember);
        setEmailError(initialEmailError);
        setPasswordError(initialPasswordError);
        setErrorMessage(initialErrorMessage);
    }, [initialEmail, initialRemember, initialEmailError, initialPasswordError, initialErrorMessage]);

    const handleSealAnimationComplete = useCallback(() => {
        if (redirectAfterSeal) {
            window.location.assign(redirectAfterSeal);
        }
    }, [redirectAfterSeal]);

    const handleServerSubmit = async (event) => {
        event.preventDefault();
        setEmailError('');
        setPasswordError('');
        setErrorMessage('');
        setIsSubmitting(true);

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
            });

            if (response.ok) {
                const data = await response.json();

                if (data.two_factor) {
                    window.location.assign(data.redirect ?? '/two-factor-challenge');
                    return;
                }

                if (typeof data.redirect === 'string' && data.redirect.length > 0) {
                    setRedirectAfterSeal(data.redirect);
                    setSuccessSealVisible(true);
                    return;
                }
            }

            if (response.status === 422) {
                const data = await response.json();
                const errors = data.errors ?? {};

                setEmailError(errors.email?.[0] ?? '');
                setPasswordError(errors.password?.[0] ?? '');
                return;
            }

            if (response.status === 419) {
                setErrorMessage('Your session expired. Please refresh the page and try again.');
                return;
            }

            setErrorMessage('Sign in failed. Please try again.');
        } catch {
            setErrorMessage('Sign in failed. Please try again.');
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

    /** Native checkbox (`sr-only`) submits `remember` for Laravel; `SquareCheckbox` is presentational only. */
    const rememberControl = (
        <label
            htmlFor={rememberInputId}
            className="group/item inline-flex cursor-pointer select-none items-center gap-2 bg-transparent"
        >
            <input
                id={rememberInputId}
                type="checkbox"
                {...(isServerForm ? { name: 'remember', value: '1' } : {})}
                checked={rememberMe}
                onChange={(e) => setRememberMe(e.target.checked)}
                className="peer sr-only"
            />
            <span
                className="inline-flex shrink-0 rounded-[4px] peer-focus-visible:outline-none peer-focus-visible:ring-2 peer-focus-visible:ring-[#556C37] peer-focus-visible:ring-offset-2"
                aria-hidden
            >
                <SquareCheckbox presentational checked={rememberMe} />
            </span>
            <span className="font-montserrat text-sm font-medium leading-snug text-[#364153] peer-focus-visible:outline-none">
                Remember me
            </span>
        </label>
    );

    return (
        <div className="relative min-h-screen w-screen bg-[#FFFFFF]">
            <main className="relative z-0 flex min-h-screen w-screen flex-col items-center justify-center bg-[#FFFFFF] px-2 sm:px-4 md:px-6">
                <div className="flex w-full max-w-full flex-col items-center md:px-12">
                    <div className="flex justify-center">
                        <MealCraftLogo variant="seal-md" width={168} className="h-auto shrink-0" alt="Meal Craft" />
                    </div>

                    <header className={`mt-8 w-full ${contentWidthClass}`}>
                        <h1 className="font-sans text-2xl font-bold tracking-tight text-[#6E8C47] sm:text-3xl lg:text-4xl">
                            Welcome to Meal Craft
                        </h1>
                    </header>

                    {errorMessage ? (
                        <div className={`mt-6 w-full ${contentWidthClass}`}>
                            <p
                                className="rounded-[12px] border border-red-200 bg-red-50 px-4 py-3 text-center text-sm font-medium text-red-800"
                                role="alert"
                            >
                                {errorMessage}
                            </p>
                        </div>
                    ) : null}

                    {statusMessage ? (
                        <div className={`mt-6 w-full ${contentWidthClass}`}>
                            <p className="text-center text-sm font-medium text-green-600" role="status">
                                {statusMessage}
                            </p>
                        </div>
                    ) : null}

                    <form
                        onSubmit={handleSubmit}
                        method={isServerForm ? 'post' : undefined}
                        action={isServerForm ? formAction : undefined}
                        className="mt-6 w-full"
                        noValidate={!isServerForm}
                    >
                        {isServerForm ? <input type="hidden" name="_token" value={csrfToken} /> : null}

                        <div className={`${fieldStackClass} space-y-4`}>
                            <TextInput
                                label="Email"
                                type="email"
                                name="email"
                                placeholder="you@example.com"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                autoComplete="email"
                                error={emailError || undefined}
                                className="!max-w-none"
                                required={isServerForm}
                                disabled={isSubmitting || successSealVisible}
                            />
                            <TextInput
                                label="Password"
                                type="password"
                                name="password"
                                placeholder="••••••••"
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                autoComplete="current-password"
                                error={passwordError || undefined}
                                className="!max-w-none"
                                required={isServerForm}
                                disabled={isSubmitting || successSealVisible}
                            />

                            <div className="flex w-full flex-wrap items-center justify-between gap-x-4 gap-y-3 text-left">
                                <div className="flex min-w-0 items-center">{rememberControl}</div>
                                {showForgotPassword ? (
                                    <TextLink href={forgotPasswordHref} className="shrink-0 text-sm font-medium">
                                        Forgot Password?
                                    </TextLink>
                                ) : null}
                            </div>

                            <Button
                                label={isSubmitting ? 'Signing in…' : 'Sign In'}
                                variant="primary"
                                type="submit"
                                className="w-full"
                                disabled={isSubmitting || successSealVisible}
                            />
                        </div>

                        {showSignUp ? (
                            <p className="mx-auto mt-8 w-full text-center font-sans text-[13px] leading-relaxed text-grey-94 sm:text-sm">
                                <span className="font-medium">New here?</span>{' '}
                                <TextLink href={signUpHref} className="rounded-sm font-bold">
                                    Sign up
                                </TextLink>
                            </p>
                        ) : null}
                    </form>
                </div>
            </main>

            {successSealVisible ? (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-[#FFFFFF] px-4 motion-reduce:transition-none"
                    style={{
                        animation: `loginSuccessSealFadeIn ${SUCCESS_SEAL_FADE_MS}ms cubic-bezier(0.4, 0, 0.2, 1) forwards`,
                    }}
                    aria-busy="true"
                    aria-label="Signing you in"
                >
                    <div className="flex w-full max-w-[min(430px,100%)] flex-col items-center justify-center">
                        <MealCraftLogo
                            variant="marketing-animated"
                            width={320}
                            className="w-full"
                            alt="Meal Craft"
                            onAnimationComplete={handleSealAnimationComplete}
                        />
                    </div>
                </div>
            ) : null}

            <style>{`
                @keyframes loginSuccessSealFadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }
            `}</style>
        </div>
    );
}
