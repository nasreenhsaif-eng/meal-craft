import { router, usePage } from '@inertiajs/react';
import { useEffect, useId, useRef, useState } from 'react';
import Button from '../../Components/Atoms/Button/Button.jsx';
import adminInertiaLayout from '../../lib/adminInertiaLayout.jsx';

const TABS = [
    { id: 'contact', label: 'Contact' },
    { id: 'body', label: 'Body & plan' },
    { id: 'subscription', label: 'Subscription' },
];

/** @typedef {'text' | 'number' | 'date' | 'select' | 'boolean' | 'readonly'} CellType */

/**
 * @typedef {{
 *   key: string,
 *   label: string,
 *   source: 'fields' | 'subscription' | 'meta',
 *   type: CellType,
 *   editable?: boolean,
 *   optionsKey?: string,
 * }} ColumnDef
 */

/** @type {ColumnDef[]} */
const CONTACT_COLUMNS = [
    { key: 'first_name', label: 'First name', source: 'fields', type: 'text', editable: true },
    { key: 'last_name', label: 'Last name', source: 'fields', type: 'text', editable: true },
    { key: 'email', label: 'Email', source: 'fields', type: 'text', editable: true },
    { key: 'phone', label: 'Phone', source: 'fields', type: 'text', editable: true },
    {
        key: 'contact_preference',
        label: 'Contact preference',
        source: 'fields',
        type: 'select',
        editable: true,
        optionsKey: 'contactPreferences',
    },
    {
        key: 'delivery_time',
        label: 'Delivery time',
        source: 'fields',
        type: 'select',
        editable: true,
        optionsKey: 'deliveryTimes',
    },
    { key: 'planned_start_date', label: 'Planned start', source: 'fields', type: 'date', editable: true },
    { key: 'area', label: 'Area', source: 'fields', type: 'text', editable: true },
    { key: 'block', label: 'Block', source: 'fields', type: 'text', editable: true },
    { key: 'road', label: 'Road', source: 'fields', type: 'text', editable: true },
    { key: 'house_number', label: 'House number', source: 'fields', type: 'text', editable: true },
    { key: 'gate_flat_number', label: 'Gate / flat', source: 'fields', type: 'text', editable: true },
    { key: 'country', label: 'Country', source: 'fields', type: 'text', editable: true },
];

/** @type {ColumnDef[]} */
const BODY_COLUMNS = [
    { key: 'date_of_birth', label: 'Date of birth', source: 'fields', type: 'date', editable: true },
    { key: 'gender', label: 'Gender', source: 'fields', type: 'select', editable: true, optionsKey: 'genders' },
    { key: 'height_cm', label: 'Height (cm)', source: 'fields', type: 'number', editable: true },
    { key: 'weight_kg', label: 'Weight (kg)', source: 'fields', type: 'number', editable: true },
    { key: 'target_weight_kg', label: 'Target weight (kg)', source: 'fields', type: 'number', editable: true },
    {
        key: 'activity_level',
        label: 'Activity',
        source: 'fields',
        type: 'select',
        editable: true,
        optionsKey: 'activityLevels',
    },
    { key: 'plan_type', label: 'Plan type', source: 'fields', type: 'select', editable: true, optionsKey: 'planTypes' },
    { key: 'plan_days', label: 'Plan days', source: 'fields', type: 'select', editable: true, optionsKey: 'planDays' },
    {
        key: 'diet_protocol',
        label: 'Diet protocol',
        source: 'fields',
        type: 'select',
        editable: true,
        optionsKey: 'dietProtocols',
    },
    { key: 'dislikes_and_allergies', label: 'Dislikes / allergies', source: 'fields', type: 'text', editable: true },
    { key: 'daily_calorie_target', label: 'Calories', source: 'fields', type: 'number', editable: true },
    { key: 'follow_instagram', label: 'Follow Instagram', source: 'fields', type: 'boolean', editable: true },
    { key: 'customer_question', label: 'Customer question', source: 'fields', type: 'text', editable: true },
    { key: 'onboarding', label: 'Onboarding', source: 'meta', type: 'readonly', editable: false },
];

/** @type {ColumnDef[]} */
const SUBSCRIPTION_COLUMNS = [
    { key: 'statusLabel', label: 'Sub status', source: 'subscription', type: 'readonly' },
    { key: 'mealPlanName', label: 'Meal plan', source: 'subscription', type: 'readonly' },
    { key: 'days', label: 'Days', source: 'subscription', type: 'readonly' },
    { key: 'startDate', label: 'Start', source: 'subscription', type: 'readonly' },
    { key: 'expiryDate', label: 'Expiry', source: 'subscription', type: 'readonly' },
    { key: 'deliveries', label: 'Deliveries', source: 'subscription', type: 'readonly' },
    { key: 'fulfillmentChoice', label: 'Fulfillment', source: 'subscription', type: 'readonly' },
    { key: 'paidAt', label: 'Paid at', source: 'subscription', type: 'readonly' },
    { key: 'frozenDays', label: 'Frozen days', source: 'subscription', type: 'readonly' },
];

/**
 * @param {string} tabId
 * @returns {ColumnDef[]}
 */
function columnsForTab(tabId) {
    if (tabId === 'body') {
        return BODY_COLUMNS;
    }
    if (tabId === 'subscription') {
        return SUBSCRIPTION_COLUMNS;
    }

    return CONTACT_COLUMNS;
}

/**
 * @param {unknown} value
 */
function emptyDisplay(value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return null;
}

/**
 * @param {ColumnDef} column
 * @param {object} customer
 * @param {Record<string, Array<{value: string, label: string}>>} options
 */
function rawCellValue(column, customer, options) {
    if (column.source === 'meta' && column.key === 'onboarding') {
        if (customer.onboardingCompletedAt) {
            return 'Complete';
        }

        return typeof customer.onboardingStep === 'string'
            ? customer.onboardingStep.replaceAll('_', ' ')
            : '';
    }

    if (column.source === 'subscription') {
        const subscription = customer.subscription;
        if (!subscription) {
            return null;
        }

        if (column.key === 'frozenDays') {
            const days = Array.isArray(subscription.frozenDays) ? subscription.frozenDays : [];
            return days.length === 0 ? '' : days.join(', ');
        }

        if (column.key === 'paidAt' && subscription.paidAt) {
            try {
                return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
                    new Date(subscription.paidAt),
                );
            } catch {
                return String(subscription.paidAt);
            }
        }

        return subscription[column.key] ?? null;
    }

    const fields = customer.fields ?? {};
    return fields[column.key] ?? null;
}

/**
 * @param {ColumnDef} column
 * @param {object} customer
 * @param {Record<string, Array<{value: string, label: string}>>} options
 */
function displayCellValue(column, customer, options) {
    const raw = rawCellValue(column, customer, options);
    const blank = emptyDisplay(raw);
    if (blank !== null) {
        return blank;
    }

    if (column.type === 'boolean') {
        return raw ? 'Yes' : 'No';
    }

    if (column.type === 'select' && column.optionsKey) {
        const list = options[column.optionsKey] ?? [];
        const match = list.find((item) => String(item.value) === String(raw));
        return match?.label ?? String(raw);
    }

    return String(raw);
}

/**
 * @param {ColumnDef} column
 * @param {object} customer
 */
function editableRawValue(column, customer) {
    if (column.source !== 'fields') {
        return '';
    }

    const value = customer.fields?.[column.key];

    if (column.type === 'boolean') {
        return Boolean(value);
    }

    return value === null || value === undefined ? '' : value;
}

/**
 * @param {{
 *   open: boolean,
 *   title: string,
 *   description: string,
 *   processing: boolean,
 *   onCancel: () => void,
 *   onConfirm: () => void,
 * }} props
 */
function ConfirmEditModal({ open, title, description, processing, onCancel, onConfirm }) {
    const titleId = useId();

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        const onKeyDown = (event) => {
            if (event.key === 'Escape' && !processing) {
                onCancel();
            }
        };

        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
    }, [open, processing, onCancel]);

    if (!open) {
        return null;
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4" role="presentation">
            <button
                type="button"
                className="absolute inset-0 bg-black/40"
                aria-label="Dismiss"
                disabled={processing}
                onClick={() => {
                    if (!processing) {
                        onCancel();
                    }
                }}
            />
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby={titleId}
                className="relative z-10 w-full max-w-md rounded-[12px] border border-gray-200 bg-white p-6 shadow-lg"
            >
                <h2 id={titleId} className="font-montserrat text-lg font-semibold text-[#262A22]">
                    {title}
                </h2>
                <p className="mt-3 text-sm text-[#555555] whitespace-pre-wrap">{description}</p>
                <div className="mt-6 flex flex-wrap justify-end gap-3">
                    <Button label="Cancel" variant="ghost" size="sm" disabled={processing} onClick={onCancel} />
                    <Button
                        label={processing ? 'Saving…' : 'Confirm'}
                        variant="primary"
                        size="sm"
                        disabled={processing}
                        onClick={onConfirm}
                    />
                </div>
            </div>
        </div>
    );
}

/**
 * @param {object} props
 * @param {Array<object>} props.customers
 * @param {boolean} [props.canEdit]
 * @param {Record<string, Array<{value: string, label: string}>>} [props.options]
 */
function CustomerProfilesView({ customers = [], canEdit = false, options = {} }) {
    const page = usePage();
    const flashSuccess = typeof page.props?.flash?.success === 'string' ? page.props.flash.success : null;
    const [activeTab, setActiveTab] = useState('contact');
    const [editing, setEditing] = useState(null);
    const [pendingConfirm, setPendingConfirm] = useState(null);
    const [processing, setProcessing] = useState(false);
    const inputRef = useRef(null);

    const groupColumns = columnsForTab(activeTab);
    const pinnedColCount = 3;
    const totalColSpan = pinnedColCount + groupColumns.length;

    useEffect(() => {
        if (editing && inputRef.current) {
            inputRef.current.focus();
            if (typeof inputRef.current.select === 'function' && editing.column.type !== 'boolean') {
                inputRef.current.select();
            }
        }
    }, [editing]);

    const cancelEditing = () => setEditing(null);

    const openConfirm = (customer, column, nextValue) => {
        const previous = editableRawValue(column, customer);
        const normalizedNext =
            column.type === 'boolean' ? Boolean(nextValue) : nextValue === null || nextValue === undefined ? '' : nextValue;

        if (String(previous) === String(normalizedNext) && column.type !== 'boolean') {
            setEditing(null);
            return;
        }

        if (column.type === 'boolean' && Boolean(previous) === Boolean(normalizedNext)) {
            setEditing(null);
            return;
        }

        setPendingConfirm({
            customer,
            column,
            previous,
            nextValue: normalizedNext,
        });
        setEditing(null);
    };

    const confirmSave = () => {
        if (!pendingConfirm?.customer?.updateUrl) {
            setPendingConfirm(null);
            return;
        }

        const { customer, column, nextValue } = pendingConfirm;
        const payload = {
            ...(customer.fields ?? {}),
            [column.key]: nextValue,
        };

        setProcessing(true);
        router.put(customer.updateUrl, payload, {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
            },
            onSuccess: () => {
                setPendingConfirm(null);
            },
        });
    };

    const formatConfirmValue = (column, value) => {
        if (column.type === 'boolean') {
            return value ? 'Yes' : 'No';
        }

        if (value === null || value === undefined || value === '') {
            return '—';
        }

        if (column.type === 'select' && column.optionsKey) {
            const list = options[column.optionsKey] ?? [];
            const match = list.find((item) => String(item.value) === String(value));
            return match?.label ?? String(value);
        }

        return String(value);
    };

    return (
        <div className="space-y-6">
            <div>
                <h1 className="font-montserrat text-2xl font-semibold text-[#262A22]">Customer Profiles</h1>
                <p className="mt-2 text-sm text-[#555555]">
                    {canEdit
                        ? 'Double-click a cell to edit onboarding details, then confirm the change.'
                        : 'Onboarding details by column group. You can view this sheet with Customer Profiles access.'}
                </p>
                {flashSuccess ? (
                    <p className="mt-3 font-body text-sm font-medium text-[#5A6B44]">{flashSuccess}</p>
                ) : null}
            </div>

            <nav aria-label="Customer profile column groups" className="flex flex-wrap gap-2">
                {TABS.map((tab) => {
                    const isActive = tab.id === activeTab;
                    return (
                        <button
                            key={tab.id}
                            type="button"
                            onClick={() => {
                                setActiveTab(tab.id);
                                setEditing(null);
                            }}
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

            <div className="overflow-auto rounded-[12px] border border-gray-200 bg-white shadow-sm">
                <table className="min-w-full border-separate border-spacing-0 text-sm">
                    <thead>
                        <tr className="bg-[#F8F9F6]">
                            <th className="sticky left-0 z-20 min-w-[160px] border-b border-gray-200 bg-[#F8F9F6] px-4 py-3 text-left font-semibold text-[#555555]">
                                Name
                            </th>
                            <th className="sticky left-[160px] z-20 min-w-[120px] border-b border-gray-200 bg-[#F8F9F6] px-4 py-3 text-left font-semibold text-[#555555]">
                                Unique ID
                            </th>
                            <th className="sticky left-[280px] z-20 min-w-[100px] border-b border-r border-gray-200 bg-[#F8F9F6] px-4 py-3 text-left font-semibold text-[#555555] shadow-[2px_0_4px_-2px_rgba(0,0,0,0.08)]">
                                Status
                            </th>
                            {groupColumns.map((column) => (
                                <th
                                    key={column.key}
                                    className="whitespace-nowrap border-b border-gray-200 px-4 py-3 text-left font-semibold text-[#555555]"
                                >
                                    {column.label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {customers.length === 0 ? (
                            <tr>
                                <td colSpan={totalColSpan} className="px-4 py-8 text-center text-[#6B7280]">
                                    No customer profiles yet.
                                </td>
                            </tr>
                        ) : (
                            customers.map((customer) => {
                                const isEditingRow = editing?.customerId === customer.id;

                                return (
                                    <tr key={customer.id} className="hover:bg-[#F8F9F6]/80">
                                        <td className="sticky left-0 z-10 min-w-[160px] border-b border-gray-100 bg-white px-4 py-3 font-medium text-[#262A22]">
                                            {customer.name || '—'}
                                        </td>
                                        <td className="sticky left-[160px] z-10 min-w-[120px] border-b border-gray-100 bg-white px-4 py-3 font-mono text-[#555555]">
                                            {customer.uniqueCode || '—'}
                                        </td>
                                        <td className="sticky left-[280px] z-10 min-w-[100px] border-b border-r border-gray-100 bg-white px-4 py-3 shadow-[2px_0_4px_-2px_rgba(0,0,0,0.08)]">
                                            <span
                                                className={`rounded-full px-2 py-1 text-xs font-semibold ${
                                                    customer.isActive
                                                        ? 'bg-[#E8EFE0] text-[#556C37]'
                                                        : 'bg-red-50 text-red-700'
                                                }`}
                                            >
                                                {customer.isActive ? 'Active' : 'Inactive'}
                                            </span>
                                        </td>
                                        {groupColumns.map((column) => {
                                            const isCellEditing =
                                                isEditingRow && editing?.column.key === column.key;
                                            const canEditCell =
                                                canEdit &&
                                                column.editable &&
                                                column.source === 'fields' &&
                                                Boolean(customer.updateUrl);

                                            return (
                                                <td
                                                    key={column.key}
                                                    className={[
                                                        'max-w-[240px] border-b border-gray-100 px-4 py-3 text-[#555555]',
                                                        canEditCell ? 'cursor-pointer' : '',
                                                    ].join(' ')}
                                                    title={
                                                        canEditCell ? 'Double-click to edit' : undefined
                                                    }
                                                    onDoubleClick={() => {
                                                        if (!canEditCell) {
                                                            return;
                                                        }

                                                        if (column.type === 'boolean') {
                                                            openConfirm(
                                                                customer,
                                                                column,
                                                                !editableRawValue(column, customer),
                                                            );
                                                            return;
                                                        }

                                                        setEditing({
                                                            customerId: customer.id,
                                                            column,
                                                            draft: editableRawValue(column, customer),
                                                            customer,
                                                        });
                                                    }}
                                                >
                                                    {isCellEditing ? (
                                                        column.type === 'select' ? (
                                                            <select
                                                                ref={inputRef}
                                                                className="w-full min-w-[140px] rounded-md border border-gray-300 px-2 py-1 text-sm"
                                                                value={String(editing.draft ?? '')}
                                                                onChange={(event) => {
                                                                    openConfirm(
                                                                        customer,
                                                                        column,
                                                                        event.target.value,
                                                                    );
                                                                }}
                                                                onKeyDown={(event) => {
                                                                    if (event.key === 'Escape') {
                                                                        cancelEditing();
                                                                    }
                                                                }}
                                                            >
                                                                <option value="">—</option>
                                                                {(options[column.optionsKey] ?? []).map((item) => (
                                                                    <option key={item.value} value={item.value}>
                                                                        {item.label}
                                                                    </option>
                                                                ))}
                                                            </select>
                                                        ) : (
                                                            <input
                                                                ref={inputRef}
                                                                type={
                                                                    column.type === 'number'
                                                                        ? 'number'
                                                                        : column.type === 'date'
                                                                          ? 'date'
                                                                          : 'text'
                                                                }
                                                                className="w-full min-w-[140px] rounded-md border border-gray-300 px-2 py-1 text-sm"
                                                                value={String(editing.draft ?? '')}
                                                                onChange={(event) =>
                                                                    setEditing((current) =>
                                                                        current
                                                                            ? {
                                                                                  ...current,
                                                                                  draft: event.target.value,
                                                                              }
                                                                            : current,
                                                                    )
                                                                }
                                                                onBlur={() => {
                                                                    if (!editing) {
                                                                        return;
                                                                    }
                                                                    openConfirm(
                                                                        editing.customer,
                                                                        editing.column,
                                                                        editing.draft,
                                                                    );
                                                                }}
                                                                onKeyDown={(event) => {
                                                                    if (event.key === 'Escape') {
                                                                        event.preventDefault();
                                                                        cancelEditing();
                                                                    }
                                                                    if (event.key === 'Enter') {
                                                                        event.preventDefault();
                                                                        openConfirm(
                                                                            customer,
                                                                            column,
                                                                            editing.draft,
                                                                        );
                                                                    }
                                                                }}
                                                            />
                                                        )
                                                    ) : (
                                                        <span className="block truncate">
                                                            {displayCellValue(column, customer, options)}
                                                        </span>
                                                    )}
                                                </td>
                                            );
                                        })}
                                    </tr>
                                );
                            })
                        )}
                    </tbody>
                </table>
            </div>

            <ConfirmEditModal
                open={Boolean(pendingConfirm)}
                processing={processing}
                title="Confirm edit"
                description={
                    pendingConfirm
                        ? `${pendingConfirm.column.label}\n${formatConfirmValue(pendingConfirm.column, pendingConfirm.previous)} → ${formatConfirmValue(pendingConfirm.column, pendingConfirm.nextValue)}`
                        : ''
                }
                onCancel={() => {
                    if (!processing) {
                        setPendingConfirm(null);
                    }
                }}
                onConfirm={confirmSave}
            />
        </div>
    );
}

export default function CustomerProfiles(props) {
    return <CustomerProfilesView {...props} />;
}

CustomerProfiles.layout = adminInertiaLayout;
