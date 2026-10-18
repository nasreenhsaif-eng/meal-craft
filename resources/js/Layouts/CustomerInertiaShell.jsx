import CustomerLayout from './CustomerLayout.jsx';

/**
 * @param {{
 *   children: import('react').ReactNode;
 *   customerName?: string;
 *   footerNav?: import('react').ReactNode;
 *   layoutVariant?: 'default' | 'onboarding';
 * }} props
 */
export default function CustomerInertiaShell({
    children,
    customerName = '',
    footerNav = null,
    layoutVariant = 'default',
}) {
    return (
        <CustomerLayout customerName={customerName} footerNav={footerNav} layoutVariant={layoutVariant}>
            {children}
        </CustomerLayout>
    );
}
