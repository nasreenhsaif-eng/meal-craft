import { useMemo, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import Button from '../../Components/Atoms/Button/Button.jsx';
import TextInput from '../../Components/Atoms/TextInput/TextInput.jsx';
import adminInertiaLayout from '../../lib/adminInertiaLayout.jsx';

/**
 * @param {{
 *   cards?: Array<{ id: number, scope: string, calorieRow: number, billedDays: number, amountBhd: number }>,
 *   discounts?: Array<{ id: number, label: string, kind: string, amountBhd: number, isActive: boolean }>,
 *   limitedTimeDiscount?: number,
 *   limitedTimeDiscountLabel?: string,
 *   deliveryCost?: number,
 *   currency?: string,
 * }} props
 */
export default function PartnerKitchenPricing({
    cards = [],
    discounts = [],
    limitedTimeDiscount = 20,
    limitedTimeDiscountLabel = 'Limited time offer',
    deliveryCost = 5,
    currency = 'BHD',
}) {
    const page = usePage();
    const flash = page.props?.flash ?? {};
    const [cardAmounts, setCardAmounts] = useState(() =>
        Object.fromEntries(cards.map((card) => [card.id, String(card.amountBhd)])),
    );
    const [limitedAmount, setLimitedAmount] = useState(String(limitedTimeDiscount));
    const [limitedLabel, setLimitedLabel] = useState(limitedTimeDiscountLabel);
    const [discountLabel, setDiscountLabel] = useState('');
    const [discountAmount, setDiscountAmount] = useState('');
    const [saving, setSaving] = useState(false);

    const craftCards = useMemo(
        () => cards.filter((card) => card.scope !== 'business'),
        [cards],
    );
    const businessCards = useMemo(
        () => cards.filter((card) => card.scope === 'business'),
        [cards],
    );
    const extraDiscounts = useMemo(
        () => discounts.filter((discount) => discount.kind === 'fixed'),
        [discounts],
    );

    function savePrices() {
        setSaving(true);
        router.put(
            '/admin/partner-kitchen-pricing',
            {
                cards: cards.map((card) => ({
                    id: card.id,
                    amount_bhd: Number(cardAmounts[card.id] ?? card.amountBhd),
                })),
                limited_time_discount: Number(limitedAmount),
                limited_time_discount_label: limitedLabel,
                delivery_cost: Number(deliveryCost),
            },
            {
                preserveScroll: true,
                onFinish: () => setSaving(false),
            },
        );
    }

    function addDiscount() {
        if (discountLabel.trim() === '' || discountAmount.trim() === '') {
            return;
        }

        router.post(
            '/admin/partner-kitchen-pricing/discounts',
            {
                label: discountLabel.trim(),
                amount_bhd: Number(discountAmount),
                is_active: true,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setDiscountLabel('');
                    setDiscountAmount('');
                },
            },
        );
    }

    return (
        <div className="mx-auto w-full max-w-5xl space-y-8">
            <header>
                <h1 className="font-montserrat text-2xl font-bold tracking-tight text-[#262A22]">
                    Partner Kitchen pricing
                </h1>
                <p className="mt-2 font-body text-sm text-[#555555]">
                    Edit the seeded {currency} price cards customers see at consultation and checkout.
                    Delivery stays at {currency} {Number(deliveryCost).toFixed(3)}.
                </p>
                {typeof flash.success === 'string' && flash.success !== '' ? (
                    <p className="mt-3 rounded-[10px] bg-[#E8EDE3] px-3 py-2 font-body text-sm text-[#5A6B44]">
                        {flash.success}
                    </p>
                ) : null}
            </header>

            <section className="rounded-[12px] border border-gray-200 bg-white p-5 shadow-sm">
                <h2 className="font-montserrat text-sm font-bold uppercase tracking-[0.14em] text-[#555555]">
                    Limited time offer
                </h2>
                <div className="mt-4 grid gap-3 sm:grid-cols-2">
                    <TextInput
                        label="Label"
                        value={limitedLabel}
                        onChange={(event) => setLimitedLabel(event.target.value)}
                    />
                    <TextInput
                        label={`Amount (${currency})`}
                        value={limitedAmount}
                        onChange={(event) => setLimitedAmount(event.target.value)}
                    />
                </div>
            </section>

            <section className="rounded-[12px] border border-gray-200 bg-white p-5 shadow-sm">
                <h2 className="font-montserrat text-sm font-bold uppercase tracking-[0.14em] text-[#555555]">
                    Calorie crafts (Full, Afternoon, Day, Intermittent)
                </h2>
                <div className="mt-4 overflow-x-auto">
                    <table className="min-w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-gray-200 text-[#555555]">
                                <th className="px-2 py-2 font-montserrat text-xs uppercase">Craft</th>
                                <th className="px-2 py-2 font-montserrat text-xs uppercase">kcal</th>
                                <th className="px-2 py-2 font-montserrat text-xs uppercase">Days</th>
                                <th className="px-2 py-2 font-montserrat text-xs uppercase">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            {craftCards.map((card) => (
                                <tr key={card.id} className="border-b border-gray-100">
                                    <td className="px-2 py-2 capitalize">{card.scope}</td>
                                    <td className="px-2 py-2">{card.calorieRow}</td>
                                    <td className="px-2 py-2">{card.billedDays}</td>
                                    <td className="px-2 py-2">
                                        <input
                                            className="w-28 rounded-[8px] border border-gray-200 px-2 py-1"
                                            value={cardAmounts[card.id] ?? ''}
                                            onChange={(event) =>
                                                setCardAmounts((prev) => ({
                                                    ...prev,
                                                    [card.id]: event.target.value,
                                                }))
                                            }
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>

            <section className="rounded-[12px] border border-gray-200 bg-white p-5 shadow-sm">
                <h2 className="font-montserrat text-sm font-bold uppercase tracking-[0.14em] text-[#555555]">
                    Business Craft
                </h2>
                <div className="mt-4 overflow-x-auto">
                    <table className="min-w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-gray-200 text-[#555555]">
                                <th className="px-2 py-2 font-montserrat text-xs uppercase">Days</th>
                                <th className="px-2 py-2 font-montserrat text-xs uppercase">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            {businessCards.map((card) => (
                                <tr key={card.id} className="border-b border-gray-100">
                                    <td className="px-2 py-2">{card.billedDays}</td>
                                    <td className="px-2 py-2">
                                        <input
                                            className="w-28 rounded-[8px] border border-gray-200 px-2 py-1"
                                            value={cardAmounts[card.id] ?? ''}
                                            onChange={(event) =>
                                                setCardAmounts((prev) => ({
                                                    ...prev,
                                                    [card.id]: event.target.value,
                                                }))
                                            }
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>

            <div>
                <Button label={saving ? 'Saving…' : 'Save prices'} onClick={savePrices} disabled={saving} />
            </div>

            <section className="rounded-[12px] border border-gray-200 bg-white p-5 shadow-sm">
                <h2 className="font-montserrat text-sm font-bold uppercase tracking-[0.14em] text-[#555555]">
                    Extra cart discounts
                </h2>
                <p className="mt-1 font-body text-xs text-[#555555]">
                    Fixed {currency} amounts applied on the Partner Kitchen basket.
                </p>
                <div className="mt-4 grid gap-3 sm:grid-cols-[1fr_140px_auto]">
                    <TextInput
                        label="Label"
                        value={discountLabel}
                        onChange={(event) => setDiscountLabel(event.target.value)}
                    />
                    <TextInput
                        label="Amount"
                        value={discountAmount}
                        onChange={(event) => setDiscountAmount(event.target.value)}
                    />
                    <div className="flex items-end">
                        <Button label="Add discount" onClick={addDiscount} />
                    </div>
                </div>
                <ul className="mt-4 space-y-2">
                    {extraDiscounts.map((discount) => (
                        <li
                            key={discount.id}
                            className="flex items-center justify-between rounded-[10px] border border-gray-100 px-3 py-2"
                        >
                            <span className="font-body text-sm text-[#262A22]">
                                {discount.label} · {currency} {Number(discount.amountBhd).toFixed(3)}
                                {discount.isActive ? '' : ' (off)'}
                            </span>
                            <Button
                                label="Remove"
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    router.delete(`/admin/partner-kitchen-pricing/discounts/${discount.id}`, {
                                        preserveScroll: true,
                                    })
                                }
                            />
                        </li>
                    ))}
                </ul>
            </section>
        </div>
    );
}

PartnerKitchenPricing.layout = adminInertiaLayout;
