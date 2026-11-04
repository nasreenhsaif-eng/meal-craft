import MealCraftLogo from '../Components/Atoms/Logo/MealCraftLogo.jsx';
import CustomerOnboardingPhoneFrame from '../Components/Molecules/Customer/CustomerOnboardingPhoneFrame.jsx';

/**
 * @param {object} props
 * @param {import('react').ReactNode} props.children
 * @param {string} [props.customerName]
 * @param {import('react').ReactNode} [props.footerNav]
 * @param {'default' | 'onboarding'} [props.layoutVariant]
 * @param {boolean} [props.embedded] Fill a parent phone slot (landing / marketing) instead of the full-page frame.
 */
export default function CustomerLayout({
    children,
    customerName = '',
    footerNav = null,
    layoutVariant = 'default',
    embedded = false,
}) {
    const isOnboarding = layoutVariant === 'onboarding';
    const hasFooterNav = footerNav != null;

    const header = (
        <header className="flex w-full shrink-0 items-center justify-between gap-3 border-b border-gray-100 bg-white px-1 py-3">
            <div className="flex min-w-0 items-center gap-3">
                <MealCraftLogo variant="seal-xs" width={40} alt="" />
                <div className="min-w-0">
                    <p className="font-montserrat text-base font-bold tracking-tight">Meal Craft</p>
                    <p className="font-montserrat text-[11px] font-bold uppercase tracking-[0.14em] text-[#555555]">
                        Your plan
                    </p>
                </div>
            </div>
            {customerName ? (
                <p className="max-w-[45%] shrink-0 truncate text-right text-sm font-medium text-[#555555]">
                    {customerName}
                </p>
            ) : null}
        </header>
    );

    if (isOnboarding) {
        return (
            <CustomerOnboardingPhoneFrame embedded={embedded}>
                <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain pb-4">{children}</div>
            </CustomerOnboardingPhoneFrame>
        );
    }

    return (
        <CustomerOnboardingPhoneFrame embedded={embedded}>
            <div className="relative flex h-full min-h-0 w-full flex-1 flex-col bg-[#F8F9F6] font-sans text-[#262A22]">
                {header}
                <main
                    className={[
                        'mx-auto min-h-0 w-full flex-1 overflow-y-auto overscroll-contain px-1 py-4',
                        // Six-tab customer nav is taller; keep list rows clear of the overlay.
                        hasFooterNav ? 'pb-32' : 'pb-4',
                    ]
                        .join(' ')
                        .trim()}
                >
                    {children}
                </main>
                {footerNav}
            </div>
        </CustomerOnboardingPhoneFrame>
    );
}
