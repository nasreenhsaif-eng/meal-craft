import { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import Button from '../../Components/Atoms/Button/Button.jsx';
import CSVUploader from '../../Components/CSVUploader.jsx';
import DropdownTextInput from '../../Components/Atoms/TextInput/DropdownTextInput.jsx';
import TextInput from '../../Components/Atoms/TextInput/TextInput.jsx';
import adminInertiaLayout from '../../lib/adminInertiaLayout.jsx';

/** @type {Array<{ value: string, label: string }>} */
const CRAFT_FILTERS = [
    { value: 'all', label: 'All crafts' },
    { value: 'full', label: 'Full' },
    { value: 'afternoon', label: 'Afternoon' },
    { value: 'day', label: 'Day' },
    { value: 'intermittent', label: 'Intermittent' },
    { value: 'business', label: 'Business' },
];

/** @type {Array<{ value: string, label: string }>} */
const DAY_FILTERS = [
    { value: 'all', label: 'All days' },
    { value: '5', label: '5' },
    { value: '6', label: '6' },
    { value: '7', label: '7' },
    { value: '20', label: '20' },
    { value: '24', label: '24' },
    { value: '28', label: '28' },
];

/**
 * @param {Array<{ value: string, label: string }>} options
 * @param {string} value
 */
function labelForOption(options, value) {
    return options.find((option) => option.value === value)?.label ?? options[0]?.label ?? '';
}

/**
 * @param {Array<{ value: string, label: string }>} options
 * @param {string} label
 */
function valueForLabel(options, label) {
    return options.find((option) => option.label === label)?.value ?? options[0]?.value ?? '';
}

/**
 * @param {number} amount
 * @param {number} discount
 * @param {'amount' | 'percent'} type
 */
function computeNet(amount, discount, type) {
    const safeAmount = Number.isFinite(amount) ? amount : 0;
    const safeDiscount = Number.isFinite(discount) ? Math.max(0, discount) : 0;

    if (type === 'percent') {
        const percent = Math.min(100, safeDiscount);

        return Math.max(0, safeAmount * (1 - percent / 100));
    }

    return Math.max(0, safeAmount - safeDiscount);
}

/**
 * @param {{
 *   cards?: Array<{
 *     id: number,
 *     scope: string,
 *     calorieRow: number,
 *     billedDays: number,
 *     amountBhd: number,
 *     discountType?: 'amount' | 'percent',
 *     discountBhd?: number
 *   }>,
 *   limitedTimeDiscount?: number,
 *   limitedTimeDiscountLabel?: string,
 *   deliveryCost?: number,
 *   currency?: string,
 *   csvExportUrl?: string,
 *   csvImportUrl?: string,
 * }} props
 */
export default function PartnerKitchenPricing({
    cards = [],
    limitedTimeDiscount = 20,
    limitedTimeDiscountLabel = 'Limited time offer',
    deliveryCost = 5,
    currency = 'BHD',
    csvExportUrl = '',
    csvImportUrl = '',
}) {
    const [cardAmounts, setCardAmounts] = useState(() =>
        Object.fromEntries(cards.map((card) => [card.id, String(card.amountBhd)])),
    );
    const [cardDiscountTypes, setCardDiscountTypes] = useState(() =>
        Object.fromEntries(
            cards.map((card) => [card.id, card.discountType === 'percent' ? 'percent' : 'amount']),
        ),
    );
    const [cardDiscounts, setCardDiscounts] = useState(() =>
        Object.fromEntries(cards.map((card) => [card.id, String(card.discountBhd ?? 0)])),
    );
    const [limitedLabel, setLimitedLabel] = useState(limitedTimeDiscountLabel);
    const [offerType, setOfferType] = useState(
        /** @type {'amount' | 'percent'} */ ('amount'),
    );
    const [offerValue, setOfferValue] = useState(String(limitedTimeDiscount));
    const [offerCraft, setOfferCraft] = useState('all');
    const [offerDays, setOfferDays] = useState('all');
    const [saving, setSaving] = useState(false);
    const [craftFilter, setCraftFilter] = useState('full');
    const [daysFilter, setDaysFilter] = useState('all');
    const [kcalFilter, setKcalFilter] = useState('all');
    const [applyNotice, setApplyNotice] = useState('');

    const discountTypeOptions = [currency, '%'];

    const kcalFilters = useMemo(() => {
        const rows = cards
            .filter((card) => {
                if (card.scope === 'business') {
                    return false;
                }

                if (craftFilter !== 'all' && card.scope !== craftFilter) {
                    return false;
                }

                return Number(card.calorieRow) > 0;
            })
            .map((card) => Number(card.calorieRow));

        const unique = [...new Set(rows)].sort((a, b) => a - b);

        return [
            { value: 'all', label: 'All kcal' },
            ...unique.map((row) => ({ value: String(row), label: String(row) })),
        ];
    }, [cards, craftFilter]);

    const filteredCards = useMemo(() => {
        return cards.filter((card) => {
            if (craftFilter !== 'all' && card.scope !== craftFilter) {
                return false;
            }

            if (daysFilter !== 'all' && String(card.billedDays) !== daysFilter) {
                return false;
            }

            if (kcalFilter !== 'all' && String(card.calorieRow) !== kcalFilter) {
                return false;
            }

            return true;
        });
    }, [cards, craftFilter, daysFilter, kcalFilter]);

    const showCalorieColumn = craftFilter !== 'business';
    const emptyColSpan = showCalorieColumn ? 6 : 5;
    const activeKcalFilter = kcalFilters.some((filter) => filter.value === kcalFilter)
        ? kcalFilter
        : 'all';

    /**
     * @param {number} cardId
     * @param {number} fallbackAmount
     * @param {number} [fallbackDiscount]
     * @param {'amount' | 'percent'} [fallbackType]
     */
    function netAmountValue(cardId, fallbackAmount, fallbackDiscount = 0, fallbackType = 'amount') {
        const amount = Number(cardAmounts[cardId] ?? fallbackAmount);
        const discount = Number(cardDiscounts[cardId] ?? fallbackDiscount);
        const type = cardDiscountTypes[cardId] === 'percent' ? 'percent' : fallbackType;

        return computeNet(amount, discount, type === 'percent' ? 'percent' : 'amount');
    }

    /**
     * @param {number} cardId
     * @param {number} fallbackAmount
     * @param {number} billedDays
     * @param {number} [fallbackDiscount]
     * @param {'amount' | 'percent'} [fallbackType]
     */
    function pricePerDay(cardId, fallbackAmount, billedDays, fallbackDiscount = 0, fallbackType = 'amount') {
        const net = netAmountValue(cardId, fallbackAmount, fallbackDiscount, fallbackType);
        const days = Number(billedDays);

        if (!Number.isFinite(days) || days <= 0) {
            return '—';
        }

        return (net / days).toFixed(3);
    }

    function applyOfferDiscount() {
        const type = offerType === 'percent' ? 'percent' : 'amount';
        const value = offerValue.trim() === '' ? '0' : offerValue;
        const targets = cards.filter((card) => {
            if (offerCraft !== 'all' && card.scope !== offerCraft) {
                return false;
            }

            if (offerDays !== 'all' && String(card.billedDays) !== offerDays) {
                return false;
            }

            return true;
        });

        if (targets.length === 0) {
            setApplyNotice('No price cards match that craft and days selection.');

            return;
        }

        const nextTypes = { ...cardDiscountTypes };
        const nextDiscounts = { ...cardDiscounts };

        targets.forEach((card) => {
            nextTypes[card.id] = type;
            nextDiscounts[card.id] = value;
        });

        setCardDiscountTypes(nextTypes);
        setCardDiscounts(nextDiscounts);

        const craftLabel = labelForOption(CRAFT_FILTERS, offerCraft);
        const daysLabel = offerDays === 'all' ? 'all days' : `${offerDays} days`;

        setApplyNotice(
            `Discount applied to ${targets.length} cards (${craftLabel}, ${daysLabel}). Save prices to keep.`,
        );
    }

    function savePrices() {
        setSaving(true);
        setApplyNotice('');
        router.put(
            '/admin/partner-kitchen-pricing',
            {
                cards: cards.map((card) => ({
                    id: card.id,
                    amount_bhd: Number(cardAmounts[card.id] ?? card.amountBhd),
                    discount_type: cardDiscountTypes[card.id] === 'percent' ? 'percent' : 'amount',
                    discount_bhd: Number(cardDiscounts[card.id] ?? card.discountBhd ?? 0),
                })),
                limited_time_discount:
                    offerType === 'amount' ? Number(offerValue) : Number(limitedTimeDiscount),
                limited_time_discount_label: limitedLabel,
                delivery_cost: Number(deliveryCost),
            },
            {
                preserveScroll: true,
                onFinish: () => setSaving(false),
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
            </header>

            <section className="rounded-[12px] border border-gray-200 bg-white p-5 shadow-sm">
                <h2 className="font-montserrat text-sm font-bold uppercase tracking-[0.14em] text-[#555555]">
                    Limited time offer
                </h2>
                <p className="mt-2 font-body text-sm text-[#6B7280]">
                    Set the offer label and discount, then apply it to the crafts and plan lengths you
                    choose. The price chart stays for editing amounts only.
                </p>

                <div className="mt-4 grid gap-3 sm:grid-cols-2">
                    <TextInput
                        label="Label"
                        value={limitedLabel}
                        onChange={(event) => setLimitedLabel(event.target.value)}
                        className="!max-w-none"
                    />
                    <div className="grid grid-cols-[7.5rem_minmax(0,1fr)] gap-3">
                        <DropdownTextInput
                            label="Type"
                            value={offerType === 'percent' ? '%' : currency}
                            options={discountTypeOptions}
                            onChange={(label) =>
                                setOfferType(label === '%' ? 'percent' : 'amount')
                            }
                            listboxAriaLabel="Discount type"
                            className="!max-w-none"
                        />
                        <TextInput
                            label="Discount"
                            value={offerValue}
                            onChange={(event) => setOfferValue(event.target.value)}
                            className="!max-w-none"
                        />
                    </div>
                    <DropdownTextInput
                        label="Apply to craft"
                        value={labelForOption(CRAFT_FILTERS, offerCraft)}
                        options={CRAFT_FILTERS.map((filter) => filter.label)}
                        onChange={(label) => setOfferCraft(valueForLabel(CRAFT_FILTERS, label))}
                        listboxAriaLabel="Apply discount to craft"
                        className="!max-w-none"
                    />
                    <DropdownTextInput
                        label="Apply to days"
                        value={labelForOption(DAY_FILTERS, offerDays)}
                        options={DAY_FILTERS.map((filter) => filter.label)}
                        onChange={(label) => setOfferDays(valueForLabel(DAY_FILTERS, label))}
                        listboxAriaLabel="Apply discount to days"
                        className="!max-w-none"
                    />
                </div>

                <div className="mt-4 flex flex-wrap items-center gap-3">
                    <Button label="Apply discount" onClick={applyOfferDiscount} />
                    {applyNotice !== '' ? (
                        <p className="rounded-[10px] bg-[#E8EDE3] px-3 py-2 font-body text-sm text-[#5A6B44]">
                            {applyNotice}
                        </p>
                    ) : null}
                </div>
            </section>

            <section className="rounded-[12px] border border-gray-200 bg-white p-5 shadow-sm">
                <h2 className="font-montserrat text-sm font-bold uppercase tracking-[0.14em] text-[#555555]">
                    Price chart
                </h2>
                {csvExportUrl !== '' || csvImportUrl !== '' ? (
                    <div className="mt-4">
                        <CSVUploader
                            exportUrl={csvExportUrl !== '' ? csvExportUrl : undefined}
                            onUpload={(file) => {
                                if (csvImportUrl === '') {
                                    return;
                                }

                                router.post(
                                    csvImportUrl,
                                    { file },
                                    { forceFormData: true, preserveScroll: true },
                                );
                            }}
                        />
                    </div>
                ) : null}

                <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <DropdownTextInput
                        label="Craft"
                        value={labelForOption(CRAFT_FILTERS, craftFilter)}
                        options={CRAFT_FILTERS.map((filter) => filter.label)}
                        onChange={(label) => {
                            setCraftFilter(valueForLabel(CRAFT_FILTERS, label));
                            setKcalFilter('all');
                        }}
                        listboxAriaLabel="Filter by craft"
                        className="!max-w-none"
                    />
                    {showCalorieColumn ? (
                        <DropdownTextInput
                            label="kcal"
                            value={labelForOption(kcalFilters, activeKcalFilter)}
                            options={kcalFilters.map((filter) => filter.label)}
                            onChange={(label) =>
                                setKcalFilter(valueForLabel(kcalFilters, label))
                            }
                            listboxAriaLabel="Filter by kcal"
                            className="!max-w-none"
                        />
                    ) : null}
                    <DropdownTextInput
                        label="Days"
                        value={labelForOption(DAY_FILTERS, daysFilter)}
                        options={DAY_FILTERS.map((filter) => filter.label)}
                        onChange={(label) => setDaysFilter(valueForLabel(DAY_FILTERS, label))}
                        listboxAriaLabel="Filter by days"
                        className="!max-w-none"
                    />
                </div>

                <div className="mt-4 overflow-x-auto">
                    <table className="min-w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-gray-200 bg-[#F8F9F6] text-[#555555]">
                                <th className="px-4 py-3 font-montserrat text-[11px] font-semibold uppercase tracking-[0.14em]">
                                    Craft
                                </th>
                                {showCalorieColumn ? (
                                    <th className="px-4 py-3 font-montserrat text-[11px] font-semibold uppercase tracking-[0.14em]">
                                        kcal
                                    </th>
                                ) : null}
                                <th className="px-4 py-3 font-montserrat text-[11px] font-semibold uppercase tracking-[0.14em]">
                                    Days
                                </th>
                                <th className="px-4 py-3 font-montserrat text-[11px] font-semibold uppercase tracking-[0.14em]">
                                    Amount
                                </th>
                                <th className="px-4 py-3 font-montserrat text-[11px] font-semibold uppercase tracking-[0.14em]">
                                    Net
                                </th>
                                <th className="px-4 py-3 font-montserrat text-[11px] font-semibold uppercase tracking-[0.14em]">
                                    Per day
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {filteredCards.map((card) => {
                                const fallbackType =
                                    card.discountType === 'percent' ? 'percent' : 'amount';

                                return (
                                    <tr key={card.id} className="border-b border-gray-100">
                                        <td className="px-4 py-3 capitalize">{card.scope}</td>
                                        {showCalorieColumn ? (
                                            <td className="px-4 py-3">
                                                {card.scope === 'business' ? '—' : card.calorieRow}
                                            </td>
                                        ) : null}
                                        <td className="px-4 py-3">{card.billedDays}</td>
                                        <td className="px-4 py-3">
                                            <TextInput
                                                label={`Amount for ${card.scope} ${card.billedDays} days`}
                                                value={cardAmounts[card.id] ?? ''}
                                                onChange={(event) =>
                                                    setCardAmounts((prev) => ({
                                                        ...prev,
                                                        [card.id]: event.target.value,
                                                    }))
                                                }
                                                className="!max-w-none [&_label]:sr-only"
                                            />
                                        </td>
                                        <td className="px-4 py-3 font-body text-[#262A22]">
                                            {netAmountValue(
                                                card.id,
                                                card.amountBhd,
                                                card.discountBhd ?? 0,
                                                fallbackType,
                                            ).toFixed(3)}
                                        </td>
                                        <td className="px-4 py-3 font-body text-[#262A22]">
                                            {pricePerDay(
                                                card.id,
                                                card.amountBhd,
                                                card.billedDays,
                                                card.discountBhd ?? 0,
                                                fallbackType,
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                            {filteredCards.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={emptyColSpan}
                                        className="px-4 py-6 font-body text-sm text-[#6B7280]"
                                    >
                                        No price cards for these filters.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>
            </section>

            <div>
                <Button label={saving ? 'Saving…' : 'Save prices'} onClick={savePrices} disabled={saving} />
            </div>
        </div>
    );
}

PartnerKitchenPricing.layout = adminInertiaLayout;
