import { router, usePage } from '@inertiajs/react';
import { AdminLayout } from '../Components/Admin/AdminLayout.jsx';
import { ADMIN_NAV_HREFS } from '../Components/Admin/AdminSidebar.jsx';

/**
 * Authenticated admin shell: same chrome as Storybook `AdminLayout`, wired to Inertia navigation.
 *
 * @param {{
 *   children: import('react').ReactNode;
 *   pageTitle: string;
 *   activePath?: string;
 *   showSearch?: boolean;
 *   hidePageTitle?: boolean;
 *   user?: { name?: string; email?: string; initials?: string } | null;
 *   contentWrapperClassName?: string;
 * }} props
 */
export default function AuthenticatedLayout({
    children,
    pageTitle,
    activePath = '',
    showSearch = false,
    hidePageTitle = false,
    user: userProp,
    contentWrapperClassName,
}) {
    const { props } = usePage();
    const authUser = userProp ?? props.auth?.user;
    const flashSuccess = props.flash?.success;
    const flashError = props.flash?.error;

    const handleNavigate = (path) => {
        const url = ADMIN_NAV_HREFS[path];
        if (url) {
            router.visit(url);
        }
    };

    const userAvatar =
        authUser?.initials != null && String(authUser.initials).length > 0 ? (
            <span
                className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#556C37] text-xs font-bold text-white"
                aria-hidden
            >
                {authUser.initials}
            </span>
        ) : undefined;

    return (
        <AdminLayout
            pageTitle={pageTitle}
            activePath={activePath}
            onNavigate={handleNavigate}
            userAvatar={userAvatar}
            onAccountSettingsClick={() => router.visit('/admin/settings/profile')}
            showSearch={showSearch}
            hidePageTitle={hidePageTitle}
            contentWrapperClassName={contentWrapperClassName}
        >
            {flashSuccess ? (
                <div
                    role="status"
                    className="mb-4 rounded-[12px] border border-[#6E8C47]/40 bg-[#6E8C47]/10 px-4 py-3 font-montserrat text-sm font-semibold text-[#3F4F2A]"
                >
                    {flashSuccess}
                </div>
            ) : null}
            {flashError ? (
                <div
                    role="alert"
                    className="mb-4 rounded-[12px] border border-[#C44F5D]/40 bg-[#C44F5D]/10 px-4 py-3 font-montserrat text-sm font-semibold text-[#7A2F38]"
                >
                    {flashError}
                </div>
            ) : null}
            {children}
        </AdminLayout>
    );
}
