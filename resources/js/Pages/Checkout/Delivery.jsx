import { router } from '@inertiajs/react';
import PartnerKitchenCard from '../../Components/Checkout/PartnerKitchenCard.jsx';
import SimpleFlowNavHeader from '../../Components/Molecules/Onboarding/SimpleFlowNavHeader.jsx';
import CustomerInertiaShell from '../../Layouts/CustomerInertiaShell.jsx';
import { resolveCsrfToken } from '../../lib/csrfToken.js';

/**
 * @param {number|string|null|undefined} amount
 * @param {number} [decimals]
 */
function formatBhd(amount, decimals = 1) {
    const value = Number(amount);

    if (!Number.isFinite(value)) {
        return `BHD ${(0).toFixed(decimals)}`;
    }

    return `BHD ${value.toFixed(decimals)}`;
}

/**
 * @param {object} props
 * @param {string} [props.customerName]
 * @param {string} [props.detailsUrl]
 * @param {string} [props.planWeeksUrl]
 * @param {string} [props.homeUrl]
 * @param {string} [props.profileUrl]
 * @param {string} [props.shopUrl]
 * @param {string} [props.mealPlanSummaryUrl]
 * @param {number} [props.selectedPlanWeeks]
 * @param {number} [props.selectedQuote]
 * @param {number} [props.billedDays]
 * @param {string|null} [props.craftTitle]
 */
export default function Delivery({
    customerName = '',
    detailsUrl = '/checkout/details',
    planWeeksUrl = '/checkout/delivery/plan-weeks',
    homeUrl = '/app',
    profileUrl = '/app/profile',
    shopUrl = '/app/shop',
    mealPlanSummaryUrl = '/app/meal-plan',
    selectedPlanWeeks = 4,
    selectedQuote = 0,
    billedDays = 28,
    craftTitle = null,
}) {
    const planWeeks = selectedPlanWeeks === 1 ? 1 : 4;
    const billingCadence = planWeeks === 1
        ? `1 week · ${billedDays} days`
        : `4 weeks · ${billedDays} days`;

    function selectKitchen() {
        const csrf = resolveCsrfToken();
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = planWeeksUrl;

        const token = document.createElement('input');
        token.type = 'hidden';
        token.name = '_token';
        token.value = csrf;
        form.appendChild(token);

        const weeks = document.createElement('input');
        weeks.type = 'hidden';
        weeks.name = 'plan_weeks';
        weeks.value = String(planWeeks);
        form.appendChild(weeks);

        document.body.appendChild(form);
        form.submit();
    }

    function goBack() {
        if (typeof window !== 'undefined' && window.history.length > 1) {
            window.history.back();

            return;
        }

        router.visit(homeUrl || profileUrl || detailsUrl || mealPlanSummaryUrl || shopUrl);
    }

    return (
        <CustomerInertiaShell customerName={customerName} layoutVariant="onboarding" footerNav={null}>
            <section className="mx-auto flex w-full max-w-xl flex-col gap-3 px-1 sm:px-0">
                <SimpleFlowNavHeader onBack={goBack} />

                <div className="w-full">
                    <h1 className="text-center font-montserrat text-2xl font-semibold leading-tight text-[#262A22]">
                        Select a partner kitchen
                    </h1>
                    <p className="mt-1.5 text-center text-sm leading-snug text-[#555555]">
                        Choose a kitchen for fresh daily preparation delivered to your door.
                        {craftTitle ? ` Pricing is for your ${craftTitle}.` : null}
                    </p>

                    <div className="mt-3 w-full">
                        <PartnerKitchenCard
                            name="Picniq"
                            logoUrl="/images/partners/picniq-gourmet.png"
                            rating={4.5}
                            reviewCount={128}
                            price={formatBhd(selectedQuote)}
                            billingCadence={billingCadence}
                            onSelect={selectKitchen}
                        />
                    </div>
                </div>
            </section>
        </CustomerInertiaShell>
    );
}
