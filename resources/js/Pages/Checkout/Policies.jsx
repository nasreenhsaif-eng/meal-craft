import { router } from '@inertiajs/react';
import Button from '../../Components/Atoms/Button/Button.jsx';
import CustomerAppBottomNav from '../../Components/Molecules/Customer/CustomerAppBottomNav.jsx';
import CustomerInertiaShell from '../../Layouts/CustomerInertiaShell.jsx';

/**
 * @param {object} props
 * @param {string} [props.customerName]
 * @param {string} [props.homeUrl]
 * @param {string} [props.profileUrl]
 * @param {string} [props.shopUrl]
 * @param {string} [props.mealPlanSummaryUrl]
 * @param {string} [props.paymentUrl]
 * @param {string} [props.cartUrl]
 */
export default function Policies({
    customerName = '',
    homeUrl = '/app',
    profileUrl = '/app/profile',
    shopUrl = '/app/shop',
    mealPlanSummaryUrl = '/app/meal-plan',
    paymentUrl = '/checkout/payment',
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
                />
            }
        >
            <div className="mx-auto w-full min-w-0">
                <div className="flex items-start justify-between gap-3">
                    <h1 className="min-w-0 font-montserrat text-3xl font-semibold tracking-tight text-[#262A22]">
                        Policies
                    </h1>
                    <Button
                        type="button"
                        label="Back"
                        variant="ghost"
                        size="sm"
                        onClick={() => router.visit(paymentUrl)}
                        className="shrink-0"
                    />
                </div>
                <p className="mt-3 font-body text-sm leading-relaxed text-[#555555] sm:text-base">
                    Freeze and refund terms for your Meal Craft package.
                </p>

                <section
                    id="freeze"
                    className="mt-6 scroll-mt-4 rounded-[12px] border border-gray-200 bg-white p-5 shadow-sm"
                >
                    <h2 className="m-0 font-montserrat text-base font-bold tracking-tight">Freeze policy</h2>
                    <p className="mt-3 font-body text-sm leading-relaxed text-[#555555]">
                        If you wish to freeze your package, the packages are calculated as a 20 day package and a 24 day
                        package. The maximum total number of days to be frozen are 14 days.
                    </p>
                </section>

                <section
                    id="refund"
                    className="mt-5 scroll-mt-4 rounded-[12px] border border-gray-200 bg-white p-5 shadow-sm"
                >
                    <h2 className="m-0 font-montserrat text-base font-bold tracking-tight">Refund policy</h2>
                    <p className="mt-3 font-body text-sm leading-relaxed text-[#555555]">
                        As we do not offer a refund policy, we suggest that you do a 5 day trial, if for any reason during
                        the 20 day package you would like to stop please keep in mind that the credit is transferable to a
                        loved one.
                    </p>
                    <p className="mt-3 font-body text-sm leading-relaxed text-[#555555]">
                        By making the payment, you agree to the Refund Policy.
                    </p>
                </section>
            </div>
        </CustomerInertiaShell>
    );
}
