import { Link, usePage } from '@inertiajs/react';
import MealCraftLogo from '../Components/Atoms/Logo/MealCraftLogo.jsx';
import CustomerOnboardingPhoneFrame from '../Components/Molecules/Customer/CustomerOnboardingPhoneFrame.jsx';

/**
 * Cart highlight follows the Inertia URL. The marketing landing mounts this
 * layout outside Inertia, where usePage throws and blanks the page.
 *
 * @param {string} cartUrl
 * @returns {boolean}
 */
function useCartActive(cartUrl) {
    try {
        const pageUrl = String(usePage().url ?? '');

        return pageUrl === cartUrl || pageUrl.startsWith(`${cartUrl}?`) || pageUrl.startsWith(`${cartUrl}/`);
    } catch {
        return false;
    }
}

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
 * @param {(() => void) | null} [props.onBack]
 * @param {string} [props.backAriaLabel]
 */
export default function CustomerLayout({
    children,
    customerName: _customerName = '',
    cartUrl = '/app/cart',
    footerNav = null,
    layoutVariant = 'default',
    embedded = false,
    onBack = null,
    backAriaLabel = 'Go back',
}) {
    const isOnboarding = layoutVariant === 'onboarding';
    const hasFooterNav = footerNav != null;
    const cartActive = useCartActive(cartUrl);
    const cartClassName = [
        'inline-flex h-10 shrink-0 items-center justify-end gap-1.5 font-montserrat text-sm font-semibold transition-colors',
        cartActive ? 'text-[#5A6B44]' : 'text-[#555555] hover:text-[#262A22]',
    ].join(' ');
    const cartContents = (
        <>
            <CartIcon className="shrink-0" />
            Cart
        </>
    );

    const header = (
        <header className="w-full shrink-0 bg-white px-1 py-2" aria-label="Meal Craft">
            <div className="grid w-full grid-cols-[2.5rem_1fr_minmax(4.5rem,auto)] items-center gap-x-2">
                {typeof onBack === 'function' ? (
                    <button
                        type="button"
                        onClick={onBack}
                        aria-label={backAriaLabel}
                        className="flex h-10 w-10 items-center justify-center rounded-full bg-gray-50 text-base leading-none text-[#262A22] outline-none transition [-webkit-tap-highlight-color:transparent] hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#6E8C47] focus-visible:ring-offset-2"
                    >
                        ←
                    </button>
                ) : (
                    <span className="block h-10 w-10" aria-hidden="true" />
                )}
                <div className="flex min-w-0 justify-center">
                    <MealCraftLogo
                        variant="minimal"
                        width={108}
                        alt="Meal Craft"
                        className="h-auto max-w-full"
                    />
                </div>
                {embedded ? (
                    <span className={cartClassName} aria-current={cartActive ? 'page' : undefined}>
                        {cartContents}
                    </span>
                ) : (
                    <Link href={cartUrl} className={cartClassName} aria-current={cartActive ? 'page' : undefined}>
                        {cartContents}
                    </Link>
                )}
            </div>
        </header>
    );

    if (isOnboarding) {
        return (
            <CustomerOnboardingPhoneFrame embedded={embedded}>
                <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain bg-white pb-4">{children}</div>
            </CustomerOnboardingPhoneFrame>
        );
    }

    return (
        <CustomerOnboardingPhoneFrame embedded={embedded}>
            <div className="relative flex h-full min-h-0 w-full flex-1 flex-col bg-white font-sans text-[#262A22]">
                {header}
                <main
                    className={[
                        'mx-auto min-h-0 w-full flex-1 overflow-y-auto overscroll-contain bg-white px-1 py-4',
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
