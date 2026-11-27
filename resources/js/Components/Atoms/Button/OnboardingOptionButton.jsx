/**
 * Onboarding selection button — same brand-primary wash as Button `secondary`
 * (`/10` · hover `/20` · selected `/30`), with optional icon + label.
 * Used for diet protocol, activity, and gender.
 *
 * @param {{
 *   label: string;
 *   detail?: string | null;
 *   icon?: import('react').ReactNode;
 *   selected?: boolean;
 *   disabled?: boolean;
 *   busy?: boolean;
 *   size?: 'md' | 'sm';
 *   align?: 'start' | 'center';
 *   onSelect?: () => void;
 *   describedBy?: string;
 *   className?: string;
 * }} props
 */
export function OnboardingOptionButton({
    label,
    detail = null,
    icon,
    selected = false,
    disabled = false,
    busy = false,
    size = 'md',
    align = 'start',
    onSelect,
    describedBy,
    className = '',
}) {
    const sizeClass = size === 'sm' ? 'h-[52px]' : 'h-[68px]';
    const isCentered = align === 'center';
    const detailLabel = typeof detail === 'string' && detail.trim() !== '' ? detail.trim() : null;

    return (
        <button
            type="button"
            onClick={onSelect}
            disabled={disabled}
            aria-pressed={selected}
            aria-busy={busy}
            aria-describedby={selected && describedBy ? describedBy : undefined}
            className={[
                'box-border flex w-full min-w-0 items-center rounded-[12px] border px-4 py-2 shadow-none',
                'no-underline [-webkit-tap-highlight-color:transparent]',
                sizeClass,
                isCentered ? 'justify-center text-center' : 'text-left',
                icon ? 'gap-3' : '',
                'font-montserrat text-[13px] font-bold uppercase leading-tight tracking-wide text-body',
                'transition-all duration-200 ease-in-out',
                'outline-none focus:outline-none focus-visible:outline-none',
                'focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-primary',
                'disabled:pointer-events-none disabled:opacity-60',
                selected
                    ? 'border-brand-primary bg-brand-primary/30'
                    : 'border-brand-primary/30 bg-brand-primary/10 hover:border-brand-primary/50 hover:bg-brand-primary/20 active:border-brand-primary active:bg-brand-primary/30',
                className,
            ].join(' ')}
        >
            {icon ? (
                <span className="flex h-9 w-9 shrink-0 items-center justify-center text-body no-underline [&>*]:!h-9 [&>*]:!w-9" aria-hidden>
                    {icon}
                </span>
            ) : null}
            <span className={['no-underline', isCentered ? 'min-w-0' : 'min-w-0 flex-1'].join(' ')}>{label}</span>
            {detailLabel ? (
                <span className="ms-3 shrink-0 font-montserrat text-[12px] font-semibold normal-case tracking-normal tabular-nums text-body">
                    {detailLabel}
                </span>
            ) : null}
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
