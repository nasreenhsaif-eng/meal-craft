/**
 * Large-phone chrome for desktop customer / onboarding screens (≈430×844).
 * Fixed height with overflow clipped so children can own internal scrolling.
 * `overflow-clip` (not `hidden`) so a focused control cannot scroll this frame.
 *
 * @param {{
 *   children: import('react').ReactNode;
 *   className?: string;
 *   storybook?: boolean;
 *   embedded?: boolean;
 * }} props
 */
export default function CustomerOnboardingPhoneFrame({
    children,
    className = '',
    storybook = false,
    embedded = false,
}) {
    if (embedded) {
        return (
            <div
                className={[
                    'relative flex h-full min-h-0 w-full flex-col overflow-clip',
                    'rounded-[28px] border border-gray-200 bg-white shadow-lg',
                    'px-3 pt-3 font-sans text-[#262A22]',
                    className,
                ]
                    .join(' ')
                    .trim()}
                data-customer-onboarding-frame="embedded"
            >
                {children}
            </div>
        );
    }

    return (
        <div
            className={[
                // Gray canvas whenever the rounded phone chrome is visible (≥430px).
                'flex min-h-screen w-full flex-col items-center justify-start bg-gray-50 px-3 py-4 font-sans text-[#262A22]',
                'min-[430px]:px-8 min-[430px]:py-10',
                'max-[429px]:bg-white max-[429px]:px-0 max-[429px]:py-0',
                className,
            ]
                .join(' ')
                .trim()}
            data-customer-onboarding-root={storybook ? undefined : ''}
            data-storybook-onboarding-root={storybook ? '' : undefined}
        >
            <div
                className={[
                    'relative mx-auto flex h-[min(844px,100dvh-5rem)] w-full max-w-[430px] flex-col overflow-clip',
                    'rounded-[28px] border border-gray-200 bg-white shadow-lg',
                    // On real small phones, fill the viewport without device chrome.
                    'max-[429px]:h-dvh max-[429px]:max-h-dvh max-[429px]:rounded-none max-[429px]:border-0 max-[429px]:shadow-none',
                    'px-3 pt-3',
                ].join(' ')}
                data-customer-onboarding-frame={storybook ? undefined : ''}
                data-storybook-onboarding-frame={storybook ? '' : undefined}
            >
                {children}
            </div>
        </div>
    );
}
