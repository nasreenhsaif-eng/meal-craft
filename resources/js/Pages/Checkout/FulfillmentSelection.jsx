import { Link } from '@inertiajs/react';
import CustomerAppHeaderActions from '../../Components/Molecules/Customer/CustomerAppHeaderActions.jsx';
import FulfillmentOptionCards from '../../Components/Molecules/Customer/FulfillmentOptionCards.jsx';
import CustomerInertiaShell from '../../Layouts/CustomerInertiaShell.jsx';
import { resolveInertiaLayoutChild } from '../../lib/resolveInertiaLayoutChild.js';

/**
 * @param {object} props
 * @param {string} [props.customerName]
 * @param {string} [props.homeUrl]
 * @param {string} [props.deliveryUrl]
 * @param {string} [props.recipesUrl]
 * @param {import('react').ReactNode} [props.headerActions]
 */
export function FulfillmentSelectionInner({
    customerName = '',
    homeUrl = '/app',
    deliveryUrl = '/checkout/delivery',
    recipesUrl = '/meal-plan/recipes',
    headerActions = null,
}) {
    return (
        <CustomerInertiaShell customerName={customerName} headerActions={headerActions}>
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
 * @param {string} [props.deliveryUrl]
 * @param {string} [props.recipesUrl]
 */
export default function FulfillmentSelection({
    customerName = '',
    homeUrl = '/app',
    deliveryUrl = '/checkout/delivery',
    recipesUrl = '/meal-plan/recipes',
}) {
    return (
        <FulfillmentSelectionInner
            customerName={customerName}
            homeUrl={homeUrl}
            deliveryUrl={deliveryUrl}
            recipesUrl={recipesUrl}
            headerActions={<CustomerAppHeaderActions />}
        />
    );
}

FulfillmentSelection.layout = (pageOrProps) => resolveInertiaLayoutChild(pageOrProps);
