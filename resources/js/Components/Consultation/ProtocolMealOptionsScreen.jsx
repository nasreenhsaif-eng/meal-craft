import { useEffect } from 'react';
import { createPortal } from 'react-dom';
import Button from '../Atoms/Button.jsx';

/**
 * Resolve the customer phone chrome when present so options fill that screen
 * instead of the full browser viewport.
 *
 * @returns {HTMLElement | null}
 */
function resolveOptionsPortalHost() {
    if (typeof document === 'undefined') {
        return null;
    }

    return (
        document.querySelector('[data-customer-onboarding-frame]') ||
        document.querySelector('[data-storybook-onboarding-frame]') ||
        document.body
    );
}

/**
 * SEE OTHER OPTIONS dialog — full-page inside the phone frame / on mobile;
 * centered sheet on larger bare viewports.
 *
 * @param {object} props
 * @param {string} [props.dayLabel] e.g. "SUNDAY"
 * @param {string} [props.sectionTitle] e.g. "Main Meals"
 * @param {() => void} props.onBack
 * @param {() => void} [props.onConfirm] Defaults to onBack when omitted.
 * @param {import('react').ReactNode} [props.children]
 * @param {string} [props.className]
 */
export default function ProtocolMealOptionsScreen({
    dayLabel = '',
    sectionTitle = 'Meals',
    onBack,
    onConfirm,
    children,
    className = '',
}) {
    const handleConfirm = typeof onConfirm === 'function' ? onConfirm : onBack;
    const handleClose = typeof onBack === 'function' ? onBack : handleConfirm;
    const day = String(dayLabel).trim().toUpperCase();

    useEffect(() => {
        const onKeyDown = (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                handleClose?.();
            }
        };

        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        window.addEventListener('keydown', onKeyDown);

        return () => {
            document.body.style.overflow = previousOverflow;
            window.removeEventListener('keydown', onKeyDown);
        };
    }, [handleClose]);

    if (typeof document === 'undefined') {
        return null;
    }

    const host = resolveOptionsPortalHost();
    if (!host) {
        return null;
    }

    const nestedInPhoneFrame = host !== document.body;

    return createPortal(
        <div
            className={
                nestedInPhoneFrame
                    ? 'absolute inset-0 z-[110] overflow-hidden overscroll-none'
                    : 'fixed inset-0 z-[110] overflow-hidden overscroll-none sm:flex sm:items-center sm:justify-center sm:p-6'
            }
        >
            {!nestedInPhoneFrame ? (
                <button
                    type="button"
                    className="absolute inset-0 bg-black/40 max-sm:hidden"
                    aria-label="Close other options"
                    onClick={handleClose}
                />
            ) : null}
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="mc-options-modal-title"
                className={[
                    'flex w-full flex-col overflow-hidden bg-[#F8F9F6]',
                    nestedInPhoneFrame
                        ? 'absolute inset-0 rounded-none'
                        : [
                              'absolute inset-0 rounded-none',
                              'sm:relative sm:inset-auto sm:my-auto sm:h-auto sm:max-h-[92dvh] sm:max-w-5xl sm:rounded-[16px] sm:shadow-2xl',
                          ].join(' '),
                    className,
                ]
                    .join(' ')
                    .trim()}
            >
                <div className="flex shrink-0 items-center justify-between gap-3 border-b border-gray-200 bg-white px-4 py-2 sm:px-5 sm:py-2.5">
                    <div className="min-w-0 flex-1">
                        {day ? (
                            <p className="font-montserrat text-[12px] font-bold uppercase tracking-wide text-[#555555]">
                                CRAFTING YOUR {day}
                            </p>
                        ) : null}
                        <h2
                            id="mc-options-modal-title"
                            className="font-montserrat text-lg font-bold tracking-tight text-[#262A22] sm:text-xl"
                        >
                            {sectionTitle}
                        </h2>
                    </div>
                    <Button
                        type="button"
                        label="Close"
                        variant="ghost"
                        size="sm"
                        onClick={handleClose}
                        className="shrink-0"
                    />
                </div>

                <div className="min-h-0 flex-1 overflow-x-clip overflow-y-auto overscroll-y-contain px-0 py-0 [-webkit-overflow-scrolling:touch] sm:py-1">
                    {children}
                </div>

                <div className="shrink-0 border-t border-gray-200 bg-white p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:p-4 sm:pb-[max(1rem,env(safe-area-inset-bottom))]">
                    <div className="flex items-center justify-between gap-3">
                        <Button
                            type="button"
                            label="BACK"
                            variant="outline"
                            size="md"
                            onClick={handleClose}
                            className="shrink-0"
                        />
                        <Button
                            type="button"
                            label="CONFIRM"
                            variant="primary"
                            size="md"
                            onClick={handleConfirm}
                            className="shrink-0"
                        />
                    </div>
                </div>
            </div>
        </div>,
        host,
    );
}
