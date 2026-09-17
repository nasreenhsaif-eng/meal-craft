/**
 * Onboarding selection button — pale green wash, left icon + label.
 * Used for diet protocol, activity, and gender.
 *
 * @param {{
 *   label: string;
 *   icon?: import('react').ReactNode;
 *   selected?: boolean;
 *   disabled?: boolean;
 *   busy?: boolean;
 *   onSelect?: () => void;
 *   describedBy?: string;
 *   className?: string;
 * }} props
 */
export function OnboardingOptionButton({
    label,
    icon,
    selected = false,
    disabled = false,
    busy = false,
    onSelect,
    describedBy,
    className = '',
}) {
    return (
        <button
            type="button"
            onClick={onSelect}
            disabled={disabled}
            aria-pressed={selected}
            aria-busy={busy}
            aria-describedby={selected && describedBy ? describedBy : undefined}
            className={[
                'box-border flex h-[68px] w-full min-w-0 items-center rounded-[12px] border-0 px-4 py-2 text-left shadow-none',
                icon ? 'gap-3' : '',
                'font-montserrat text-[13px] font-bold uppercase leading-tight tracking-wide text-[#364153]',
                'transition-all duration-200 ease-in-out',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6E8C47] focus-visible:ring-offset-2',
                'disabled:pointer-events-none disabled:opacity-60',
                selected
                    ? 'bg-[#6E8C47]/30 active:bg-[#6E8C47]/35'
                    : 'bg-[#6E8C47]/10 hover:bg-[#6E8C47]/20 active:bg-[#6E8C47]/30',
                className,
            ].join(' ')}
        >
            {icon ? (
                <span className="flex h-9 w-9 shrink-0 items-center justify-center text-[#364153]" aria-hidden>
                    {icon}
                </span>
            ) : null}
            <span className="min-w-0 flex-1">{label}</span>
            {busy ? (
                <span
                    className="inline-flex h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-current border-t-transparent"
                    aria-hidden
                />
            ) : null}
        </button>
    );
}

export default OnboardingOptionButton;
