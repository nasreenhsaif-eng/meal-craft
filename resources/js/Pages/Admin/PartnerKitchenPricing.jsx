import { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import Button from '../../Components/Atoms/Button/Button.jsx';
import SquareCheckbox from '../../Components/Atoms/Icons/SquareCheckbox.jsx';
import CSVUploader from '../../Components/CSVUploader.jsx';
import DropdownTextInput from '../../Components/Atoms/TextInput/DropdownTextInput.jsx';
import TextInput from '../../Components/Atoms/TextInput/TextInput.jsx';
import adminInertiaLayout from '../../lib/adminInertiaLayout.jsx';

const DURATIONS = [5, 6, 7, 20, 24, 28];

/** Calorie curves — brand hues + light tints so each tier is chart-distinct. */
const TIER_COLORS = {
    650: '#6E8C47', // brand primary
    850: '#2F4C9B', // magnesium
    950: '#8F55A8', // accent
    1200: '#D8A933', // secondary
    1300: '#C44F5D', // error
    1400: '#9BB56E', // light primary
    1500: '#6B86C4', // light magnesium
    1800: '#B888C9', // light accent
    2000: '#E8C56A', // light secondary
};

/**
 * @param {number} base
 * @param {number | null} discounted
 */
function effectiveTotal(base, discounted) {
    if (discounted !== null && discounted < base) {
        return discounted;
    }

    return base;
}

/**
 * Brand checkbox from Storybook SquareCheckbox (`#556C37`).
 *
 * @param {{
 *   checked: boolean,
 *   onChange: () => void,
 *   children: import('react').ReactNode,
 * }} props
 */
function BrandCheckbox({ checked, onChange, children }) {
    return (
        <label className="group/item flex cursor-pointer items-center gap-2 font-body text-sm text-[#262A22]">
            <input
                type="checkbox"
                className="sr-only"
                checked={checked}
                onChange={onChange}
            />
            <SquareCheckbox checked={checked} presentational />
            {children}
        </label>
    );
}

/**
 * @param {number} base
 * @param {'amount' | 'percent'} type
 * @param {number} value
 */
function salePrice(base, type, value) {
    if (type === 'percent') {
        const percent = Math.min(100, Math.max(0, value));

        return Math.round(base * (1 - percent / 100));
    }

    return Math.max(0, base - Math.max(0, value));
}

/**
 * @param {{
 *   series: Array<{ tier: number, points: Array<{ days: number, total: number }> }>
 * }} props
 */
function PriceTrendChart({ series }) {
    const width = 720;
    const height = 240;
    const pad = { top: 16, right: 16, bottom: 32, left: 40 };
    const maxY = Math.max(
        1,
        ...series.flatMap((line) => line.points.map((point) => point.total)),
    );
    const innerW = width - pad.left - pad.right;
    const innerH = height - pad.top - pad.bottom;

    /**
     * @param {number} days
     */
    function xFor(days) {
        const index = DURATIONS.indexOf(days);

        return pad.left + (index / (DURATIONS.length - 1)) * innerW;
    }

    /**
     * @param {number} total
     */
    function yFor(total) {
        return pad.top + innerH - (total / maxY) * innerH;
    }

    return (
        <svg viewBox={`0 0 ${width} ${height}`} className="h-60 w-full" role="img" aria-label="Price trend comparison">
            {[0, 0.5, 1].map((step) => {
                const y = yFor(maxY * step);

                return (
                    <g key={step}>
                        <line x1={pad.left} x2={width - pad.right} y1={y} y2={y} stroke="#E5E7EB" />
                        <text x={4} y={y + 4} className="fill-[#888888] text-[10px]">
                            {Math.round(maxY * step)}
                        </text>
                    </g>
                );
            })}
            {DURATIONS.map((days) => (
                <text key={days} x={xFor(days)} y={height - 8} textAnchor="middle" className="fill-[#555555] text-[11px]">
                    {days}d
                </text>
            ))}
            {series.map((line) => {
                const color = TIER_COLORS[line.tier] ?? '#6E8C47';
                const d = line.points
                    .map((point, index) => `${index === 0 ? 'M' : 'L'} ${xFor(point.days)} ${yFor(point.total)}`)
                    .join(' ');

                return (
                    <g key={line.tier}>
                        <path d={d} fill="none" stroke={color} strokeWidth="2.5" />
                        {line.points.map((point) => (
                            <circle key={point.days} cx={xFor(point.days)} cy={yFor(point.total)} r="3.5" fill={color} />
                        ))}
                    </g>
                );
            })}
        </svg>
    );
}

/**
 * @param {{
 *   plans?: Array<{ key: string, name: string, calorieTiers: number[], minCalories: number }>,
 *   prices?: Array<{
 *     id: number,
 *     planKey: string,
 *     planName: string,
 *     calorieTier: number,
 *     daysCount: number,
 *     baseTotalPrice: number,
 *     discountedTotalPrice: number | null,
 *     dailyRate: number,
 *     isFeatured: boolean
 *   }>,
 *   currency?: string,
 *   csvExportUrl?: string,
 *   csvImportUrl?: string,
 * }} props
 */
export default function PartnerKitchenPricing({
    plans = [],
    prices = [],
    currency = 'BHD',
    csvExportUrl = '',
    csvImportUrl = '',
}) {
    const [drafts, setDrafts] = useState(() =>
        Object.fromEntries(
            prices.map((price) => [
                price.id,
                {
                    base: price.baseTotalPrice,
                    discounted: price.discountedTotalPrice,
                },
            ]),
        ),
    );
    const [planView, setPlanView] = useState('all');
    const [discountType, setDiscountType] = useState(/** @type {'amount' | 'percent'} */ ('percent'));
    const [discountValue, setDiscountValue] = useState('10');
    const [selectedPlans, setSelectedPlans] = useState(() => plans.map((plan) => plan.key));
    const [selectedTiers, setSelectedTiers] = useState(() =>
        [...new Set(plans.flatMap((plan) => plan.calorieTiers))].sort((a, b) => a - b),
    );
    const [selectedDays, setSelectedDays] = useState([...DURATIONS]);
    const [saving, setSaving] = useState(false);

    const viewOptions = useMemo(
        () => [
            { value: 'all', label: 'All Plans / Master Calorie Matrix' },
            ...plans.map((plan) => ({ value: plan.key, label: plan.name })),
        ],
        [plans],
    );

    const eligibleTiers = useMemo(() => {
        const keys = selectedPlans.length > 0 ? selectedPlans : plans.map((plan) => plan.key);
        const tiers = plans
            .filter((plan) => keys.includes(plan.key))
            .flatMap((plan) => plan.calorieTiers);

        return [...new Set(tiers)].sort((a, b) => a - b);
    }, [plans, selectedPlans]);

    const scopedPrices = useMemo(() => {
        if (planView === 'all') {
            return prices;
        }

        return prices.filter((price) => price.planKey === planView);
    }, [prices, planView]);

    const matrixTiers = useMemo(() => {
        return [...new Set(scopedPrices.map((price) => price.calorieTier))].sort((a, b) => a - b);
    }, [scopedPrices]);

    /**
     * @param {number} tier
     * @param {number} days
     */
    function cellPrices(tier, days) {
        return scopedPrices.filter((price) => price.calorieTier === tier && price.daysCount === days);
    }

    /**
     * @param {number} id
     */
    function draftFor(id) {
        return drafts[id] ?? { base: 0, discounted: null };
    }

    /**
     * @param {Array<{ id: number }>} matches
     */
    function cellDisplay(matches) {
        if (matches.length === 0) {
            return null;
        }

        const bases = matches.map((price) => Number(draftFor(price.id).base));
        const sales = matches.map((price) => {
            const draft = draftFor(price.id);

            return effectiveTotal(Number(draft.base), draft.discounted);
        });
        const basesMatch = bases.every((value) => value === bases[0]);
        const salesMatch = sales.every((value) => value === sales[0]);
        const discounted = matches.some((price) => {
            const draft = draftFor(price.id);

            return draft.discounted !== null && draft.discounted < Number(draft.base);
        });

        return {
            base: basesMatch ? bases[0] : null,
            sale: salesMatch ? sales[0] : null,
            discounted,
            days: matches[0].daysCount,
            ids: matches.map((price) => price.id),
        };
    }

    const chartSeries = useMemo(() => {
        return matrixTiers.map((tier) => ({
            tier,
            points: DURATIONS.map((days) => {
                const display = cellDisplay(cellPrices(tier, days));

                return {
                    days,
                    total: display?.sale ?? 0,
                };
            }),
        }));
    }, [matrixTiers, scopedPrices, drafts]);

    /**
     * @param {string} key
     */
    function togglePlan(key) {
        setSelectedPlans((current) =>
            current.includes(key) ? current.filter((item) => item !== key) : [...current, key],
        );
    }

    /**
     * @param {number} tier
     */
    function toggleTier(tier) {
        setSelectedTiers((current) =>
            current.includes(tier) ? current.filter((item) => item !== tier) : [...current, tier],
        );
    }

    /**
     * @param {number} days
     */
    function toggleDays(days) {
        setSelectedDays((current) =>
            current.includes(days) ? current.filter((item) => item !== days) : [...current, days],
        );
    }

    function campaignTargets() {
        return prices.filter((price) => {
            if (selectedPlans.length > 0 && !selectedPlans.includes(price.planKey)) {
                return false;
            }

            if (!selectedTiers.includes(price.calorieTier)) {
                return false;
            }

            return selectedDays.includes(price.daysCount);
        });
    }

    function applyCampaign() {
        const value = Number.parseInt(discountValue, 10) || 0;
        const targets = campaignTargets();
        const next = { ...drafts };

        targets.forEach((price) => {
            const base = Number(next[price.id]?.base ?? price.baseTotalPrice);
            const sale = salePrice(base, discountType, value);
            next[price.id] = {
                base,
                discounted: sale < base ? sale : null,
            };
        });

        setDrafts(next);

        router.post(
            '/admin/partner-kitchen-pricing/discounts/apply',
            {
                discount_type: discountType,
                discount_value: value,
                plan_keys: selectedPlans,
                calorie_tiers: selectedTiers,
                days_counts: selectedDays,
            },
            { preserveScroll: true, preserveState: true },
        );
    }

    function resetCampaign() {
        const targets = campaignTargets();
        const next = { ...drafts };

        targets.forEach((price) => {
            next[price.id] = {
                base: Number(next[price.id]?.base ?? price.baseTotalPrice),
                discounted: null,
            };
        });

        setDrafts(next);

        router.post(
            '/admin/partner-kitchen-pricing/discounts/reset',
            {
                apply_all: selectedPlans.length === plans.length
                    && selectedTiers.length === eligibleTiers.length
                    && selectedDays.length === DURATIONS.length,
                plan_keys: selectedPlans,
                calorie_tiers: selectedTiers,
                days_counts: selectedDays,
            },
            { preserveScroll: true, preserveState: true },
        );
    }

    function savePrices() {
        setSaving(true);
        router.put(
            '/admin/partner-kitchen-pricing',
            {
                prices: prices.map((price) => {
                    const draft = draftFor(price.id);

                    return {
                        id: price.id,
                        base_total_price: Number.parseInt(String(draft.base), 10) || 0,
                        discounted_total_price: draft.discounted,
                    };
                }),
            },
            {
                preserveScroll: true,
                onFinish: () => setSaving(false),
            },
        );
    }

    /**
     * @param {number[]} ids
     * @param {string} value
     */
    function setBases(ids, value) {
        const parsed = value === '' ? 0 : Number.parseInt(value, 10) || 0;

        setDrafts((current) => {
            const next = { ...current };

            ids.forEach((id) => {
                const previous = next[id] ?? { base: 0, discounted: null };
                next[id] = {
                    base: parsed,
                    discounted:
                        previous.discounted !== null && previous.discounted < parsed
                            ? previous.discounted
                            : null,
                };
            });

            return next;
        });
    }

    return (
        <div className="mx-auto w-full max-w-6xl space-y-8">
            <header>
                <h1 className="font-montserrat text-2xl font-bold tracking-tight text-[#262A22]">
                    Subscription pricing matrix
                </h1>
                <p className="mt-2 font-body text-sm text-[#555555]">
                    Integer {currency} package prices feed consultation and checkout. Longer plans should
                    show a lower daily rate inside the same calorie tier.
                </p>
            </header>

            <section className="rounded-[12px] border border-[#E5E7EB] bg-white p-5 shadow-sm">
                <h2 className="font-montserrat text-sm font-bold uppercase tracking-[0.14em] text-[#6E8C47]">
                    Discount campaign
                </h2>
                <div className="mt-4 grid gap-4 lg:grid-cols-[12rem_minmax(0,1fr)]">
                    <div className="grid gap-3">
                        <DropdownTextInput
                            label="Type"
                            value={discountType === 'percent' ? '% off' : `Flat ${currency}`}
                            options={['% off', `Flat ${currency}`]}
                            onChange={(label) => setDiscountType(label.startsWith('%') ? 'percent' : 'amount')}
                            listboxAriaLabel="Discount type"
                            className="!max-w-none"
                        />
                        <TextInput
                            label="Discount value"
                            value={discountValue}
                            onChange={(event) => setDiscountValue(event.target.value.replace(/[^\d]/g, ''))}
                            className="!max-w-none"
                        />
                    </div>
                    <div className="space-y-4">
                        <fieldset>
                            <legend className="font-montserrat text-[11px] font-semibold uppercase tracking-[0.14em] text-[#555555]">
                                Plans
                            </legend>
                            <div className="mt-2 flex flex-wrap gap-3">
                                <BrandCheckbox
                                    checked={selectedPlans.length === plans.length}
                                    onChange={() =>
                                        setSelectedPlans(
                                            selectedPlans.length === plans.length
                                                ? []
                                                : plans.map((plan) => plan.key),
                                        )
                                    }
                                >
                                    Select all
                                </BrandCheckbox>
                                {plans.map((plan) => (
                                    <BrandCheckbox
                                        key={plan.key}
                                        checked={selectedPlans.includes(plan.key)}
                                        onChange={() => togglePlan(plan.key)}
                                    >
                                        {plan.name}
                                    </BrandCheckbox>
                                ))}
                            </div>
                        </fieldset>
                        <fieldset>
                            <legend className="font-montserrat text-[11px] font-semibold uppercase tracking-[0.14em] text-[#555555]">
                                Calorie tiers
                            </legend>
                            <div className="mt-2 flex flex-wrap gap-3">
                                <BrandCheckbox
                                    checked={
                                        eligibleTiers.length > 0
                                        && selectedTiers.length === eligibleTiers.length
                                    }
                                    onChange={() =>
                                        setSelectedTiers(
                                            selectedTiers.length === eligibleTiers.length
                                                ? []
                                                : [...eligibleTiers],
                                        )
                                    }
                                >
                                    Select all
                                </BrandCheckbox>
                                {eligibleTiers.map((tier) => (
                                    <BrandCheckbox
                                        key={tier}
                                        checked={selectedTiers.includes(tier)}
                                        onChange={() => toggleTier(tier)}
                                    >
                                        <span className="inline-block size-2 rounded-full" style={{ backgroundColor: TIER_COLORS[tier] }} />
                                        {tier}
                                    </BrandCheckbox>
                                ))}
                            </div>
                        </fieldset>
                        <fieldset>
                            <legend className="font-montserrat text-[11px] font-semibold uppercase tracking-[0.14em] text-[#555555]">
                                Durations
                            </legend>
                            <div className="mt-2 flex flex-wrap items-center gap-3">
                                <BrandCheckbox
                                    checked={selectedDays.length === DURATIONS.length}
                                    onChange={() =>
                                        setSelectedDays(
                                            selectedDays.length === DURATIONS.length
                                                ? []
                                                : [...DURATIONS],
                                        )
                                    }
                                >
                                    Select all
                                </BrandCheckbox>
                                {DURATIONS.map((days) => (
                                    <BrandCheckbox
                                        key={days}
                                        checked={selectedDays.includes(days)}
                                        onChange={() => toggleDays(days)}
                                    >
                                        {days}d
                                    </BrandCheckbox>
                                ))}
                            </div>
                        </fieldset>
                    </div>
                </div>
                <div className="mt-4 flex flex-wrap gap-3">
                    <Button label="Apply to selected packages" size="sm" onClick={applyCampaign} />
                    <Button label="Reset discounts" size="sm" variant="outline" onClick={resetCampaign} />
                </div>
            </section>

            <section className="rounded-[12px] border border-[#E5E7EB] bg-white p-5 shadow-sm">
                <h2 className="font-montserrat text-sm font-bold uppercase tracking-[0.14em] text-[#555555]">
                    Price trend
                </h2>
                <div className="mt-3 flex flex-wrap gap-3">
                    {chartSeries.map((line) => (
                        <span key={line.tier} className="inline-flex items-center gap-2 font-body text-xs text-[#262A22]">
                            <span className="inline-block size-2.5 rounded-full" style={{ backgroundColor: TIER_COLORS[line.tier] }} />
                            {line.tier} kcal
                        </span>
                    ))}
                </div>
                <div className="mt-2">
                    <PriceTrendChart series={chartSeries} />
                </div>
            </section>

            <section className="rounded-[12px] border border-[#E5E7EB] bg-white p-5 shadow-sm">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <h2 className="font-montserrat text-sm font-bold uppercase tracking-[0.14em] text-[#555555]">
                        Pricing matrix
                    </h2>
                    <DropdownTextInput
                        label="View"
                        value={viewOptions.find((option) => option.value === planView)?.label ?? viewOptions[0].label}
                        options={viewOptions.map((option) => option.label)}
                        onChange={(label) => {
                            const next = viewOptions.find((option) => option.label === label);
                            setPlanView(next?.value ?? 'all');
                        }}
                        listboxAriaLabel="Pricing matrix view"
                        className="!max-w-none sm:w-80"
                    />
                </div>
                {csvExportUrl !== '' || csvImportUrl !== '' ? (
                    <div className="mt-4">
                        <CSVUploader
                            exportUrl={csvExportUrl !== '' ? csvExportUrl : undefined}
                            onUpload={(file) => {
                                if (csvImportUrl === '') {
                                    return;
                                }

                                router.post(csvImportUrl, { file }, { forceFormData: true, preserveScroll: true });
                            }}
                        />
                    </div>
                ) : null}
                <div className="mt-4 overflow-x-auto">
                    <table className="min-w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-[#E5E7EB] bg-[#F6F6F4] text-[#555555]">
                                <th className="px-3 py-3 font-montserrat text-[11px] font-semibold uppercase tracking-[0.14em]">
                                    Calorie tier
                                </th>
                                {DURATIONS.map((days) => (
                                    <th key={days} className="px-3 py-3 font-montserrat text-[11px] font-semibold uppercase tracking-[0.14em]">
                                        {days}d
                                        {days === 24 ? (
                                            <span className="ml-1 text-[#D8A933]" title="Best value">
                                                ★
                                            </span>
                                        ) : null}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {matrixTiers.map((tier) => (
                                <tr key={tier} className="border-b border-[#F2F2F2] align-top">
                                    <td className="px-3 py-3 font-montserrat font-semibold text-[#262A22]">
                                        <span className="mr-2 inline-block size-2 rounded-full" style={{ backgroundColor: TIER_COLORS[tier] }} />
                                        {tier}
                                    </td>
                                    {DURATIONS.map((days) => {
                                        const display = cellDisplay(cellPrices(tier, days));

                                        if (display === null) {
                                            return (
                                                <td key={days} className="px-3 py-3 text-[#888888]">
                                                    —
                                                </td>
                                            );
                                        }

                                        const sale = display.sale;
                                        const showStrike = display.discounted && display.base !== null && sale !== null && sale < display.base;

                                        return (
                                            <td key={days} className="relative px-3 py-3">
                                                {display.base === null ? (
                                                    <p className="font-body text-xs text-[#D8A933]">Varies by plan</p>
                                                ) : (
                                                    <TextInput
                                                        label={`${tier} kcal ${days} days`}
                                                        value={String(display.base)}
                                                        onChange={(event) =>
                                                            setBases(
                                                                display.ids,
                                                                event.target.value.replace(/[^\d]/g, ''),
                                                            )
                                                        }
                                                        className={[
                                                            '!max-w-none [&_label]:sr-only',
                                                            showStrike ? '[&_input]:text-[#888888] [&_input]:line-through' : '',
                                                        ].filter(Boolean).join(' ')}
                                                    />
                                                )}
                                                {showStrike && sale !== null ? (
                                                    <span className="mt-1 inline-flex rounded-full bg-[#C44F5D] px-2 py-0.5 font-montserrat text-[11px] font-bold text-white">
                                                        {sale}
                                                    </span>
                                                ) : null}
                                            </td>
                                        );
                                    })}
                                </tr>
                            ))}
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
