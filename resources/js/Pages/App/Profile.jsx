import { Link, router } from '@inertiajs/react';
import CustomerAppBottomNav from '../../Components/Molecules/Customer/CustomerAppBottomNav.jsx';
import CustomerDetailSection, {
    displayValue,
} from '../../Components/Molecules/Customer/CustomerDetailSection.jsx';
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
 * @param {string} [props.contactEditUrl]
 * @param {string} [props.logoutUrl]
 * @param {{
 *   name?: string;
 *   email?: string | null;
 *   phone?: string | null;
 *   address?: string | null;
 *   deliveryAddress?: string | null;
 *   cards?: string[] | null;
 * } | null} [props.details]
 * @param {{
 *   mealPlanName?: string | null;
 *   days?: number | null;
 *   startDate?: string | null;
 *   expiryDate?: string | null;
 *   deliveries?: number | null;
 * } | null} [props.subscription]
 */
export function ProfileInner({
    customerName = '',
    homeUrl = '/app',
    profileUrl = '/app/profile',
    shopUrl = '/app/shop',
    mealPlanSummaryUrl = '/app/meal-plan',
    toolsUrl = '/app/tools',
    cartUrl = '/app/cart',
    contactEditUrl = '/checkout/details',
    logoutUrl = '/logout',
    details = null,
    subscription = null,
}) {
    const cardsDisplay =
        Array.isArray(details?.cards) && details.cards.length > 0
            ? details.cards.join(', ')
            : 'None saved';

    /** @type {{ label: string; value: string; preserveCase?: boolean }[]} */
    const contactRows = [
        { label: 'Name', value: displayValue(details?.name || customerName) },
        { label: 'Email', value: displayValue(details?.email), preserveCase: true },
        { label: 'Phone', value: displayValue(details?.phone), preserveCase: true },
        { label: 'Address', value: displayValue(details?.address) },
        {
            label: 'Delivery address',
            value: displayValue(details?.deliveryAddress),
            preserveCase: true,
        },
        { label: 'Cards', value: cardsDisplay, preserveCase: true },
    ];

    /** @type {{ label: string; value: string; preserveCase?: boolean }[]} */
    const subscriptionRows = [
        { label: 'Meal plan', value: displayValue(subscription?.mealPlanName) },
        { label: 'Days', value: displayValue(subscription?.days) },
        {
            label: 'Start date',
            value: displayValue(subscription?.startDate),
            preserveCase: true,
        },
        {
            label: 'Expiry date',
            value: displayValue(subscription?.expiryDate),
            preserveCase: true,
        },
        { label: 'Deliveries', value: displayValue(subscription?.deliveries) },
    ];

    return (
        <CustomerInertiaShell
            customerName={customerName}
            cartUrl={cartUrl}
            footerNav={
                <CustomerAppBottomNav
                    activeTab="profile"
                    homeUrl={homeUrl}
                    profileUrl={profileUrl}
                    shopUrl={shopUrl}
                    mealsUrl={mealPlanSummaryUrl}
                    toolsUrl={toolsUrl}
                />
            }
        >
            <div className="flex w-full min-w-0 flex-col gap-4">
                <h1 className="font-montserrat text-3xl font-semibold tracking-tight text-[#262A22]">
                    Your details
                </h1>

                <CustomerDetailSection
                    title="Contact"
                    rows={contactRows}
                    action={
                        <Link
                            href={contactEditUrl}
                            className="shrink-0 pt-0.5 font-montserrat text-sm font-semibold text-[#5A6B44] hover:underline"
                        >
                            Edit
                        </Link>
                    }
                />

                <CustomerDetailSection title="Subscriptions" rows={subscriptionRows} />

                <div className="mt-2 flex justify-center pb-2">
                    <button
                        type="button"
                        onClick={() => router.post(logoutUrl)}
                        className="font-montserrat text-sm font-semibold text-[#6B7280] underline decoration-[#6B7280]/40 underline-offset-2 hover:text-[#374151] hover:decoration-[#374151]"
                    >
                        Sign out
                    </button>
                </div>
            </div>
        </CustomerInertiaShell>
    );
}

/**
 * @param {object} props
 */
export default function Profile(props) {
    return <ProfileInner {...props} />;
}

Profile.layout = (pageOrProps) => resolveInertiaLayoutChild(pageOrProps);
