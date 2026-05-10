import TextInput from '../../Components/Atoms/TextInput/TextInput.jsx';
import Button from '../../Components/Atoms/Button/Button.jsx';
import { useId, useState } from 'react';
import UserManagementTable from '../../Components/Admin/UserManagementTable.jsx';
import DropdownTextInput from '../../Components/Atoms/TextInput/DropdownTextInput.jsx';

function formatCurrency(value) {
    try {
        return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(value);
    } catch {
        return `$${value}`;
    }
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

/**
 * Admin command-center dashboard.
 *
 * @param {{
 *   adminName?: string;
 *   adminEmail?: string;
 * }} props
 */
export default function DashboardView({ adminName = 'Amina Saif', adminEmail = 'amina@mealcrafthq.test' }) {
    const accessSelectId = useId();
    const [adminNameValue, setAdminNameValue] = useState(adminName);
    const [adminEmailValue, setAdminEmailValue] = useState(adminEmail);
    const [adminPasswordValue, setAdminPasswordValue] = useState('');
    const [adminAccess, setAdminAccess] = useState('View');

    const totalCost = 121_900;
    const grossProfit = 62_300;
    const totalRevenues = 184_200;
    const profitColor = '#5A6B44';
    const costColor = '#D12A1C';
    const gold = '#D8A933';

    const handleSaveAdmin = () => {
        // Storybook/demo: no persistence yet; wire to Laravel later.
        setAdminPasswordValue('');
    };

    const [users, setUsers] = useState(() => [
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
    ]);

    const toggleUserActive = (userId) => {
        setUsers((prev) =>
            prev.map((u) => (u.id === userId ? { ...u, isDeactivated: !Boolean(u.isDeactivated) } : u)),
        );
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
                    <MetricMini label="Submissions" value="1,284" valueClassName={`text-[${gold}]`} />
                    <MetricMini label="Customers" value="342" />
                    <MetricMini label="Ingredients" value="4,961" />
                    <MetricMini label="Meals" value="128" />
                    <MetricMini label="Meal Plans" value="24" />
                </div>
            </section>

            <section className="grid gap-4 md:grid-cols-3">
                <SummaryCard label="Revenues" value={formatCurrency(totalRevenues)} valueClassName="text-[#262A22]" />
                <SummaryCard label="Total Cost" value={formatCurrency(totalCost)} valueClassName="text-[#D12A1C]" />
                <SummaryCard label="Gross Profit" value={formatCurrency(grossProfit)} valueClassName="text-[#5A6B44]" />
            </section>

            <UserManagementTable
                users={users}
                onToggleActive={toggleUserActive}
                onChangePassword={() => {}}
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

