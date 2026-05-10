import { useEffect, useId, useState } from 'react';
import MealCraftLogo from '../../Components/Atoms/Logo/MealCraftLogo.jsx';
import TextInput from '../../Components/Atoms/TextInput/TextInput';
import Button from '../../Components/Atoms/Button/Button';
import TextLink from '../../Components/Atoms/TextLink.jsx';
import SquareCheckbox from '../../Components/Atoms/SquareCheckbox.jsx';

/** Shared width; pair with `text-center` (hero / footer) or `text-left` (field stack). */
const contentWidthLayout = 'mx-auto w-full max-w-[90%] md:max-w-[440px]';

const contentWidthClass = `${contentWidthLayout} text-center`;

/** Form fields: left-aligned labels + inputs; keeps inputs visually dominant vs. headings. */
const fieldStackClass = `${contentWidthLayout} space-y-8 text-left [&_input]:text-left`;

/** Time to hold splash before crossfade (~5s lets marketing-animated tagline reveal finish). */
const DEFAULT_SPLASH_MS = 5000;

/** Splash fade-out + login fade-in duration */
const CROSSFADE_MS = 700;

/**
 * Full-viewport sign-in — Phase 1: white splash with `marketing-animated` lockup; Phase 2: form.
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
 * @param {(event: import('react').FormEvent<HTMLFormElement>) => void} [props.onSubmit]
 * @param {number} [props.splashDurationMs] Hold splash before crossfade; `0` skips splash (e.g. Storybook). Default 5000.
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
    emailError = '',
    passwordError = '',
    statusMessage = '',
    onSubmit,
    splashDurationMs = DEFAULT_SPLASH_MS,
}) {
    const isServerForm = Boolean(formAction);
    const rememberInputId = useId();

    const skipSplash = splashDurationMs <= 0;

    const [email, setEmail] = useState(initialEmail);
    const [password, setPassword] = useState('');
    const [rememberMe, setRememberMe] = useState(initialRemember);

    /** Splash overlay mounted (removed after fade-out completes). */
    const [splashMounted, setSplashMounted] = useState(!skipSplash);
    /** Splash layer opacity: fades out when crossfade starts. */
    const [splashOpaque, setSplashOpaque] = useState(!skipSplash);
    /** Login column visible + interactive after crossfade begins. */
    const [formRevealed, setFormRevealed] = useState(skipSplash);

    useEffect(() => {
        if (skipSplash) {
            return undefined;
        }
        const holdMs = splashDurationMs;
        const t = window.setTimeout(() => {
            setSplashOpaque(false);
            setFormRevealed(true);
        }, holdMs);
        return () => window.clearTimeout(t);
    }, [skipSplash, splashDurationMs]);

    /** Unmount splash layer after CSS opacity transition (and as a fallback if `transitionend` is skipped). */
    useEffect(() => {
        if (skipSplash || splashOpaque) {
            return undefined;
        }
        const t = window.setTimeout(() => setSplashMounted(false), CROSSFADE_MS);
        return () => window.clearTimeout(t);
    }, [skipSplash, splashOpaque]);

    const handleSubmit = (event) => {
        if (isServerForm) {
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
            <main
                className={`relative z-0 flex min-h-screen w-screen flex-col items-center justify-center bg-[#FFFFFF] px-2 sm:px-4 md:px-6 motion-reduce:transition-none ${
                    formRevealed ? 'pointer-events-auto opacity-100' : 'pointer-events-none opacity-0'
                }`}
                style={{
                    transitionProperty: 'opacity',
                    transitionDuration: `${CROSSFADE_MS}ms`,
                    transitionTimingFunction: 'cubic-bezier(0.4, 0, 0.2, 1)',
                }}
                aria-hidden={!formRevealed}
            >
                <div className="flex w-full max-w-full flex-col items-center md:px-12">
                    <div className="mx-auto w-full max-w-[280px]">
                        <MealCraftLogo variant="smart" width={217} className="w-full" alt="Meal Craft" />
                    </div>

                    <header className={`mt-8 w-full ${contentWidthClass}`}>
                        <h1 className="font-sans text-2xl font-bold tracking-tight text-[#6E8C47] sm:text-3xl lg:text-4xl">
                            Welcome to Meal Craft
                        </h1>
                        <p className="mx-auto mt-3 max-w-prose text-base font-medium leading-relaxed text-grey-94 sm:text-lg">
                            Your Smart Kitchen Dashboard.
                        </p>
                    </header>

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
                            />

                            <div className="flex w-full flex-wrap items-center justify-between gap-x-4 gap-y-3 text-left">
                                <div className="flex min-w-0 items-center">{rememberControl}</div>
                                {showForgotPassword ? (
                                    <TextLink href={forgotPasswordHref} className="shrink-0 text-sm font-medium">
                                        Forgot Password?
                                    </TextLink>
                                ) : null}
                            </div>

                            <Button label="Sign In" variant="primary" type="submit" className="w-full" />
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

            {splashMounted ? (
                <div
                    className={`fixed inset-0 z-50 flex items-center justify-center bg-[#FFFFFF] px-4 motion-reduce:transition-none ${
                        splashOpaque ? 'opacity-100' : 'pointer-events-none opacity-0'
                    }`}
                    style={{
                        transitionProperty: 'opacity',
                        transitionDuration: `${CROSSFADE_MS}ms`,
                        transitionTimingFunction: 'cubic-bezier(0.4, 0, 0.2, 1)',
                    }}
                    aria-busy={splashOpaque}
                    aria-label="Meal Craft"
                    aria-hidden={!splashOpaque}
                >
                    <div className="flex w-full max-w-[min(430px,100%)] flex-col items-center justify-center">
                        <MealCraftLogo variant="marketing-animated" width={320} className="w-full" alt="Meal Craft" />
                    </div>
                </div>
            ) : null}
        </div>
    );
}
