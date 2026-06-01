/**
 * Uniform onboarding selection button — secondary soft-green palette (diet protocol, activity, etc.).
 *
 * @param {{
 *   label: string;
 *   icon?: import('react').ReactNode;
 *   selected?: boolean;
 *   onSelect?: () => void;
 *   describedBy?: string;
 *   className?: string;
 * }} props
 */
export function OnboardingOptionButton({
    label,
    icon,
    selected = false,
    onSelect,
    describedBy,
    className = '',
}) {
    return (
        <button
            type="button"
            onClick={onSelect}
            aria-pressed={selected}
            aria-describedby={selected && describedBy ? describedBy : undefined}
            className={[
                'box-border flex h-[68px] w-full min-w-0 items-center rounded-[12px] border-0 px-4 py-2 text-left shadow-none',
                icon ? 'gap-3' : '',
                'font-montserrat text-[13px] font-bold leading-tight tracking-wide text-[#364153]',
                'transition-all duration-200 ease-in-out',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6E8C47] focus-visible:ring-offset-2',
                selected
                    ? 'bg-[#6E8C47]/30 active:bg-[#6E8C47]/35'
                    : 'bg-[#6E8C47]/10 hover:bg-[#6E8C47]/20 active:bg-[#6E8C47]/30',
                className,
            ].join(' ')}
        >
            {icon ? (
                <span
                    className="flex h-9 w-9 shrink-0 items-center justify-center text-[#364153]"
                    aria-hidden
                >
                    {icon}
                </span>
            ) : null}
            <span className="min-w-0 flex-1">{label}</span>
        </button>
    );
}

export default OnboardingOptionButton;
