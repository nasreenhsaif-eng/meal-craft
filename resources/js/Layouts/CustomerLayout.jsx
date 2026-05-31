import MealCraftLogo from '../Components/Atoms/Logo/MealCraftLogo.jsx';

/**
 * @param {object} props
 * @param {import('react').ReactNode} props.children
 * @param {string} [props.customerName]
 */
export default function CustomerLayout({ children, customerName = '' }) {
    return (
        <div className="min-h-screen bg-[#F8F9F6] font-sans text-[#262A22]">
            <header className="border-b border-gray-200 bg-white">
                <div className="mx-auto flex max-w-4xl items-center justify-between px-6 py-4">
                    <div className="flex items-center gap-3">
                        <MealCraftLogo variant="seal-xs" width={40} alt="" />
                        <div>
                            <p className="font-montserrat text-base font-bold tracking-tight">Meal Craft</p>
                            <p className="font-montserrat text-[11px] font-bold uppercase tracking-[0.14em] text-[#555555]">
                                Your plan
                            </p>
                        </div>
                    </div>
                    {customerName ? (
                        <p className="text-sm font-medium text-[#555555]">{customerName}</p>
                    ) : null}
                </div>
            </header>
            <main className="mx-auto max-w-4xl px-6 py-10">{children}</main>
        </div>
    );
}
