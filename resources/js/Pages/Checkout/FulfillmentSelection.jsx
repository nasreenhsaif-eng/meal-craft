import { Link } from '@inertiajs/react';
import CustomerAppBottomNav from '../../Components/Molecules/Customer/CustomerAppBottomNav.jsx';
import FulfillmentOptionCards from '../../Components/Molecules/Customer/FulfillmentOptionCards.jsx';
import CustomerInertiaShell from '../../Layouts/CustomerInertiaShell.jsx';

/**
 * @param {object} props
 * @param {string} [props.customerName]
 * @param {string} [props.homeUrl]
 * @param {string} [props.profileUrl]
 * @param {string} [props.shopUrl]
 * @param {string} [props.mealPlanSummaryUrl]
 * @param {string} [props.deliveryUrl]
 * @param {string} [props.recipesUrl]
 */
export function FulfillmentSelectionInner({
    customerName = '',
    homeUrl = '/app',
    profileUrl = '/app/profile',
    shopUrl = '/app/shop',
    mealPlanSummaryUrl = '/app/meal-plan',
    deliveryUrl = '/checkout/delivery',
    recipesUrl = '/meal-plan/recipes',
}) {
    return (
        <CustomerInertiaShell
            customerName={customerName}
            footerNav={
                <CustomerAppBottomNav
                    homeUrl={homeUrl}
                    profileUrl={profileUrl}
                    shopUrl={shopUrl}
                    mealsUrl={mealPlanSummaryUrl}
                />
            }
        >
            <div className="mx-auto w-full max-w-5xl">
                <Link
                    href={homeUrl}
                    className="inline-flex items-center gap-1 font-montserrat text-sm font-semibold text-[#5A6B44] hover:underline"
                >
                    ← Back to dashboard
                </Link>
                <h1 className="mt-3 font-montserrat text-3xl font-semibold tracking-tight text-[#262A22]">
                    How would you like to receive your meal plan?
                </h1>
                <p className="mt-3 max-w-2xl font-body text-sm leading-relaxed text-[#555555] sm:text-base">
                    Choose between fresh daily preparation delivered to your door or self-prepared recipes at home.
                </p>

                <FulfillmentOptionCards deliveryUrl={deliveryUrl} className="mt-8" />
            </div>
        </CustomerInertiaShell>
    );
}

/**
 * @param {object} props
 * @param {string} [props.customerName]
 * @param {string} [props.homeUrl]
 * @param {string} [props.profileUrl]
 * @param {string} [props.shopUrl]
 * @param {string} [props.mealPlanSummaryUrl]
 * @param {string} [props.deliveryUrl]
 * @param {string} [props.recipesUrl]
 */
export default function FulfillmentSelection({
    customerName = '',
    homeUrl = '/app',
    profileUrl = '/app/profile',
    shopUrl = '/app/shop',
    mealPlanSummaryUrl = '/app/meal-plan',
    deliveryUrl = '/checkout/delivery',
    recipesUrl = '/meal-plan/recipes',
}) {
    return (
        <FulfillmentSelectionInner
            customerName={customerName}
            homeUrl={homeUrl}
            profileUrl={profileUrl}
            shopUrl={shopUrl}
            mealPlanSummaryUrl={mealPlanSummaryUrl}
            deliveryUrl={deliveryUrl}
            recipesUrl={recipesUrl}
        />
    );
}

