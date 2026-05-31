import CustomerLayout from './CustomerLayout.jsx';

/**
 * @param {{ children: import('react').ReactNode, customerName?: string }} props
 */
export default function CustomerInertiaShell({ children, customerName = '' }) {
    return <CustomerLayout customerName={customerName}>{children}</CustomerLayout>;
}
