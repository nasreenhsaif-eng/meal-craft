import { Link } from '@inertiajs/react';
import CustomerAppBottomNav from '../../Components/Molecules/Customer/CustomerAppBottomNav.jsx';
import CustomerInertiaShell from '../../Layouts/CustomerInertiaShell.jsx';
import { resolveInertiaLayoutChild } from '../../lib/resolveInertiaLayoutChild.js';

/**
 * @param {object} props
 * @param {string} [props.customerName]
 * @param {string} [props.homeUrl]
 * @param {string} [props.profileUrl]
 * @param {string} [props.shopUrl]
 * @param {string} [props.mealPlanSummaryUrl]
 * @param {string} [props.toolsUrl]
 * @param {string} [props.cartUrl]
 */
export function CartInner({
    customerName = '',
    homeUrl = '/app',
    profileUrl = '/app/profile',
    shopUrl = '/app/shop',
    mealPlanSummaryUrl = '/app/meal-plan',
    toolsUrl = '/app/tools',
    cartUrl = '/app/cart',
}) {
    return (
        <CustomerInertiaShell
            customerName={customerName}
            cartUrl={cartUrl}
            footerNav={
                <CustomerAppBottomNav
                    homeUrl={homeUrl}
                    profileUrl={profileUrl}
                    shopUrl={shopUrl}
                    mealsUrl={mealPlanSummaryUrl}
                    toolsUrl={toolsUrl}
                />
            }
        >
            <div className="w-full min-w-0 rounded-[16px] border border-gray-200 bg-white px-4 py-6 shadow-sm sm:p-8">
                <div className="flex items-start justify-between gap-3">
                    <h1 className="min-w-0 font-montserrat text-3xl font-semibold tracking-tight text-[#262A22]">
                        Cart
                    </h1>
                    <Link
                        href={homeUrl}
                        className="inline-flex shrink-0 items-center justify-center rounded-[12px] px-4 py-2 font-montserrat text-sm font-bold uppercase tracking-wider text-[#5A6B44] transition-colors hover:bg-[#5A6B44]/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-offset-2"
                    >
                        Close
                    </Link>
                </div>
                <p className="mt-3 font-body text-sm leading-relaxed text-[#555555] sm:text-base">
                    Your shopping cart is empty.
                </p>
            </div>
        </CustomerInertiaShell>
    );
}

/**
 * @param {object} props
 */
export default function Cart(props) {
    return <CartInner {...props} />;
}

Cart.layout = (pageOrProps) => resolveInertiaLayoutChild(pageOrProps);
