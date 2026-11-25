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
                <h1 className="mt-3 font-montserrat text-2xl font-semibold text-heading">
                    {customerName || 'Customer profile'}
                </h1>
                <p className="mt-2 text-sm text-body">
                    {canEdit
                        ? 'View and edit intake details collected at onboarding and checkout.'
                        : 'View intake details. Only the Craft Master General can edit customer profiles.'}
                </p>
                {flashSuccess ? (
                    <p className="mt-3 font-body text-sm font-medium text-[#5A6B44]">{flashSuccess}</p>
                ) : null}
            </div>

            <nav aria-label="Customer profile sections" className="flex flex-wrap gap-2" role="tablist">
                {TABS.map((tab) => {
                    const isActive = tab.id === activeTab;
                    return (
                        <Button
                            key={tab.id}
                            type="button"
                            role="tab"
                            aria-selected={isActive}
                            aria-current={isActive ? 'page' : undefined}
                            label={tab.label}
                            variant={isActive ? 'primary' : 'tab'}
                            size="sm"
                            className="shrink-0"
                            onClick={() => setActiveTab(tab.id)}
                        />
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
                    <h2 className="m-0 font-montserrat text-base font-bold tracking-tight text-heading">
                        Subscription
                    </h2>
                    <p className="mt-1 font-montserrat text-sm text-body">
                        Paid Partner Kitchen lifecycle for this customer.
                    </p>

                    {!subscription ? (
                        <p className="mt-5 font-montserrat text-sm text-body">
                            No paid subscription recorded yet. Completing checkout Make payment creates one.
                        </p>
                    ) : (
                        <dl className="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <dt className="font-montserrat text-[11px] font-bold uppercase tracking-[0.14em] text-body">
                                    Status
                                </dt>
                                <dd className="mt-1 font-montserrat text-sm font-semibold text-heading">
                                    {subscription.statusLabel ?? subscription.status ?? '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="font-montserrat text-[11px] font-bold uppercase tracking-[0.14em] text-body">
                                    Payment date
                                </dt>
                                <dd className="mt-1 font-montserrat text-sm font-semibold text-heading">
                                    {formatDate(subscription.paidAt)}
                                </dd>
                            </div>
                            <div>
                                <dt className="font-montserrat text-[11px] font-bold uppercase tracking-[0.14em] text-body">
                                    Meal plan
                                </dt>
                                <dd className="mt-1 font-montserrat text-sm font-semibold text-heading">
                                    {subscription.mealPlanName ?? '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="font-montserrat text-[11px] font-bold uppercase tracking-[0.14em] text-body">
                                    Package days
                                </dt>
                                <dd className="mt-1 font-montserrat text-sm font-semibold text-heading">
                                    {subscription.days ?? '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="font-montserrat text-[11px] font-bold uppercase tracking-[0.14em] text-body">
                                    Start
                                </dt>
                                <dd className="mt-1 font-montserrat text-sm font-semibold text-heading">
                                    {formatDate(subscription.startDate)}
                                </dd>
                            </div>
                            <div>
                                <dt className="font-montserrat text-[11px] font-bold uppercase tracking-[0.14em] text-body">
                                    Expiry
                                </dt>
                                <dd className="mt-1 font-montserrat text-sm font-semibold text-heading">
                                    {formatDate(subscription.expiryDate)}
                                </dd>
                            </div>
                            <div>
                                <dt className="font-montserrat text-[11px] font-bold uppercase tracking-[0.14em] text-body">
                                    Deliveries
                                </dt>
                                <dd className="mt-1 font-montserrat text-sm font-semibold text-heading">
                                    {subscription.deliveries ?? 0}
                                </dd>
                            </div>
                            <div>
                                <dt className="font-montserrat text-[11px] font-bold uppercase tracking-[0.14em] text-body">
                                    Fulfillment
                                </dt>
                                <dd className="mt-1 font-montserrat text-sm font-semibold text-heading">
                                    {subscription.fulfillmentChoice === 'partner_kitchen'
                                        ? 'Partner Kitchen'
                                        : subscription.fulfillmentChoice === 'diy'
                                          ? 'DIY'
                                          : (subscription.fulfillmentChoice ?? '—')}
                                </dd>
                            </div>
                            <div className="sm:col-span-2">
                                <dt className="font-montserrat text-[11px] font-bold uppercase tracking-[0.14em] text-body">
                                    Frozen days
                                </dt>
                                <dd className="mt-1 font-montserrat text-sm text-heading">
                                    {Array.isArray(subscription.frozenDays) && subscription.frozenDays.length > 0 ? (
                                        <ul className="m-0 list-disc space-y-1 pl-5">
                                            {subscription.frozenDays.map((day) => (
                                                <li key={day}>{formatDate(day)}</li>
                                            ))}
                                        </ul>
                                    ) : (
                                        <span className="text-body">No frozen days yet</span>
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
