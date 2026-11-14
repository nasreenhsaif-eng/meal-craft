import CustomerAppBottomNav from '../../Components/Molecules/Customer/CustomerAppBottomNav.jsx';
import CustomerInertiaShell from '../../Layouts/CustomerInertiaShell.jsx';
import { resolveInertiaLayoutChild } from '../../lib/resolveInertiaLayoutChild.js';

/**
 * @param {object} props
 * @param {string} [props.customerName]
 * @param {string} [props.homeUrl]
 * @param {string} [props.profileUrl]
 * @param {string} [props.mealPlanSummaryUrl]
 * @param {string} [props.shopUrl]
 * @param {string} [props.cartUrl]
 * @param {string} [props.toolsUrl]
 */
export function ShopInner({
    customerName = '',
    homeUrl = '/app',
    profileUrl = '/app/profile',
    mealPlanSummaryUrl = '/app/meal-plan',
    shopUrl = '/app/shop',
    cartUrl = '/app/cart',
    toolsUrl = '/app/tools',
}) {
    return (
        <CustomerInertiaShell
            customerName={customerName}
            cartUrl={cartUrl}
            footerNav={
                <CustomerAppBottomNav
                    activeTab="shop"
                    homeUrl={homeUrl}
                    profileUrl={profileUrl}
                    mealsUrl={mealPlanSummaryUrl}
                    shopUrl={shopUrl}
                    toolsUrl={toolsUrl}
                />
            }
        >
            <div className="w-full min-w-0 rounded-[16px] border border-gray-200 bg-white px-4 py-6 shadow-sm sm:p-8">
                <h1 className="font-montserrat text-3xl font-semibold tracking-tight text-[#262A22]">Shop</h1>
                <p className="mt-3 font-body text-sm leading-relaxed text-[#555555] sm:text-base">
                    Partner kitchen products and add-ons will show up here soon.
                </p>
            </div>
        </CustomerInertiaShell>
    );
}

/**
 * @param {object} props
 * @param {string} [props.customerName]
 * @param {string} [props.homeUrl]
 * @param {string} [props.profileUrl]
 * @param {string} [props.mealPlanSummaryUrl]
 * @param {string} [props.shopUrl]
 * @param {string} [props.cartUrl]
 * @param {string} [props.toolsUrl]
 */
export default function Shop({
    customerName = '',
    homeUrl = '/app',
    profileUrl = '/app/profile',
    mealPlanSummaryUrl = '/app/meal-plan',
    shopUrl = '/app/shop',
    cartUrl = '/app/cart',
    toolsUrl = '/app/tools',
}) {
    return (
        <ShopInner
            customerName={customerName}
            homeUrl={homeUrl}
            profileUrl={profileUrl}
            mealPlanSummaryUrl={mealPlanSummaryUrl}
            shopUrl={shopUrl}
            cartUrl={cartUrl}
            toolsUrl={toolsUrl}
        />
    );
}

Shop.layout = (pageOrProps) => resolveInertiaLayoutChild(pageOrProps);
