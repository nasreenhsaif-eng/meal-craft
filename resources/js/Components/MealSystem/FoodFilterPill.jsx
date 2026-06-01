/**
 * Selectable food-filter pill — secondary soft-green palette when active.
 *
 * @param {{
 *   label: string;
 *   icon?: import('react').ReactNode;
 *   isActive?: boolean;
 *   onClick?: () => void;
 *   className?: string;
 * }} props
 */
export function FoodFilterPill({ label, icon, isActive = false, onClick, className = '' }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={isActive}
            className={[
                'inline-flex min-h-[34px] max-w-full shrink-0 items-center gap-2 rounded-[12px] border-0 px-3 py-2 shadow-none',
                'font-montserrat text-[11px] font-bold uppercase leading-none tracking-wide whitespace-nowrap text-[#364153]',
                'transition-all duration-200 ease-in-out',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6E8C47] focus-visible:ring-offset-2',
                isActive
                    ? 'bg-[#6E8C47]/30 active:bg-[#6E8C47]/35'
                    : 'bg-[#6E8C47]/10 hover:bg-[#6E8C47]/20 active:bg-[#6E8C47]/30',
                className,
            ].join(' ')}
        >
            {icon ? (
                <span className="flex h-4 w-4 shrink-0 items-center justify-center text-[#364153] [&_svg]:text-current">
                    {icon}
                </span>
            ) : null}
            <span>{label}</span>
        </button>
    );
}

export default FoodFilterPill;
