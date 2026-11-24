import CustomerOnboardingPhoneFrame from '../Components/Molecules/Customer/CustomerOnboardingPhoneFrame.jsx';

/**
 * Storybook canvas for every Onboarding template — large-phone frame on desktop.
 * Flattens a nested production phone frame so wizard / app shells are not double-framed,
 * while keeping height so the footer can pin to the phone bottom.
 *
 * @param {{ children: import('react').ReactNode; className?: string }} props
 */
export function MobileStoryViewport({ children, className = '' }) {
    return (
        <CustomerOnboardingPhoneFrame
            storybook
            className={[
                // Flatten production chrome nested inside this Storybook frame.
                '[&_[data-customer-onboarding-root]]:min-h-full',
                '[&_[data-customer-onboarding-root]]:h-full',
                '[&_[data-customer-onboarding-root]]:flex-1',
                '[&_[data-customer-onboarding-root]]:items-stretch',
                '[&_[data-customer-onboarding-root]]:justify-start',
                '[&_[data-customer-onboarding-root]]:bg-transparent',
                '[&_[data-customer-onboarding-root]]:p-0',
                '[&_[data-customer-onboarding-frame]]:max-w-none',
                '[&_[data-customer-onboarding-frame]]:h-full',
                '[&_[data-customer-onboarding-frame]]:min-h-0',
                '[&_[data-customer-onboarding-frame]]:flex-1',
                '[&_[data-customer-onboarding-frame]]:w-full',
                '[&_[data-customer-onboarding-frame]]:rounded-none',
                '[&_[data-customer-onboarding-frame]]:border-0',
                '[&_[data-customer-onboarding-frame]]:bg-transparent',
                '[&_[data-customer-onboarding-frame]]:p-0',
                '[&_[data-customer-onboarding-frame]]:shadow-none',
                '[&_[data-customer-onboarding-frame]]:overflow-hidden',
                className,
            ]
                .join(' ')
                .trim()}
        >
            {children}
        </CustomerOnboardingPhoneFrame>
    );
}

export default MobileStoryViewport;
