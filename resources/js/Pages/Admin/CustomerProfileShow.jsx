import { Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Button from '../../Components/Atoms/Button/Button.jsx';
import CustomerIntakeForm from '../../Components/CustomerIntake/CustomerIntakeForm.jsx';
import adminInertiaLayout from '../../lib/adminInertiaLayout.jsx';

const TABS = [
    { id: 'contact', label: 'Contact' },
    { id: 'body', label: 'Body & plan' },
    { id: 'subscription', label: 'Subscription' },
];

/**
 * @param {string|null|undefined} value
 */
function formatDate(value) {
    if (!value) {
        return '—';
    }

    try {
        return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value));
    } catch {
        return String(value);
    }
}

/**
 * @param {object} props
 * @param {Record<string, mixed>} props.form
 * @param {Record<string, Array<{value: string, label: string}>>} props.options
 * @param {string} [props.uniqueCode]
 * @param {string} [props.intakeSubmissionId]
 * @param {string} [props.customerName]
 * @param {string|null} [props.submitUrl]
 * @param {string} [props.listUrl]
 * @param {boolean} [props.canEdit]
 * @param {object|null} [props.subscription]
 */
export default function CustomerProfileShow({
    form,
    options,
    uniqueCode = '',
    intakeSubmissionId = '',
    customerName = '',
    submitUrl = null,
    listUrl = '/admin/customers',
    canEdit = false,
    subscription = null,
}) {
    const page = usePage();
    const flashSuccess = typeof page.props?.flash?.success === 'string' ? page.props.flash.success : null;
    const { data, setData, put, processing, errors } = useForm({ ...form });
    const [activeTab, setActiveTab] = useState('contact');

    const save = () => {
        if (!canEdit || !submitUrl) {
            return;
        }
        put(submitUrl);
    };

    return (
        <div className="space-y-6">
            <div>
                <Link
                    href={listUrl}
                    className="inline-flex items-center gap-1 font-montserrat text-sm font-semibold text-[#5A6B44] hover:underline"
                >
                    ← Customer Profiles
                </Link>
                <h1 className="mt-3 font-montserrat text-2xl font-semibold text-[#262A22]">
                    {customerName || 'Customer profile'}
                </h1>
                <p className="mt-2 text-sm text-[#555555]">
                    {canEdit
                        ? 'View and edit intake details collected at onboarding and checkout.'
                        : 'View intake details. Only the Craft Master General can edit customer profiles.'}
                </p>
                {flashSuccess ? (
                    <p className="mt-3 font-body text-sm font-medium text-[#5A6B44]">{flashSuccess}</p>
                ) : null}
            </div>

            <nav aria-label="Customer profile sections" className="flex flex-wrap gap-2">
                {TABS.map((tab) => {
                    const isActive = tab.id === activeTab;
                    return (
                        <button
                            key={tab.id}
                            type="button"
                            onClick={() => setActiveTab(tab.id)}
                            aria-current={isActive ? 'page' : undefined}
                            className={[
                                'rounded-[10px] px-3 py-2 font-montserrat text-sm font-semibold transition-colors',
                                isActive
                                    ? 'bg-[#6E8C47]/15 text-[#3F4F2A]'
                                    : 'text-[#555555] hover:bg-gray-100 hover:text-[#364153]',
                            ].join(' ')}
                        >
                            {tab.label}
                        </button>
                    );
                })}
            </nav>

            {activeTab === 'contact' ? (
                <div className="space-y-4">
                    <CustomerIntakeForm
                        data={data}
                        setData={setData}
                        errors={errors}
                        processing={processing}
                        options={options}
                        uniqueCode={uniqueCode}
                        intakeSubmissionId={intakeSubmissionId}
                        sections={['contact', 'delivery']}
                        showSubmit={false}
                        readOnly={!canEdit}
                        onSubmit={save}
                    />
                    {canEdit ? (
                        <div className="flex justify-center">
                            <Button
                                type="button"
                                label={processing ? 'Saving…' : 'Save profile'}
                                disabled={processing}
                                onClick={save}
                                className="w-full min-w-[200px] max-w-sm uppercase tracking-[0.08em]"
                            />
                        </div>
                    ) : null}
                </div>
            ) : null}

            {activeTab === 'body' ? (
                <div className="space-y-4">
                    <CustomerIntakeForm
                        data={data}
                        setData={setData}
                        errors={errors}
                        processing={processing}
                        options={options}
                        uniqueCode={uniqueCode}
                        intakeSubmissionId={intakeSubmissionId}
                        sections={['body', 'other']}
                        showSubmit={false}
                        readOnly={!canEdit}
                        onSubmit={save}
                    />
                    {canEdit ? (
                        <div className="flex justify-center">
                            <Button
                                type="button"
                                label={processing ? 'Saving…' : 'Save profile'}
                                disabled={processing}
                                onClick={save}
                                className="w-full min-w-[200px] max-w-sm uppercase tracking-[0.08em]"
                            />
                        </div>
                    ) : null}
                </div>
            ) : null}

            {activeTab === 'subscription' ? (
                <section className="rounded-[12px] border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <h2 className="m-0 font-montserrat text-base font-bold tracking-tight text-[#262A22]">
                        Subscription
                    </h2>
                    <p className="mt-1 font-montserrat text-sm text-[#555555]">
                        Paid Partner Kitchen lifecycle for this customer.
                    </p>

                    {!subscription ? (
                        <p className="mt-5 font-montserrat text-sm text-[#555555]">
                            No paid subscription recorded yet. Completing checkout Make payment creates one.
                        </p>
                    ) : (
                        <dl className="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <dt className="font-montserrat text-[11px] font-bold uppercase tracking-[0.14em] text-[#555555]">
                                    Status
                                </dt>
                                <dd className="mt-1 font-montserrat text-sm font-semibold text-[#262A22]">
                                    {subscription.statusLabel ?? subscription.status ?? '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="font-montserrat text-[11px] font-bold uppercase tracking-[0.14em] text-[#555555]">
                                    Payment date
                                </dt>
                                <dd className="mt-1 font-montserrat text-sm font-semibold text-[#262A22]">
                                    {formatDate(subscription.paidAt)}
                                </dd>
                            </div>
                            <div>
                                <dt className="font-montserrat text-[11px] font-bold uppercase tracking-[0.14em] text-[#555555]">
                                    Meal plan
                                </dt>
                                <dd className="mt-1 font-montserrat text-sm font-semibold text-[#262A22]">
                                    {subscription.mealPlanName ?? '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="font-montserrat text-[11px] font-bold uppercase tracking-[0.14em] text-[#555555]">
                                    Package days
                                </dt>
                                <dd className="mt-1 font-montserrat text-sm font-semibold text-[#262A22]">
                                    {subscription.days ?? '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="font-montserrat text-[11px] font-bold uppercase tracking-[0.14em] text-[#555555]">
                                    Start
                                </dt>
                                <dd className="mt-1 font-montserrat text-sm font-semibold text-[#262A22]">
                                    {formatDate(subscription.startDate)}
                                </dd>
                            </div>
                            <div>
                                <dt className="font-montserrat text-[11px] font-bold uppercase tracking-[0.14em] text-[#555555]">
                                    Expiry
                                </dt>
                                <dd className="mt-1 font-montserrat text-sm font-semibold text-[#262A22]">
                                    {formatDate(subscription.expiryDate)}
                                </dd>
                            </div>
                            <div>
                                <dt className="font-montserrat text-[11px] font-bold uppercase tracking-[0.14em] text-[#555555]">
                                    Deliveries
                                </dt>
                                <dd className="mt-1 font-montserrat text-sm font-semibold text-[#262A22]">
                                    {subscription.deliveries ?? 0}
                                </dd>
                            </div>
                            <div>
                                <dt className="font-montserrat text-[11px] font-bold uppercase tracking-[0.14em] text-[#555555]">
                                    Fulfillment
                                </dt>
                                <dd className="mt-1 font-montserrat text-sm font-semibold text-[#262A22]">
                                    {subscription.fulfillmentChoice === 'partner_kitchen'
                                        ? 'Partner Kitchen'
                                        : subscription.fulfillmentChoice === 'diy'
                                          ? 'DIY'
                                          : (subscription.fulfillmentChoice ?? '—')}
                                </dd>
                            </div>
                            <div className="sm:col-span-2">
                                <dt className="font-montserrat text-[11px] font-bold uppercase tracking-[0.14em] text-[#555555]">
                                    Frozen days
                                </dt>
                                <dd className="mt-1 font-montserrat text-sm text-[#262A22]">
                                    {Array.isArray(subscription.frozenDays) && subscription.frozenDays.length > 0 ? (
                                        <ul className="m-0 list-disc space-y-1 pl-5">
                                            {subscription.frozenDays.map((day) => (
                                                <li key={day}>{formatDate(day)}</li>
                                            ))}
                                        </ul>
                                    ) : (
                                        <span className="text-[#555555]">No frozen days yet</span>
                                    )}
                                </dd>
                            </div>
                        </dl>
                    )}
                </section>
            ) : null}
        </div>
    );
}

CustomerProfileShow.layout = adminInertiaLayout;
