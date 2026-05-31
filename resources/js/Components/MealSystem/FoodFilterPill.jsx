/**
 * Selectable food-filter pill — icon-led soft pill matching the Dairy reference.
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
                'inline-flex min-h-[34px] max-w-full shrink-0 items-center gap-2 rounded-[12px] border px-3 py-2',
                'font-montserrat text-[11px] font-bold uppercase leading-none tracking-wide whitespace-nowrap',
                'transition-colors duration-200',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-offset-2',
                isActive
                    ? 'border-[#5A6B44] bg-[#5A6B44] text-white'
                    : 'border-[#E5E7EB] bg-[#F8F9F6] text-[#5A6B44] hover:border-[#6E8C47]/40 hover:bg-[#6E8C47]/10',
                className,
            ].join(' ')}
        >
            {icon ? (
                <span className="flex h-4 w-4 shrink-0 items-center justify-center [&_svg]:text-current">
                    {icon}
                </span>
            ) : null}
            <span>{label}</span>
        </button>
    );
}

export default FoodFilterPill;
