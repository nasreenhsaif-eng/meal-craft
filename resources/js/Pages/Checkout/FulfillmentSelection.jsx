import { Link, router } from '@inertiajs/react';
import Button from '../../Components/Atoms/Button/Button.jsx';
import CustomerAppHeaderActions from '../../Components/Molecules/Customer/CustomerAppHeaderActions.jsx';
import CustomerInertiaShell from '../../Layouts/CustomerInertiaShell.jsx';
import { resolveInertiaLayoutChild } from '../../lib/resolveInertiaLayoutChild.js';

/**
 * @param {{ items: string[] }} props
 */
function HighlightList({ items }) {
    return (
        <ul className="mt-5 flex flex-1 flex-col gap-2.5">
            {items.map((item) => (
                <li key={item} className="flex items-start gap-2.5 font-body text-sm text-[#555555]">
                    <span
                        className="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#6E8C47]/15 text-[#5A6B44]"
                        aria-hidden="true"
                    >
                        <svg className="h-3 w-3" viewBox="0 0 12 12" fill="none">
                            <path
                                d="M2.5 6.2 4.7 8.5 9.5 3.5"
                                stroke="currentColor"
                                strokeWidth="1.75"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                            />
                        </svg>
                    </span>
                    <span>{item}</span>
                </li>
            ))}
        </ul>
    );
}

/**
 * @param {{
 *   badge: string;
 *   title: string;
 *   subtitle?: string;
 *   description: string;
 *   highlights: string[];
 *   buttonLabel: string;
 *   onSelect: () => void;
 * }} props
 */
function FulfillmentOptionCard({ badge, title, subtitle, description, highlights, buttonLabel, onSelect }) {
    return (
        <article className="flex h-full flex-col rounded-[16px] border border-gray-200 bg-white p-6 shadow-sm transition-all duration-200 ease-in-out hover:border-[#5A6B44]/40 hover:shadow-md sm:p-8">
            <span className="inline-flex w-fit items-center rounded-full bg-[#6E8C47]/10 px-3 py-1 font-montserrat text-[11px] font-bold uppercase tracking-[0.12em] text-[#5A6B44]">
                {badge}
            </span>
            <h2 className="mt-4 font-montserrat text-xl font-semibold text-[#262A22] sm:text-2xl">{title}</h2>
            {subtitle ? (
                <p className="mt-1 font-montserrat text-sm font-semibold text-[#5A6B44]">{subtitle}</p>
            ) : null}
            <p className="mt-3 font-body text-sm leading-relaxed text-[#555555]">{description}</p>
            <HighlightList items={highlights} />
            <div className="mt-6">
                <Button type="button" label={buttonLabel} onClick={onSelect} className="w-full" />
            </div>
        </article>
    );
}

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

                <div className="mt-8 grid grid-cols-1 gap-6 md:grid-cols-2">
                    <FulfillmentOptionCard
                        badge="Partner Kitchen"
                        title="Fresh Preparation & Delivery"
                        description="Portion-controlled meals tailored to your calorie and macro targets, prepared fresh and delivered daily."
                        highlights={[
                            'Ready-to-eat fresh meals',
                            'Prepared according to your dietary profile',
                            'Daily doorstep delivery',
                        ]}
                        buttonLabel="Select Active Kitchen"
                        onSelect={() => router.visit(deliveryUrl)}
                    />
                    <FulfillmentOptionCard
                        badge="Digital Access"
                        title="Cook It Yourself (DIY)"
                        subtitle="Instant Digital Plan"
                        description="Full access to detailed daily recipes, precise ingredient gram weights, and aggregated shopping lists."
                        highlights={[
                            'Step-by-step recipe instructions',
                            'Printable daily menus & shopping lists',
                            'Ingredient breakdowns matched to your targets',
                        ]}
                        buttonLabel="Unlock Digital Plan & Recipes"
                        onSelect={() => router.visit(recipesUrl)}
                    />
                </div>
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
