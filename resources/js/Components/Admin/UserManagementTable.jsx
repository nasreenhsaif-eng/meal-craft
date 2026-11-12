import TextLink from '../Atoms/TextLink.jsx';
import { useMemo } from 'react';

/**
 * @typedef {{
 *   id: string;
 *   name: string;
 *   email: string;
 *   roleLabel?: string;
 *   permissions?: string[];
 *   accessRight?: string;
 *   isDeactivated?: boolean;
 *   isCraftMasterGeneral?: boolean;
 * }} UserRow
 */

/**
 * User management table (Admin dashboard operational area).
 *
 * @param {{
 *   users: UserRow[];
 *   onToggleActive?: (userId: string) => void;
 *   onChangePassword?: (userId: string) => void;
 *   canManage?: boolean;
 *   className?: string;
 * }} props
 */
export default function UserManagementTable({
    users,
    onToggleActive,
    onChangePassword,
    canManage = true,
    className = '',
}) {
    const sortedUsers = useMemo(() => {
        const rows = Array.isArray(users) ? users : [];
        return [...rows].sort((a, b) => {
            const da = Boolean(a.isDeactivated);
            const db = Boolean(b.isDeactivated);
            if (da !== db) {
                return da ? 1 : -1;
            }
            const nameCmp = String(a.name ?? '').localeCompare(String(b.name ?? ''), undefined, { sensitivity: 'base' });
            if (nameCmp !== 0) {
                return nameCmp;
            }
            return String(a.email ?? '').localeCompare(String(b.email ?? ''), undefined, { sensitivity: 'base' });
        });
    }, [users]);

    return (
        <section className={`rounded-[12px] border border-gray-200 bg-white shadow-sm ${className}`.trim()}>
            <header className="flex flex-col gap-1 border-b border-gray-200 px-5 py-4">
                <h3 className="m-0 font-montserrat text-base font-bold tracking-tight text-[#262A22]">User Management</h3>
                <p className="m-0 font-montserrat text-sm font-medium text-[#555555]">
                    Admin portal users and access rights.
                </p>
            </header>

            <div className="overflow-x-auto">
                <table className="w-full min-w-[760px] border-collapse text-left font-montserrat text-sm">
                    <thead>
                        <tr className="bg-white">
                            <th className="px-5 py-3 text-[11px] font-bold uppercase tracking-[0.14em] text-[#555555]">
                                Name
                            </th>
                            <th className="px-5 py-3 text-[11px] font-bold uppercase tracking-[0.14em] text-[#555555]">
                                Email
                            </th>
                            <th className="px-5 py-3 text-[11px] font-bold uppercase tracking-[0.14em] text-[#555555]">
                                Role
                            </th>
                            <th className="px-5 py-3 text-[11px] font-bold uppercase tracking-[0.14em] text-[#555555]">
                                Access Rights
                            </th>
                            {canManage ? (
                                <th className="px-5 py-3 text-[11px] font-bold uppercase tracking-[0.14em] text-[#555555]">
                                    Actions
                                </th>
                            ) : null}
                        </tr>
                    </thead>
                    <tbody>
                        {sortedUsers.length === 0 ? (
                            <tr>
                                <td colSpan={canManage ? 5 : 4} className="px-5 py-6 text-[#555555]">
                                    No admin portal users yet.
                                </td>
                            </tr>
                        ) : null}
                        {sortedUsers.map((u, idx) => {
                            const deactivated = Boolean(u.isDeactivated);
                            const zebra = idx % 2 === 1 ? 'bg-[#F8F9F6]' : 'bg-white';
                            const rights = u.isCraftMasterGeneral
                                ? 'All sections'
                                : Array.isArray(u.permissions) && u.permissions.length > 0
                                  ? `${u.permissions.length} section${u.permissions.length === 1 ? '' : 's'}`
                                  : u.accessRight ?? 'None';

                            return (
                                <tr key={u.id} className={`${zebra} border-t border-gray-100`}>
                                    <td
                                        className={`px-5 py-3 font-semibold ${
                                            deactivated ? 'text-[#71717a] line-through' : 'text-[#262A22]'
                                        }`.trim()}
                                    >
                                        {u.name}
                                    </td>
                                    <td className={`px-5 py-3 ${deactivated ? 'text-[#71717a] line-through' : 'text-[#364153]'}`.trim()}>
                                        {u.email}
                                    </td>
                                    <td className={`px-5 py-3 ${deactivated ? 'text-[#71717a]' : 'text-[#364153]'}`.trim()}>
                                        {u.roleLabel ?? 'Admin'}
                                    </td>
                                    <td className={`px-5 py-3 font-semibold ${deactivated ? 'text-[#71717a]' : 'text-[#364153]'}`.trim()}>
                                        {rights}
                                    </td>
                                    {canManage ? (
                                        <td className="px-5 py-3">
                                            {u.isCraftMasterGeneral ? (
                                                <span className="text-xs text-[#888888]">Protected</span>
                                            ) : (
                                                <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
                                                    <TextLink
                                                        href="#change-password"
                                                        className="text-sm"
                                                        onClick={(e) => {
                                                            e.preventDefault();
                                                            onChangePassword?.(u.id);
                                                        }}
                                                    >
                                                        Change Password
                                                    </TextLink>

                                                    <TextLink
                                                        href="#toggle-active"
                                                        className={
                                                            deactivated
                                                                ? 'text-sm !text-[#5A6B44] hover:!text-[#5A6B44]'
                                                                : 'text-sm !text-[#D12A1C] hover:!text-[#B42318]'
                                                        }
                                                        onClick={(e) => {
                                                            e.preventDefault();
                                                            onToggleActive?.(u.id);
                                                        }}
                                                    >
                                                        {deactivated ? 'Activate' : 'Deactivate User'}
                                                    </TextLink>
                                                </div>
                                            )}
                                        </td>
                                    ) : null}
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </section>
    );
}
