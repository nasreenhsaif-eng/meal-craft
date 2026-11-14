import CustomerLayout from './CustomerLayout.jsx';

/**
 * @param {{
 *   children: import('react').ReactNode;
 *   customerName?: string;
 *   cartUrl?: string;
 *   footerNav?: import('react').ReactNode;
 *   layoutVariant?: 'default' | 'onboarding';
 *   embedded?: boolean;
 *   onBack?: (() => void) | null;
 *   backAriaLabel?: string;
 * }} props
 */
export default function CustomerInertiaShell({
    children,
    customerName = '',
    cartUrl = '/app/cart',
    footerNav = null,
    layoutVariant = 'default',
    embedded = false,
    onBack = null,
    backAriaLabel = 'Go back',
}) {
    return (
        <CustomerLayout
            customerName={customerName}
            cartUrl={cartUrl}
            footerNav={footerNav}
            layoutVariant={layoutVariant}
            embedded={embedded}
            onBack={onBack}
            backAriaLabel={backAriaLabel}
        >
            {children}
        </CustomerLayout>
    );
}
