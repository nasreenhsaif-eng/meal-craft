import { Link, router } from '@inertiajs/react';
import PartnerKitchenCard from '../../Components/Checkout/PartnerKitchenCard.jsx';
import CustomerAppHeaderActions from '../../Components/Molecules/Customer/CustomerAppHeaderActions.jsx';
import CustomerInertiaShell from '../../Layouts/CustomerInertiaShell.jsx';
import { resolveInertiaLayoutChild } from '../../lib/resolveInertiaLayoutChild.js';

/**
 * @param {object} props
 * @param {string} [props.customerName]
 * @param {string} [props.fulfillmentUrl]
 * @param {string} [props.detailsUrl]
 * @param {string} [props.homeUrl]
 */
export default function Delivery({
    customerName = '',
    fulfillmentUrl = '/checkout',
    detailsUrl = '/checkout/details',
    homeUrl = '/app',
}) {
    return (
        <CustomerInertiaShell customerName={customerName} headerActions={<CustomerAppHeaderActions />}>
            <div className="mx-auto w-full max-w-3xl">
                <Link
                    href={fulfillmentUrl}
                    className="inline-flex items-center gap-1 font-montserrat text-sm font-semibold text-[#5A6B44] hover:underline"
                >
                    ← Back to fulfillment
                </Link>
                <h1 className="mt-3 font-montserrat text-3xl font-semibold tracking-tight text-brand-primary-pressed">
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

Delivery.layout = (pageOrProps) => resolveInertiaLayoutChild(pageOrProps);
