/**
 * Large-phone chrome for desktop customer / onboarding screens (≈430×844).
 * Fixed height with overflow clipped so children can own internal scrolling.
 *
 * @param {{
 *   children: import('react').ReactNode;
 *   className?: string;
 *   storybook?: boolean;
 * }} props
 */
export default function CustomerOnboardingPhoneFrame({ children, className = '', storybook = false }) {
    return (
        <div
            className={[
                'flex min-h-screen w-full flex-col items-center justify-start bg-white px-3 py-4 font-sans text-[#262A22]',
                'md:bg-gray-50 md:px-8 md:py-10',
                className,
            ]
                .join(' ')
                .trim()}
            data-customer-onboarding-root={storybook ? undefined : ''}
            data-storybook-onboarding-root={storybook ? '' : undefined}
        >
            <div
                className={[
                    'relative mx-auto flex h-[min(844px,100dvh-5rem)] w-full max-w-[430px] flex-col overflow-hidden',
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
