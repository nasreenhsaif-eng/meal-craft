import { useMemo, useState } from 'react';
import PartnerKitchenCard from '../../Components/Checkout/PartnerKitchenCard.jsx';
import OnboardingOptionButton from '../../Components/Atoms/Button/OnboardingOptionButton.jsx';
import CustomerAppBottomNav from '../../Components/Molecules/Customer/CustomerAppBottomNav.jsx';
import CustomerInertiaShell from '../../Layouts/CustomerInertiaShell.jsx';
import { resolveCsrfToken } from '../../lib/csrfToken.js';

/**
 * @param {number|string|null|undefined} amount
 */
function formatBhd(amount) {
    const value = Number(amount);

    if (!Number.isFinite(value)) {
        return 'BHD 0.000';
    }

    return `BHD ${value.toFixed(3)}`;
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
 * @param {number} [props.quoteOneWeek]
 * @param {number} [props.quoteFourWeeks]
 * @param {number} [props.billedDaysOneWeek]
 * @param {number} [props.billedDaysFourWeeks]
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
    quoteOneWeek = 0,
    quoteFourWeeks = 0,
    billedDaysOneWeek = 7,
    billedDaysFourWeeks = 28,
    craftTitle = null,
}) {
    const [planWeeks, setPlanWeeks] = useState(selectedPlanWeeks === 1 ? 1 : 4);

    const selectedQuote = useMemo(
        () => (planWeeks === 1 ? Number(quoteOneWeek) : Number(quoteFourWeeks)),
        [planWeeks, quoteOneWeek, quoteFourWeeks],
    );

    const billingCadence = planWeeks === 1
        ? `1 week · ${billedDaysOneWeek} days`
        : `4 weeks · ${billedDaysFourWeeks} days`;

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
                    {craftTitle ? ` Pricing is for your ${craftTitle}.` : null}
                </p>

                <div className="mt-6 flex flex-col gap-2.5">
                    <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#555555]">
                        Plan length
                    </p>
                    <OnboardingOptionButton
                        label="1 week trial"
                        detail={formatBhd(quoteOneWeek)}
                        selected={planWeeks === 1}
                        onSelect={() => setPlanWeeks(1)}
                    />
                    <OnboardingOptionButton
                        label="4 weeks"
                        detail={formatBhd(quoteFourWeeks)}
                        selected={planWeeks === 4}
                        onSelect={() => setPlanWeeks(4)}
                    />
                </div>

                <div className="mt-8">
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
        </CustomerInertiaShell>
    );
}
