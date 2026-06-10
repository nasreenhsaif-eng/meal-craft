/**
 * Gender choice — full-width secondary capsule with centered icon + label.
 *
 * Uses an inner visual shell so browser button intrinsic sizing cannot collapse width.
 *
 * @param {{
 *   label: string;
 *   selected?: boolean;
 *   disabled?: boolean;
 *   busy?: boolean;
 *   onSelect?: () => void;
 *   icon?: import('react').ReactNode;
 *   className?: string;
 * }} props
 */
export function GenderOptionCard({
    label,
    selected = false,
    disabled = false,
    busy = false,
    onSelect,
    icon,
    className = '',
}) {
    const shellClass = selected
        ? 'border-[#5A6B44] bg-[#5A6B44] text-white'
        : 'border-gray-200 bg-[#6E8C47]/10 text-[#364153] hover:border-[#6E8C47]/40 hover:bg-[#6E8C47]/20 active:bg-[#6E8C47]/30';

    return (
        <button
            type="button"
            onClick={onSelect}
            disabled={disabled}
            aria-pressed={selected}
            aria-busy={busy}
            className={[
                'mc-gender-option block w-full appearance-none border-0 bg-transparent p-0 shadow-none',
                'outline-none focus:outline-none focus:ring-0 active:outline-none',
                'transition-opacity duration-200 ease-in-out [-webkit-tap-highlight-color:transparent]',
                'disabled:pointer-events-none disabled:opacity-60',
                className,
            ].join(' ')}
        >
            <span
                className={[
                    'mc-gender-option__shell box-border flex min-h-[56px] w-full items-center justify-center gap-3 rounded-xl border-2 px-6 py-4',
                    'font-montserrat text-base font-bold leading-none tracking-wide',
                    'transition-all duration-200 ease-in-out',
                    selected ? 'active:border-[#4F5F3D] active:bg-[#4F5F3D]' : 'active:scale-[0.99]',
                    busy ? 'cursor-wait' : '',
                    shellClass,
                ].join(' ')}
            >
                {icon ? (
                    <span className="inline-flex shrink-0 items-center justify-center text-current" aria-hidden>
                        {icon}
                    </span>
                ) : null}
                <span className="whitespace-nowrap">{label}</span>
                {busy ? (
                    <span
                        className="inline-flex h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-current border-t-transparent"
                        aria-hidden
                    />
                ) : null}
            </span>
        </button>
    );
}

function IconMale({ className = '' }) {
    return (
        <svg
            width="24"
            height="24"
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden
            className={`block ${className}`.trim()}
        >
            <circle cx="10" cy="14" r="4" stroke="currentColor" strokeWidth="2" />
            <path d="M14 10l6-6M16 4h4v4" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
        </svg>
    );
}

function IconFemale({ className = '' }) {
    return (
        <svg
            width="24"
            height="24"
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden
            className={`block ${className}`.trim()}
        >
            <circle cx="12" cy="9" r="4" stroke="currentColor" strokeWidth="2" />
            <path d="M12 13v7M9 17h6" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
        </svg>
    );
}

/** @param {'male' | 'female'} value */
export function genderOptionIcon(value) {
    return value === 'male' ? <IconMale /> : <IconFemale />;
}
