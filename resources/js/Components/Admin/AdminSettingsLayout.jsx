import { Link } from '@inertiajs/react';

const NAV = [
    { id: 'profile', label: 'Profile', href: '/admin/settings/profile' },
    { id: 'security', label: 'Security', href: '/admin/settings/security' },
    { id: 'appearance', label: 'Appearance', href: '/admin/settings/appearance' },
];

/**
 * Settings sub-navigation inside the admin Inertia shell.
 *
 * @param {{
 *   active: 'profile' | 'security' | 'appearance';
 *   heading: string;
 *   subheading: string;
 *   children: import('react').ReactNode;
 *   onNavigate?: (section: 'profile' | 'security' | 'appearance') => void;
 * }} props
 */
export function AdminSettingsLayout({ active, heading, subheading, children, onNavigate }) {
    return (
        <div className="mx-auto w-full max-w-[900px]">
            <header className="mb-8">
                <h1 className="font-montserrat text-2xl font-bold tracking-tight text-[#364153]">Settings</h1>
                <p className="mt-1 font-montserrat text-sm text-[#555555]">Manage your profile and account settings</p>
            </header>

            <div className="flex flex-col gap-8 lg:flex-row lg:items-start">
                <nav aria-label="Settings sections" className="flex shrink-0 flex-row flex-wrap gap-2 lg:w-44 lg:flex-col lg:gap-1">
                    {NAV.map((item) => {
                        const isActive = item.id === active;

                        const linkClassName = [
                            'rounded-[10px] px-3 py-2 font-montserrat text-sm font-semibold transition-colors',
                            isActive
                                ? 'bg-[#6E8C47]/15 text-[#3F4F2A]'
                                : 'text-[#555555] hover:bg-gray-100 hover:text-[#364153]',
                        ].join(' ');

                        if (onNavigate) {
                            return (
                                <button
                                    key={item.id}
                                    type="button"
                                    onClick={() => onNavigate(item.id)}
                                    aria-current={isActive ? 'page' : undefined}
                                    className={`text-left ${linkClassName}`}
                                >
                                    {item.label}
                                </button>
                            );
                        }

                        return (
                            <Link
                                key={item.id}
                                href={item.href}
                                aria-current={isActive ? 'page' : undefined}
                                className={linkClassName}
                            >
                                {item.label}
                            </Link>
                        );
                    })}
                </nav>

                <section className="min-w-0 w-full flex-1 rounded-[12px] border border-gray-200 bg-white p-6 shadow-sm">
                    <header className="mb-6 border-b border-gray-100 pb-4">
                        <h2 className="font-montserrat text-lg font-bold text-[#364153]">{heading}</h2>
                        <p className="mt-1 font-montserrat text-sm text-[#555555]">{subheading}</p>
                    </header>
                    <div className="w-full">{children}</div>
                </section>
            </div>
        </div>
    );
}
