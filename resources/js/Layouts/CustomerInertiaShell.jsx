import CustomerLayout from './CustomerLayout.jsx';

/**
 * @param {{
 *   children: import('react').ReactNode;
 *   customerName?: string;
 *   headerActions?: import('react').ReactNode;
 *   layoutVariant?: 'default' | 'onboarding';
 * }} props
 */
export default function CustomerInertiaShell({
    children,
    customerName = '',
    headerActions = null,
    layoutVariant = 'default',
}) {
    return (
        <CustomerLayout
            customerName={customerName}
            headerActions={headerActions}
            layoutVariant={layoutVariant}
        >
            {children}
        </CustomerLayout>
    );
}
