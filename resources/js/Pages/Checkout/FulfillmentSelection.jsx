import SimpleFlowNavHeader from '../../Components/Molecules/Onboarding/SimpleFlowNavHeader.jsx';
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
        <CustomerInertiaShell customerName={customerName} layoutVariant="onboarding" footerNav={null}>
            <section className="mx-auto flex w-full max-w-xl flex-col gap-3 px-1 sm:px-0">
                <SimpleFlowNavHeader
                    onBack={() => {
                        window.location.assign(homeUrl);
                    }}
                />

                <div className="w-full">
                    <h1 className="text-center font-montserrat text-2xl font-semibold leading-tight text-[#262A22]">
                        How would you like to receive your meal plan?
                    </h1>
                    <p className="mt-1.5 text-center text-sm leading-snug text-[#555555]">
                        Choose between fresh daily preparation delivered to your door or self-prepared recipes at
                        home.
                    </p>

                    <FulfillmentOptionCards
                        deliveryUrl={deliveryUrl}
                        recipesUrl={recipesUrl}
                        className="mt-3"
                    />
                </div>
            </section>
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
