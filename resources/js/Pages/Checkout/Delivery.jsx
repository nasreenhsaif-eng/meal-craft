import { router } from '@inertiajs/react';
import PartnerKitchenCard from '../../Components/Checkout/PartnerKitchenCard.jsx';
import CustomerAppBottomNav from '../../Components/Molecules/Customer/CustomerAppBottomNav.jsx';
import CustomerInertiaShell from '../../Layouts/CustomerInertiaShell.jsx';

/**
 * @param {object} props
 * @param {string} [props.customerName]
 * @param {string} [props.detailsUrl]
 * @param {string} [props.homeUrl]
 * @param {string} [props.profileUrl]
 * @param {string} [props.shopUrl]
 * @param {string} [props.mealPlanSummaryUrl]
 */
export default function Delivery({
    customerName = '',
    detailsUrl = '/checkout/details',
    homeUrl = '/app',
    profileUrl = '/app/profile',
    shopUrl = '/app/shop',
    mealPlanSummaryUrl = '/app/meal-plan',
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
            <div className="mx-auto w-full max-w-3xl">
                <h1 className="font-montserrat text-3xl font-semibold tracking-tight text-brand-primary-pressed">
                    Select a partner kitchen
                </h1>
                <p className="mt-3 font-body text-sm leading-relaxed text-grey-33 sm:text-base">
                    Choose a kitchen for fresh daily preparation delivered to your door.
                </p>

                <div className="mt-8">
                    <PartnerKitchenCard
                        name="Picniq"
                        logoUrl="/images/partners/picniq-gourmet.png"
                        rating={4.5}
                        reviewCount={128}
                        price="BHD 385"
                        billingCadence="week"
                        onSelect={() => router.visit(detailsUrl)}
                    />
                </div>
            </div>
        </CustomerInertiaShell>
    );
}

