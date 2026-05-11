import TextInput from '../../Components/Atoms/TextInput/TextInput.jsx';
import Button from '../../Components/Atoms/Button/Button.jsx';
import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import UserManagementTable from '../../Components/Admin/UserManagementTable.jsx';
import DropdownTextInput from '../../Components/Atoms/TextInput/DropdownTextInput.jsx';
import AdminInertiaShell from '../../Layouts/AdminInertiaShell.jsx';

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

/** @type {{ id: string; name: string; email: string; accessRight: 'View' | 'View and Edit'; isDeactivated?: boolean }[]} */
const STORYBOOK_LATEST_USERS = [
    {
        id: 'u-1',
        name: 'Amina Saif',
        email: 'amina@mealcrafthq.test',
        accessRight: 'View and Edit',
        isDeactivated: false,
    },
    {
        id: 'u-2',
        name: 'Dina Nour',
        email: 'dina@mealcrafthq.test',
        accessRight: 'View',
        isDeactivated: false,
    },
    {
        id: 'u-3',
        name: 'Omar Khaled',
        email: 'omar@mealcrafthq.test',
        accessRight: 'View',
        isDeactivated: true,
    },
];

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
 * Inner dashboard markup (Storybook / tests). Matches the original DashboardView layout and Tailwind classes.
 *
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
 *   latestRegisteredUsers?: { id: string; name: string; email: string; accessRight: 'View' | 'View and Edit'; isDeactivated?: boolean }[];
 *   onToggleUserActive?: (userId: string) => void;
 *   onRequestPasswordReset?: (userId: string) => void;
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
    latestRegisteredUsers = STORYBOOK_LATEST_USERS,
    onToggleUserActive,
    onRequestPasswordReset,
}) {
    const [adminNameValue, setAdminNameValue] = useState(adminName);
    const [adminEmailValue, setAdminEmailValue] = useState(adminEmail);
    const [adminPasswordValue, setAdminPasswordValue] = useState('');
    const [adminAccess, setAdminAccess] = useState('View');

    const gold = '#D8A933';

    const [demoUsers, setDemoUsers] = useState(() => latestRegisteredUsers);

    useEffect(() => {
        if (!onToggleUserActive) {
            setDemoUsers(latestRegisteredUsers);
        }
    }, [latestRegisteredUsers, onToggleUserActive]);

    const tableUsers = onToggleUserActive ? latestRegisteredUsers : demoUsers;

    const handleToggleActive = (userId) => {
        if (onToggleUserActive) {
            onToggleUserActive(userId);
            return;
        }
        setDemoUsers((prev) =>
            prev.map((u) => (u.id === userId ? { ...u, isDeactivated: !Boolean(u.isDeactivated) } : u)),
        );
    };

    const handleChangePassword = (userId) => {
        if (onRequestPasswordReset) {
            onRequestPasswordReset(userId);
        }
    };

    const handleSaveAdmin = () => {
        setAdminPasswordValue('');
    };

    return (
        <div className="space-y-6 font-montserrat">
            <header className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 className="m-0 text-lg font-bold tracking-tight text-[#262A22]">Dashboard</h2>
                    <p className="mt-1 text-sm font-medium text-[#555555]">High-level command center for kitchen operations.</p>
                </div>
            </header>

            <section className="flex w-full flex-col gap-4">
                <div className="flex w-full flex-col gap-3 sm:flex-row">
                    <MetricMini label="Submissions" value={formatCount(userSubmissionsCount)} valueClassName={`text-[${gold}]`} />
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

            <UserManagementTable
                users={tableUsers}
                onToggleActive={handleToggleActive}
                onChangePassword={handleChangePassword}
            />

            <section className="grid gap-4 md:grid-cols-1">
                <article className="rounded-[12px] border border-gray-200 bg-white p-6 shadow-sm">
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <h3 className="m-0 text-base font-bold tracking-tight text-[#262A22]">Create New User</h3>
                            <p className="mt-1 text-sm font-medium text-[#555555]">Provision a new user with access rights (demo).</p>
                        </div>
                    </div>

                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                        <TextInput
                            label="Name"
                            placeholder="Full name"
                            value={adminNameValue}
                            onChange={(e) => setAdminNameValue(e.target.value)}
                            className="!max-w-none"
                        />
                        <TextInput
                            label="Email"
                            placeholder="name@mealcrafthq.test"
                            value={adminEmailValue}
                            onChange={(e) => setAdminEmailValue(e.target.value)}
                            className="!max-w-none"
                        />
                        <TextInput
                            label="Password"
                            type="password"
                            revealPassword
                            placeholder="••••••••"
                            value={adminPasswordValue}
                            onChange={(e) => setAdminPasswordValue(e.target.value)}
                            className="!max-w-none"
                        />
                        <DropdownTextInput
                            label="Access Rights"
                            value={adminAccess}
                            options={['View', 'View and Edit']}
                            onChange={setAdminAccess}
                            className="!max-w-none"
                        />

                        <div className="sm:col-span-2">
                            <div className="flex justify-start">
                                <Button label="Save" variant="primary" size="sm" type="button" onClick={handleSaveAdmin} />
                            </div>
                        </div>
                    </div>
                </article>
            </section>
        </div>
    );
}

/** Inertia page: authenticated admin shell + dashboard content (same markup as Storybook `DashboardView`). */
export default function Dashboard() {
    const { props } = usePage();
    const stats = { ...EMPTY_STATS, ...(props.stats ?? {}) };

    const handleToggleUserActive = (userId) => {
        router.post(`/admin/users/${userId}/toggle-active`, {}, { preserveScroll: true });
    };

    const handleRequestPasswordReset = (userId) => {
        router.post(`/admin/users/${userId}/password-reset`, {}, { preserveScroll: true });
    };

    return (
        <AdminDashboardInner
            adminName={props.adminName}
            adminEmail={props.adminEmail}
            userSubmissionsCount={stats.totalSubmissions}
            customersCount={stats.customersCount}
            ingredientCount={stats.ingredientCount}
            mealCount={stats.mealCount}
            mealPlanCount={stats.mealPlanCount}
            totalRevenues={stats.totalRevenue}
            totalCost={stats.totalCost}
            grossProfit={stats.grossProfit}
            latestRegisteredUsers={stats.activeUsers}
            onToggleUserActive={handleToggleUserActive}
            onRequestPasswordReset={handleRequestPasswordReset}
        />
    );
}

Dashboard.layout = (page) => <AdminInertiaShell>{page}</AdminInertiaShell>;
