import { useCallback, useEffect, useState } from 'react';
import MealCraftLogo from '../../Components/Atoms/Logo/MealCraftLogo.jsx';
import TextInput from '../../Components/Atoms/TextInput/TextInput';
import Button from '../../Components/Atoms/Button/Button';

const contentWidthLayout = 'mx-auto w-full max-w-[90%] md:max-w-[492px]';
const contentWidthClass = `${contentWidthLayout} text-center`;
const SUCCESS_SEAL_FADE_MS = 700;

/**
 * Signup OTP — one code sent to email and WhatsApp.
 *
 * @param {object} [props]
 * @param {string} [props.formAction]
 * @param {string} [props.resendAction]
 * @param {string} [props.csrfToken]
 * @param {string} [props.email]
 * @param {string} [props.maskedPhone]
 * @param {string} [props.codeError]
 * @param {string} [props.statusMessage]
 * @param {string} [props.warningMessage]
 * @param {string} [props.previewCode]
 * @param {string} [props.deliveryWarning]
 */
export default function JoinVerifyPage({
    formAction = '/join/verify',
    resendAction = '/join/verify/resend',
    csrfToken = '',
    email = '',
    maskedPhone = '',
    codeError: initialCodeError = '',
    statusMessage = '',
    warningMessage = '',
    previewCode = '',
    deliveryWarning = '',
}) {
    const [code, setCode] = useState('');
    const [codeError, setCodeError] = useState(initialCodeError);
    const [submitError, setSubmitError] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [successSealVisible, setSuccessSealVisible] = useState(false);
    const [redirectAfterSeal, setRedirectAfterSeal] = useState('');

    useEffect(() => {
        setCodeError(initialCodeError);
    }, [initialCodeError]);

    const handleSealAnimationComplete = useCallback(() => {
        if (redirectAfterSeal) {
            window.location.assign(redirectAfterSeal);
        }
    }, [redirectAfterSeal]);

    const handleSubmit = async (event) => {
        event.preventDefault();
        setCodeError('');
        setSubmitError('');
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

                if (typeof data.redirect === 'string' && data.redirect.length > 0) {
                    setRedirectAfterSeal(data.redirect);
                    setSuccessSealVisible(true);
                    return;
                }
            }

            if (response.status === 422) {
                const data = await response.json();
                setCodeError(data.errors?.code?.[0] ?? 'That code is incorrect.');
                return;
            }

            if (response.status === 419) {
                setSubmitError('Your session expired. Please refresh the page and try again.');
                return;
            }

            setSubmitError('Verification failed. Please try again.');
        } catch {
            setSubmitError('Verification failed. Please try again.');
        } finally {
            setIsSubmitting(false);
        }
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
                            Confirm your code
                        </h1>
                        <p className="font-montserrat text-base font-normal leading-normal text-black">
                            {previewCode
                                ? 'This machine is not sending email or WhatsApp yet. Use the code below to continue.'
                                : (
                                    <>
                                        We sent the same 6-digit code to{' '}
                                        <span className="font-semibold">{email || 'your email'}</span>
                                        {maskedPhone ? (
                                            <>
                                                {' '}
                                                and WhatsApp <span className="font-semibold">{maskedPhone}</span>
                                            </>
                                        ) : (
                                            ' and your WhatsApp number'
                                        )}
                                        . Enter it to continue.
                                    </>
                                )}
                        </p>
                    </header>

                    {previewCode ? (
                        <p
                            className={`font-montserrat text-3xl font-bold tracking-[0.2em] text-[#5A6B44] ${contentWidthClass}`}
                            data-test="signup-otp-preview"
                        >
                            {previewCode}
                        </p>
                    ) : null}

                    {deliveryWarning && !previewCode ? (
                        <p className={`w-full text-sm text-amber-700 ${contentWidthClass}`} role="status">
                            {deliveryWarning}
                        </p>
                    ) : null}

                    {warningMessage ? (
                        <p className={`w-full text-sm text-amber-700 ${contentWidthClass}`} role="status">
                            {warningMessage}
                        </p>
                    ) : null}

                    {statusMessage ? (
                        <p className={`w-full text-sm text-[#556C37] ${contentWidthClass}`} role="status">
                            {statusMessage}
                        </p>
                    ) : null}

                    {submitError ? (
                        <p className={`w-full text-sm text-red-600 ${contentWidthClass}`} role="alert">
                            {submitError}
                        </p>
                    ) : null}

                    <form method="post" action={formAction} onSubmit={handleSubmit} className={`w-full space-y-8 text-left ${contentWidthLayout}`}>
                        <input type="hidden" name="_token" value={csrfToken} />
                        <TextInput
                            label="Verification code"
                            type="text"
                            name="code"
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            placeholder="6-digit code"
                            value={code}
                            onChange={(event) => setCode(event.target.value.replace(/\D/g, '').slice(0, 6))}
                            error={codeError || undefined}
                            className="!max-w-none"
                            required
                            disabled={isSubmitting || successSealVisible}
                            data-test="signup-otp-input"
                        />
                        <div className="flex justify-center pt-2">
                            <Button
                                label={isSubmitting ? 'Checking…' : 'Continue'}
                                variant="primary"
                                type="submit"
                                className="min-w-[190px]"
                                disabled={isSubmitting || successSealVisible}
                                data-test="signup-otp-submit"
                            />
                        </div>
                    </form>

                    <form method="post" action={resendAction} className={contentWidthClass}>
                        <input type="hidden" name="_token" value={csrfToken} />
                        <button
                            type="submit"
                            className="font-montserrat text-base font-semibold text-[#556C37] underline underline-offset-2"
                            data-test="signup-otp-resend"
                        >
                            Resend code
                        </button>
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
