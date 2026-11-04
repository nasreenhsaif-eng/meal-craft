import CustomerLayout from './CustomerLayout.jsx';

/**
 * @param {{
 *   children: import('react').ReactNode;
 *   customerName?: string;
 *   footerNav?: import('react').ReactNode;
 *   layoutVariant?: 'default' | 'onboarding';
 *   embedded?: boolean;
 * }} props
 */
export default function CustomerInertiaShell({
    children,
    customerName = '',
    footerNav = null,
    layoutVariant = 'default',
    embedded = false,
}) {
    return (
        <CustomerLayout
            customerName={customerName}
            footerNav={footerNav}
            layoutVariant={layoutVariant}
            embedded={embedded}
        >
            {children}
        </CustomerLayout>
    );
}
