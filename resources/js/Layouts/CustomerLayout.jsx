import { Link, usePage } from '@inertiajs/react';
import MealCraftLogo from '../Components/Atoms/Logo/MealCraftLogo.jsx';
import CustomerOnboardingPhoneFrame from '../Components/Molecules/Customer/CustomerOnboardingPhoneFrame.jsx';

/**
 * @param {{ className?: string }} props
 */
function CartIcon({ className = '' }) {
    return (
        <svg className={className} width={18} height={18} viewBox="0 0 24 24" fill="none" aria-hidden>
            <path
                d="M6 6h15l-1.5 9h-12L6 6Zm0 0L5 3H2"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <circle cx="9" cy="20" r="1.25" fill="currentColor" />
            <circle cx="18" cy="20" r="1.25" fill="currentColor" />
        </svg>
    );
}

/**
 * @param {object} props
 * @param {import('react').ReactNode} props.children
 * @param {string} [props.customerName]
 * @param {string} [props.cartUrl]
 * @param {import('react').ReactNode} [props.footerNav]
 * @param {'default' | 'onboarding'} [props.layoutVariant]
 * @param {boolean} [props.embedded] Fill a parent phone slot (landing / marketing) instead of the full-page frame.
 */
export default function CustomerLayout({
    children,
    customerName: _customerName = '',
    cartUrl = '/app/cart',
    footerNav = null,
    layoutVariant = 'default',
    embedded = false,
}) {
    const isOnboarding = layoutVariant === 'onboarding';
    const hasFooterNav = footerNav != null;
    const pageUrl = String(usePage().url ?? '');
    const cartActive = pageUrl === cartUrl || pageUrl.startsWith(`${cartUrl}?`) || pageUrl.startsWith(`${cartUrl}/`);

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
            <Link
                href={cartUrl}
                className={[
                    'inline-flex shrink-0 items-center gap-1.5 font-montserrat text-sm font-semibold transition-colors',
                    cartActive ? 'text-[#5A6B44]' : 'text-[#555555] hover:text-[#262A22]',
                ].join(' ')}
                aria-current={cartActive ? 'page' : undefined}
            >
                <CartIcon className="shrink-0" />
                Cart
            </Link>
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
