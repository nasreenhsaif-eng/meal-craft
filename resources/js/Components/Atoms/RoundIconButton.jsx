const BASE =
    'inline-flex h-10 w-10 items-center justify-center rounded-full bg-white shadow-sm ring-1 ring-black/5 transition ' +
    'text-[#262A22] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#556C37] focus-visible:ring-offset-2';

const INTENT = {
    default: {
        hoverText: 'hover:text-[#556C37]',
    },
    danger: {
        hoverText: 'hover:text-[#D12A1C]',
    },
};

/**
 * White circular icon button used for admin actions on cards.
 *
 * @param {{
 *   icon: import('react').ReactNode;
 *   label: string;
 *   intent?: 'default' | 'danger';
 *   onClick?: () => void;
 *   disabled?: boolean;
 *   className?: string;
 *   type?: 'button' | 'submit' | 'reset';
 * }} props
 */
export default function RoundIconButton({
    icon,
    label,
    intent = 'default',
    onClick,
    disabled = false,
    className = '',
    type = 'button',
    ...props
}) {
    const intentConfig = INTENT[intent] ?? INTENT.default;

    return (
        <button
            type={type}
            onClick={onClick}
            disabled={disabled}
            aria-label={label}
            className={[
                BASE,
                'hover:bg-[#F9FAFB]',
                intentConfig.hoverText,
                'active:scale-[0.98]',
                'disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-white disabled:hover:text-[#262A22]',
                className,
            ].join(' ')}
            {...props}
        >
            <span className="inline-flex h-5 w-5 items-center justify-center [&_svg]:h-5 [&_svg]:w-5 [&_svg]:text-current">
                {icon}
            </span>
        </button>
    );
}
