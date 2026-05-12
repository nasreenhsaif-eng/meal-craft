const BASE =
    'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-[10px] ' +
    'border border-[#E5E7EB] bg-white shadow-sm ' +
    'text-[#262A22] transition-colors ' +
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#556C37] focus-visible:ring-offset-2';

const INTENT = {
    default: {
        hoverText: 'hover:text-[#556C37]',
    },
    danger: {
        hoverText: 'hover:text-[#D12A1C]',
    },
};

/**
 * Icon-only rounded-square control (matches segmented toggles + card chrome). Accessible name via `ariaLabel`.
 *
 * @param {{
 *   icon: import('react').ReactNode;
 *   ariaLabel: string;
 *   intent?: 'default' | 'danger';
 *   onClick?: () => void;
 *   disabled?: boolean;
 *   className?: string;
 *   type?: 'button' | 'submit' | 'reset';
 * }} props
 */
export default function RoundIconButton({
    icon,
    ariaLabel,
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
            aria-label={ariaLabel}
            className={[
                BASE,
                'hover:bg-[#F9FAFB] hover:border-[#E5E7EB]',
                intentConfig.hoverText,
                'active:scale-[0.98]',
                'disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-white disabled:hover:border-[#E5E7EB] disabled:hover:text-[#262A22]',
                className,
            ].join(' ')}
            {...props}
        >
            <span className="inline-flex h-5 w-5 shrink-0 items-center justify-center [&_svg]:h-5 [&_svg]:w-5 [&_svg]:text-current">
                {icon}
            </span>
        </button>
    );
}
