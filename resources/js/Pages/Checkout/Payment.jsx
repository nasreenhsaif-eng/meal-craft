import { useMemo, useState } from 'react';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import Button from '../../Components/Atoms/Button/Button.jsx';
import SquareCheckbox from '../../Components/Atoms/Icons/SquareCheckbox.jsx';
import TextInput from '../../Components/Atoms/TextInput/TextInput.jsx';
import FoodFilterPill from '../../Components/MealSystem/FoodFilterPill.jsx';
import CustomerAppHeaderActions from '../../Components/Molecules/Customer/CustomerAppHeaderActions.jsx';
import CustomerInertiaShell from '../../Layouts/CustomerInertiaShell.jsx';
import { resolveInertiaLayoutChild } from '../../lib/resolveInertiaLayoutChild.js';

/**
 * @param {number | null | undefined} amount
 * @param {string} currency
 * @returns {string}
 */
function formatMoney(amount, currency = 'BHD') {
    const value = Number(amount);

    if (!Number.isFinite(value)) {
        return `${currency} —`;
    }

    return `${currency} ${value.toFixed(3)}`;
}

/**
 * @param {object} props
 * @param {string} [props.customerName]
 * @param {{
 *   planLabel?: string;
 *   averageDayCalories?: number;
 *   currency?: string;
 *   subtotal?: number;
 *   deliveryCost?: number;
 *   limitedTimeDiscount?: number;
 *   limitedTimeDiscountLabel?: string;
 *   promoDiscount?: number;
 *   promoCode?: string | null;
 *   total?: number;
 *   benefitPayNumber?: string;
 *   refundPolicyUrl?: string;
 *   deliveryAddress?: {
 *     formatted?: string;
 *     area?: string;
 *     block?: string;
 *     road?: string;
 *     houseNumber?: string;
 *     gateFlatNumber?: string;
 *     country?: string;
 *     deliveryTime?: string | null;
 *     plannedStartDate?: string | null;
 *   };
 * }} [props.basket]
 * @param {string} [props.detailsUrl]
 * @param {string} [props.homeUrl]
 * @param {string} [props.submitUrl]
 * @param {string} [props.applyPromoUrl]
 */
export default function Payment({
    customerName = '',
    basket = {},
    detailsUrl = '/checkout/details',
    homeUrl = '/app',
    submitUrl = '/checkout/payment',
    applyPromoUrl = '/checkout/payment',
}) {
    const page = usePage();
    const flashSuccess = typeof page.props?.flash?.success === 'string' ? page.props.flash.success : null;

    const currency = basket.currency ?? 'BHD';
    const [promoInput, setPromoInput] = useState(basket.promoCode ?? '');
    const [cartConfirmedLocal, setCartConfirmedLocal] = useState(false);

    const { data, setData, post, processing, errors, transform } = useForm({
        cart_confirmed: false,
        invoice_same_address: true,
        payment_method: 'benefit_pay',
        promo_code: basket.promoCode ?? '',
    });

    const totals = useMemo(() => {
        const subtotal = Number(basket.subtotal ?? 0);
        const deliveryCost = Number(basket.deliveryCost ?? 0);
        const limitedTimeDiscount = Number(basket.limitedTimeDiscount ?? 0);
        const promoDiscount = Number(basket.promoDiscount ?? 0);
        const total = Number(
            basket.total ?? Math.max(0, subtotal + deliveryCost - limitedTimeDiscount - promoDiscount),
        );

        return { subtotal, deliveryCost, limitedTimeDiscount, promoDiscount, total };
    }, [basket]);

    const address = basket.deliveryAddress ?? {};
    const canPay = data.cart_confirmed && data.payment_method === 'benefit_pay' && !processing;

    return (
        <CustomerInertiaShell customerName={customerName} headerActions={<CustomerAppHeaderActions />}>
            <div className="mx-auto w-full min-w-0 max-w-3xl">
                <Button
                    type="button"
                    label="← Back to your details"
                    variant="ghost"
                    size="sm"
                    onClick={() => router.visit(detailsUrl)}
                    className="!h-auto !min-h-0 px-0"
                />
                <h1 className="mt-3 font-montserrat text-3xl font-semibold tracking-tight text-[#262A22]">
                    Payment checkout
                </h1>
                <p className="mt-3 font-body text-sm leading-relaxed text-[#555555] sm:text-base">
                    Review your basket, confirm delivery, and pay with Benefit Pay.
                </p>
                {flashSuccess ? (
                    <p className="mt-4 font-body text-sm font-medium text-[#5A6B44]">{flashSuccess}</p>
                ) : null}

                <section className="mt-8 rounded-[12px] border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <h2 className="m-0 font-montserrat text-base font-bold tracking-tight text-[#262A22]">
                        Basket summary
                    </h2>
                    <p className="mt-3 font-body text-sm font-medium text-[#262A22]">
                        {basket.planLabel ?? 'Meal plan'}
                    </p>

                    <dl className="mt-5 space-y-3 border-t border-gray-100 pt-4 font-body text-sm">
                        <div className="flex items-start justify-between gap-4">
                            <dt className="text-[#555555]">Subtotal</dt>
                            <dd className="font-semibold text-[#262A22]">{formatMoney(totals.subtotal, currency)}</dd>
                        </div>
                        <div className="flex items-start justify-between gap-4">
                            <dt className="text-[#555555]">Delivery cost</dt>
                            <dd className="font-semibold text-[#262A22]">
                                {formatMoney(totals.deliveryCost, currency)}
                            </dd>
                        </div>
                        <div className="flex items-start justify-between gap-4">
                            <dt className="text-[#555555]">
                                Total discount
                                <span className="mt-0.5 block text-xs font-normal text-[#5A6B44]">
                                    {basket.limitedTimeDiscountLabel ?? 'Limited time offer'}
                                </span>
                            </dt>
                            <dd className="font-semibold text-[#5A6B44]">
                                −{formatMoney(totals.limitedTimeDiscount, currency)}
                            </dd>
                        </div>
                        {totals.promoDiscount > 0 ? (
                            <div className="flex items-start justify-between gap-4">
                                <dt className="text-[#555555]">
                                    Promo code
                                    <span className="mt-0.5 block text-xs font-normal text-[#5A6B44]">
                                        {basket.promoCode}
                                    </span>
                                </dt>
                                <dd className="font-semibold text-[#5A6B44]">
                                    −{formatMoney(totals.promoDiscount, currency)}
                                </dd>
                            </div>
                        ) : null}
                        <div className="flex items-start justify-between gap-4 border-t border-gray-100 pt-3">
                            <dt className="font-montserrat text-base font-bold text-[#262A22]">Total</dt>
                            <dd className="font-montserrat text-base font-bold text-[#262A22]">
                                {formatMoney(totals.total, currency)}
                            </dd>
                        </div>
                    </dl>

                    <div className="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end">
                        <TextInput
                            label="Enter discount code"
                            value={promoInput}
                            onChange={(event) => setPromoInput(event.target.value)}
                            className="w-full !max-w-full"
                            autoComplete="off"
                        />
                        <Button
                            type="button"
                            label="Apply"
                            variant="outline"
                            size="sm"
                            className="w-full shrink-0 border border-[#5A6B44] bg-transparent text-[#5A6B44] sm:w-auto"
                            onClick={() => {
                                const code = promoInput.trim();
                                router.get(
                                    applyPromoUrl,
                                    code !== '' ? { promo: code } : {},
                                    { preserveState: true, preserveScroll: true },
                                );
                            }}
                        />
                    </div>

                    <label className="mt-5 flex cursor-pointer select-none items-start gap-3">
                        <input
                            type="checkbox"
                            checked={Boolean(data.cart_confirmed)}
                            onChange={(event) => {
                                setData('cart_confirmed', event.target.checked);
                                setCartConfirmedLocal(event.target.checked);
                            }}
                            className="peer sr-only"
                        />
                        <span className="mt-0.5 inline-flex shrink-0 rounded-[4px] peer-focus-visible:ring-2 peer-focus-visible:ring-[#556C37] peer-focus-visible:ring-offset-2">
                            <SquareCheckbox presentational checked={Boolean(data.cart_confirmed)} />
                        </span>
                        <span className="font-montserrat text-sm font-bold uppercase leading-snug tracking-wide text-[#262A22]">
                            Confirm cart
                        </span>
                    </label>
                    {errors.cart_confirmed ? (
                        <p className="mt-2 text-sm text-status-error" role="alert">
                            {errors.cart_confirmed}
                        </p>
                    ) : null}
                    {cartConfirmedLocal ? (
                        <p className="mt-2 font-body text-xs text-[#5A6B44]">Cart confirmed.</p>
                    ) : null}
                </section>

                <section className="mt-5 rounded-[12px] border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <h2 className="m-0 font-montserrat text-base font-bold tracking-tight text-[#262A22]">
                        Delivery address
                    </h2>
                    <p className="mt-3 font-body text-sm leading-relaxed text-[#262A22]">
                        {address.formatted || '—'}
                    </p>
                    {address.plannedStartDate || address.deliveryTime ? (
                        <dl className="mt-4 grid gap-3 sm:grid-cols-2">
                            {address.plannedStartDate ? (
                                <div className="rounded-[12px] bg-[#F8F9F6] px-4 py-3">
                                    <dt className="font-montserrat text-xs font-bold uppercase tracking-wide text-[#555555]">
                                        Planned start
                                    </dt>
                                    <dd className="mt-1 font-body text-sm font-medium text-[#262A22]">
                                        {address.plannedStartDate}
                                    </dd>
                                </div>
                            ) : null}
                            {address.deliveryTime ? (
                                <div className="rounded-[12px] bg-[#F8F9F6] px-4 py-3">
                                    <dt className="font-montserrat text-xs font-bold uppercase tracking-wide text-[#555555]">
                                        Delivery time
                                    </dt>
                                    <dd className="mt-1 font-body text-sm font-medium capitalize text-[#262A22]">
                                        {String(address.deliveryTime).replaceAll('_', ' ')}
                                    </dd>
                                </div>
                            ) : null}
                        </dl>
                    ) : null}

                    <label className="mt-5 flex cursor-pointer select-none items-start gap-3">
                        <input
                            type="checkbox"
                            checked={Boolean(data.invoice_same_address)}
                            onChange={(event) => setData('invoice_same_address', event.target.checked)}
                            className="peer sr-only"
                        />
                        <span className="mt-0.5 inline-flex shrink-0 rounded-[4px] peer-focus-visible:ring-2 peer-focus-visible:ring-[#556C37] peer-focus-visible:ring-offset-2">
                            <SquareCheckbox presentational checked={Boolean(data.invoice_same_address)} />
                        </span>
                        <span className="font-body text-sm leading-snug text-[#262A22]">
                            Send invoice to the same address
                        </span>
                    </label>
                </section>

                <section className="mt-5 rounded-[12px] border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <h2 className="m-0 font-montserrat text-base font-bold tracking-tight text-[#262A22]">
                        Payment options
                    </h2>
                    <div className="mt-4 flex flex-wrap gap-2" role="group" aria-label="Payment options">
                        <FoodFilterPill
                            label={`Benefit Pay to ${basket.benefitPayNumber ?? '33177718'}`}
                            isActive={data.payment_method === 'benefit_pay'}
                            onClick={() => setData('payment_method', 'benefit_pay')}
                        />
                    </div>
                    {errors.payment_method ? (
                        <p className="mt-2 text-sm text-status-error" role="alert">
                            {errors.payment_method}
                        </p>
                    ) : null}

                    <p className="mt-5 font-body text-sm text-[#555555]">
                        <a
                            href={basket.refundPolicyUrl || '/refund-policy'}
                            className="font-semibold text-[#5A6B44] underline decoration-[#5A6B44]/40 underline-offset-2 hover:decoration-[#5A6B44]"
                        >
                            Refund policy
                        </a>
                    </p>

                    <div className="mt-6 flex w-full justify-center">
                        <Button
                            type="button"
                            label={processing ? 'Processing…' : 'Make a payment'}
                            disabled={!canPay}
                            className="w-full min-w-[200px] max-w-sm uppercase tracking-[0.08em]"
                            onClick={() => {
                                transform((form) => ({
                                    ...form,
                                    promo_code: promoInput.trim(),
                                }));
                                post(submitUrl);
                            }}
                        />
                    </div>
                </section>

                <p className="mt-4 font-body text-xs text-[#6B7280]">
                    You can return to your dashboard anytime from{' '}
                    <Link href={homeUrl} className="font-semibold text-[#5A6B44] hover:underline">
                        Home
                    </Link>
                    .
                </p>
            </div>
        </CustomerInertiaShell>
    );
}

Payment.layout = (pageOrProps) => resolveInertiaLayoutChild(pageOrProps);
