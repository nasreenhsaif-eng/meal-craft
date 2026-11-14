import TextInput from '../../Components/Atoms/TextInput/TextInput.jsx';
import Button from '../../Components/Atoms/Button/Button.jsx';
import SquareCheckbox from '../../Components/Atoms/Icons/SquareCheckbox.jsx';
import { useMemo, useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import UserManagementTable from '../../Components/Admin/UserManagementTable.jsx';
import adminInertiaLayout from '../../lib/adminInertiaLayout.jsx';

/**
 * Brand checkbox from Storybook SquareCheckbox (`#556C37`).
 *
 * @param {{
 *   checked: boolean;
 *   onChange: (event: import('react').ChangeEvent<HTMLInputElement>) => void;
 *   children: import('react').ReactNode;
 * }} props
 */
function BrandCheckbox({ checked, onChange, children }) {
    return (
        <label className="group/item flex cursor-pointer items-center gap-2 font-montserrat text-sm text-[#262A22]">
            <input type="checkbox" className="sr-only" checked={checked} onChange={onChange} />
            <SquareCheckbox checked={checked} presentational />
            {children}
        </label>
    );
}

function formatCurrency(value) {
    try {
        return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(
            Number(value) || 0,
        );
    } catch {
        return `$${value}`;
    }
}

/** @param {number} value */
function formatCount(value) {
    const n = Number(value);
    if (!Number.isFinite(n)) {
        return '0';
    }
    return new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 }).format(n);
}

function SummaryCard({ label, value, valueClassName = 'text-[#5A6B44]' }) {
    return (
        <article className="rounded-[12px] border border-gray-200 bg-white p-5 shadow-sm">
            <p className="text-[11px] font-bold uppercase tracking-[0.14em] text-[#555555]">{label}</p>
            <p className={`mt-2 text-2xl font-bold tracking-tight ${valueClassName}`.trim()}>{value}</p>
        </article>
    );
}

function MetricMini({ label, value, valueClassName = 'text-[#5A6B44]' }) {
    return (
        <article className="min-w-[150px] flex-1 rounded-[12px] border border-gray-200 bg-white p-4 shadow-sm">
            <p className="text-[11px] font-bold uppercase tracking-[0.14em] text-[#555555]">{label}</p>
            <p className={`mt-2 text-2xl font-bold leading-none tracking-tight ${valueClassName}`.trim()}>{value}</p>
        </article>
    );
}

/** New admins start with no portal sections selected — CMG must opt in. */
const DEFAULT_PERMISSIONS = [];

const EMPTY_STATS = {
    totalSubmissions: 0,
    totalRevenue: 0,
    activeUsers: [],
    customersCount: 0,
    ingredientCount: 0,
    mealCount: 0,
    mealPlanCount: 0,
    totalCost: 0,
    grossProfit: 0,
};

/**
 * @param {{
 *   value: string[];
 *   onChange: (next: string[]) => void;
 *   checklist?: Array<{ group: string; items: Array<{ key: string; label: string }> }>;
 * }} props
 */
function PermissionChecklist({ value, onChange, checklist = [] }) {
    const groups = Array.isArray(checklist) ? checklist : [];
    const selected = Array.isArray(value) ? value : [];

    return (
        <div className="space-y-4 rounded-[12px] border border-gray-200 bg-[#F8F9F6] px-4 py-4">
            {groups.map((group) => (
                <div key={group.group}>
                    <p className="m-0 mb-2 font-montserrat text-[11px] font-bold uppercase tracking-[0.14em] text-[#555555]">
                        {group.group}
                    </p>
                    <div className="space-y-2">
                        {(group.items ?? []).map((item) => {
                            const checked = selected.includes(item.key);
                            return (
                                <BrandCheckbox
                                    key={item.key}
                                    checked={checked}
                                    onChange={(e) => {
                                        if (e.target.checked) {
                                            onChange([...selected, item.key]);
                                        } else {
                                            onChange(selected.filter((key) => key !== item.key));
                                        }
                                    }}
                                >
                                    {item.label}
                                </BrandCheckbox>
                            );
                        })}
                    </div>
                </div>
            ))}
        </div>
    );
}

function asUserList(value) {
    return Array.isArray(value) ? value : [];
}

/**
 * @param {{
 *   adminName?: string;
 *   adminEmail?: string;
 *   userSubmissionsCount?: number;
 *   customersCount?: number;
 *   ingredientCount?: number;
 *   mealCount?: number;
 *   mealPlanCount?: number;
 *   totalRevenues?: number;
 *   totalCost?: number;
 *   grossProfit?: number;
 *   latestRegisteredUsers?: object[];
 *   permissionChecklist?: Array<{ group: string; items: Array<{ key: string; label: string }> }>;
 *   isCraftMasterGeneral?: boolean;
 *   onToggleUserActive?: (userId: string) => void;
 *   onChangePassword?: (userId: string) => void;
 * }} props
 */
export function AdminDashboardInner({
    adminName = 'Amina Saif',
    adminEmail = 'amina@mealcrafthq.test',
    userSubmissionsCount = 1284,
    customersCount = 342,
    ingredientCount = 4961,
    mealCount = 128,
    mealPlanCount = 24,
    totalRevenues = 184_200,
    totalCost = 121_900,
    grossProfit = 62_300,
    latestRegisteredUsers = [],
    permissionChecklist = [],
    isCraftMasterGeneral = false,
    onToggleUserActive,
    onChangePassword,
}) {
    const [showCreate, setShowCreate] = useState(false);
    const [passwordUserId, setPasswordUserId] = useState(null);
    const [rightsUserId, setRightsUserId] = useState(null);
    const [rightsSaving, setRightsSaving] = useState(false);

    const createForm = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        admin_permissions: [...DEFAULT_PERMISSIONS],
        is_active: true,
    });

    const passwordForm = useForm({
        password: '',
        password_confirmation: '',
    });

    const tableUsers = asUserList(latestRegisteredUsers);
    const passwordUser = useMemo(
        () => tableUsers.find((user) => String(user.id) === String(passwordUserId)) ?? null,
        [passwordUserId, tableUsers],
    );
    const rightsUser = useMemo(
        () => tableUsers.find((user) => String(user.id) === String(rightsUserId)) ?? null,
        [rightsUserId, tableUsers],
    );

    const saveAdminRights = (user, nextPermissions) => {
        if (!user?.id) {
            return;
        }

        setRightsSaving(true);
        router.put(
            `/admin/users/${user.id}`,
            {
                name: user.name,
                email: user.email,
                admin_permissions: nextPermissions,
            },
            {
                preserveScroll: true,
                onFinish: () => setRightsSaving(false),
            },
        );
    };

    return (
        <div className="space-y-6 font-montserrat">
            <header className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 className="m-0 text-lg font-bold tracking-tight text-[#262A22]">Dashboard</h2>
                    <p className="mt-1 text-sm font-medium text-[#555555]">
                        {isCraftMasterGeneral
                            ? 'Manage admin portal users and assign Kitchen engine + User intelligence access.'
                            : 'High-level command center for kitchen operations.'}
                    </p>
                </div>
                {isCraftMasterGeneral ? (
                    <Button
                        type="button"
                        size="sm"
                        label={showCreate ? 'Close' : 'Create Admin'}
                        onClick={() => setShowCreate((open) => !open)}
                    />
                ) : null}
            </header>

            <section className="flex w-full flex-col gap-4">
                <div className="flex w-full flex-col gap-3 sm:flex-row">
                    <MetricMini label="Submissions" value={formatCount(userSubmissionsCount)} valueClassName="text-[#D8A933]" />
                    <MetricMini label="Customers" value={formatCount(customersCount)} />
                    <MetricMini label="Ingredients" value={formatCount(ingredientCount)} />
                    <MetricMini label="Meals" value={formatCount(mealCount)} />
                    <MetricMini label="Meal Plans" value={formatCount(mealPlanCount)} />
                </div>
            </section>

            <section className="grid gap-4 md:grid-cols-3">
                <SummaryCard label="Revenues" value={formatCurrency(totalRevenues)} valueClassName="text-[#262A22]" />
                <SummaryCard label="Total Cost" value={formatCurrency(totalCost)} valueClassName="text-[#D12A1C]" />
                <SummaryCard label="Gross Profit" value={formatCurrency(grossProfit)} valueClassName="text-[#5A6B44]" />
            </section>

            {isCraftMasterGeneral && showCreate ? (
                <section className="rounded-[12px] border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 className="m-0 text-base font-bold tracking-tight text-[#262A22]">Create admin user</h3>
                    <p className="mt-1 text-sm font-medium text-[#555555]">
                        Assign a temporary password and checklist of portal sections.
                    </p>

                    <form
                        className="mt-4 grid gap-4 sm:grid-cols-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            createForm.post('/admin/users', {
                                preserveScroll: true,
                                onSuccess: () => {
                                    createForm.reset();
                                    createForm.setData('admin_permissions', [...DEFAULT_PERMISSIONS]);
                                    createForm.setData('is_active', true);
                                    setShowCreate(false);
                                },
                            });
                        }}
                    >
                        <TextInput
                            label="Name"
                            placeholder="Full name"
                            value={createForm.data.name}
                            onChange={(e) => createForm.setData('name', e.target.value)}
                            error={createForm.errors.name}
                            className="!max-w-none"
                            required
                        />
                        <TextInput
                            label="Email"
                            type="email"
                            placeholder="name@mealcrafthq.test"
                            value={createForm.data.email}
                            onChange={(e) => createForm.setData('email', e.target.value)}
                            error={createForm.errors.email}
                            className="!max-w-none"
                            required
                        />
                        <TextInput
                            label="Temporary password"
                            type="password"
                            revealPassword
                            value={createForm.data.password}
                            onChange={(e) => createForm.setData('password', e.target.value)}
                            error={createForm.errors.password}
                            className="!max-w-none"
                            required
                        />
                        <TextInput
                            label="Confirm password"
                            type="password"
                            revealPassword
                            value={createForm.data.password_confirmation}
                            onChange={(e) => createForm.setData('password_confirmation', e.target.value)}
                            error={createForm.errors.password_confirmation}
                            className="!max-w-none"
                            required
                        />

                        <div className="sm:col-span-2">
                            <p className="mb-2 text-sm font-semibold text-[#262A22]">Access rights</p>
                            <PermissionChecklist
                                value={createForm.data.admin_permissions}
                                checklist={permissionChecklist}
                                onChange={(next) => createForm.setData('admin_permissions', next)}
                            />
                            {createForm.errors.admin_permissions ? (
                                <p className="mt-2 text-sm text-[#D12A1C]">{createForm.errors.admin_permissions}</p>
                            ) : null}
                        </div>

                        <div className="sm:col-span-2">
                            <BrandCheckbox
                                checked={Boolean(createForm.data.is_active)}
                                onChange={(e) => createForm.setData('is_active', e.target.checked)}
                            >
                                Active on create
                            </BrandCheckbox>
                        </div>

                        <div className="sm:col-span-2">
                            <Button
                                type="submit"
                                size="sm"
                                label={createForm.processing ? 'Creating…' : 'Create admin'}
                                disabled={createForm.processing}
                            />
                        </div>
                    </form>
                </section>
            ) : null}

            {isCraftMasterGeneral && rightsUser ? (
                <section className="rounded-[12px] border border-gray-200 bg-white p-6 shadow-sm">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 className="m-0 text-base font-bold tracking-tight text-[#262A22]">
                                Access rights — {rightsUser.name}
                            </h3>
                            <p className="mt-1 text-sm font-medium text-[#555555]">
                                Click a checkbox to add or remove a portal section. Changes save immediately.
                                {rightsSaving ? ' Saving…' : null}
                            </p>
                        </div>
                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            label="Close"
                            onClick={() => setRightsUserId(null)}
                        />
                    </div>
                    <div className="mt-4">
                        <PermissionChecklist
                            value={Array.isArray(rightsUser.permissions) ? rightsUser.permissions : []}
                            checklist={permissionChecklist}
                            onChange={(next) => saveAdminRights(rightsUser, next)}
                        />
                    </div>
                </section>
            ) : null}

            {isCraftMasterGeneral && passwordUser ? (
                <section className="rounded-[12px] border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 className="m-0 text-base font-bold tracking-tight text-[#262A22]">
                        Change password — {passwordUser.name}
                    </h3>
                    <form
                        className="mt-4 grid gap-4 sm:grid-cols-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            passwordForm.put(`/admin/users/${passwordUser.id}/password`, {
                                preserveScroll: true,
                                onSuccess: () => {
                                    passwordForm.reset();
                                    setPasswordUserId(null);
                                },
                            });
                        }}
                    >
                        <TextInput
                            label="New password"
                            type="password"
                            revealPassword
                            value={passwordForm.data.password}
                            onChange={(e) => passwordForm.setData('password', e.target.value)}
                            error={passwordForm.errors.password}
                            className="!max-w-none"
                            required
                        />
                        <TextInput
                            label="Confirm password"
                            type="password"
                            revealPassword
                            value={passwordForm.data.password_confirmation}
                            onChange={(e) => passwordForm.setData('password_confirmation', e.target.value)}
                            error={passwordForm.errors.password_confirmation}
                            className="!max-w-none"
                            required
                        />
                        <div className="flex flex-wrap gap-3 sm:col-span-2">
                            <Button
                                type="submit"
                                size="sm"
                                label={passwordForm.processing ? 'Saving…' : 'Update password'}
                                disabled={passwordForm.processing}
                            />
                            <Button
                                type="button"
                                size="sm"
                                variant="secondary"
                                label="Cancel"
                                onClick={() => setPasswordUserId(null)}
                            />
                        </div>
                    </form>
                </section>
            ) : null}

            <UserManagementTable
                users={tableUsers}
                canManage={isCraftMasterGeneral}
                selectedRightsUserId={rightsUserId}
                onToggleActive={(userId) => onToggleUserActive?.(userId)}
                onEditRights={(userId) => {
                    if (!isCraftMasterGeneral) {
                        return;
                    }
                    setPasswordUserId(null);
                    setRightsUserId((current) =>
                        String(current) === String(userId) ? null : userId,
                    );
                }}
                onChangePassword={(userId) => {
                    if (!isCraftMasterGeneral) {
                        return;
                    }
                    setRightsUserId(null);
                    setPasswordUserId(userId);
                    passwordForm.reset();
                    onChangePassword?.(userId);
                }}
            />

            {/* Keep Storybook-friendly unused demo fields referenced so props stay stable */}
            <span className="sr-only">
                {adminName} {adminEmail}
            </span>
        </div>
    );
}

/** Inertia page: authenticated admin shell + dashboard content. */
export default function Dashboard({
    users = [],
    permissionChecklist = [],
    isCraftMasterGeneral = false,
    adminName,
    adminEmail,
    stats: statsProp,
}) {
    const page = usePage();
    const authUser = page.props?.auth?.user;
    const isCraftMaster = Boolean(isCraftMasterGeneral || authUser?.isCraftMasterGeneral);
    const stats = { ...EMPTY_STATS, ...(statsProp ?? page.props?.stats ?? {}) };
    const portalUsers = asUserList(users.length ? users : stats.activeUsers);

    return (
        <>
            <Head title="Admin Dashboard" />
            <AdminDashboardInner
                adminName={adminName ?? page.props?.adminName}
                adminEmail={adminEmail ?? page.props?.adminEmail}
                userSubmissionsCount={stats.totalSubmissions}
                customersCount={stats.customersCount}
                ingredientCount={stats.ingredientCount}
                mealCount={stats.mealCount}
                mealPlanCount={stats.mealPlanCount}
                totalRevenues={stats.totalRevenue}
                totalCost={stats.totalCost}
                grossProfit={stats.grossProfit}
                latestRegisteredUsers={portalUsers}
                permissionChecklist={permissionChecklist}
                isCraftMasterGeneral={isCraftMaster}
                onToggleUserActive={(userId) => {
                    router.post(`/admin/users/${userId}/toggle-active`, {}, { preserveScroll: true });
                }}
                onChangePassword={() => {}}
            />
        </>
    );
}

Dashboard.layout = adminInertiaLayout;
