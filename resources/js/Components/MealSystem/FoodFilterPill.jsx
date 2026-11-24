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
                'inline-flex min-h-[44px] max-w-full shrink-0 items-center gap-2.5 rounded-[12px] border-0 px-3.5 py-2.5 shadow-none',
                'font-montserrat text-[11px] font-bold uppercase leading-none tracking-wide whitespace-nowrap text-body',
                'transition-all duration-200 ease-in-out',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6E8C47] focus-visible:ring-offset-2',
                isActive
                    ? 'bg-[#6E8C47]/30 active:bg-[#6E8C47]/35'
                    : 'bg-[#6E8C47]/10 hover:bg-[#6E8C47]/20 active:bg-[#6E8C47]/30',
                className,
            ].join(' ')}
        >
            {icon ? (
                <span className="flex h-8 w-8 shrink-0 items-center justify-center text-body [&>*]:!h-8 [&>*]:!w-8 [&_svg]:h-8 [&_svg]:w-8 [&_svg]:text-current">
                    {icon}
                </span>
            ) : null}
            <span>{label}</span>
        </button>
    );
}

export default FoodFilterPill;
