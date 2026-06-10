import MealCraftLogo from '../Components/Atoms/Logo/MealCraftLogo.jsx';

/**
 * @param {object} props
 * @param {import('react').ReactNode} props.children
 * @param {string} [props.customerName]
 * @param {import('react').ReactNode} [props.headerActions]
 * @param {'default' | 'onboarding'} [props.layoutVariant]
 */
export default function CustomerLayout({
    children,
    customerName = '',
    headerActions = null,
    layoutVariant = 'default',
}) {
    const isOnboarding = layoutVariant === 'onboarding';

    const header = (
        <header
            className={
                isOnboarding
                    ? 'flex w-full items-center justify-between py-3 md:rounded-xl md:border md:border-gray-100 md:bg-white md:px-5 md:py-4 md:shadow-sm'
                    : 'flex w-full items-center justify-between border-b border-gray-100 bg-white px-6 py-4'
            }
        >
            <div className="flex min-w-0 items-center gap-3">
                <MealCraftLogo variant="seal-xs" width={40} alt="" />
                <div className="min-w-0">
                    <p className="font-montserrat text-base font-bold tracking-tight">Meal Craft</p>
                    <p className="font-montserrat text-[11px] font-bold uppercase tracking-[0.14em] text-[#555555]">
                        Your plan
                    </p>
                </div>
            </div>
            <div className="flex shrink-0 flex-wrap items-center justify-end gap-3 sm:gap-4 md:gap-5">
                {customerName ? (
                    <p className="max-w-[9rem] truncate text-sm font-medium text-[#555555] sm:max-w-none">
                        {customerName}
                    </p>
                ) : null}
                {headerActions}
            </div>
        </header>
    );

    if (isOnboarding) {
        return (
            <div className="flex min-h-screen w-full flex-col items-center justify-start bg-white px-3 py-4 font-sans text-[#262A22] md:justify-center md:bg-gray-50 md:px-8 md:py-8">
                <div className="flex w-full flex-col space-y-6 md:max-w-2xl md:space-y-8">{children}</div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-[#F8F9F6] font-sans text-[#262A22]">
            {header}
            <main className="mx-auto max-w-4xl px-6 py-10">{children}</main>
        </div>
    );
}
